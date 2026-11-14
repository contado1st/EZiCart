<?php

namespace App\Services;

use App\Models\ProductDraft;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductDraftService
{
    private const FIELDS = ['name', 'category', 'description', 'price', 'stock', 'weight_kg', 'length_mm', 'width_mm', 'height_mm', 'variations', 'variations_present', 'variations_enabled'];

    public function save(Request $request, ?ProductDraft $draft = null): ProductDraft
    {
        $this->authorize($request, $draft);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'], 'price' => ['nullable', 'numeric', 'min:0.01'],
            'stock' => ['nullable', 'integer', 'min:0'], 'weight_kg' => ['nullable', 'numeric', 'min:0.001', 'max:100000'],
            'length_mm' => ['nullable', 'integer', 'min:1', 'max:5000'], 'width_mm' => ['nullable', 'integer', 'min:1', 'max:5000'], 'height_mm' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'variations_present' => ['nullable', 'boolean'], 'variations' => ['nullable', 'array', 'max:100'],
            'variations_enabled' => ['nullable', 'boolean'],
            'variations.*.price' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'variations.*.type' => ['nullable', 'string', 'max:50'], 'variations.*.value' => ['nullable', 'string', 'max:50'],
            'variations.*.sku' => ['nullable', 'string', 'max:64'], 'variations.*.price_adjustment' => ['nullable', 'numeric'],
            'variations.*.stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variations.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ...app(ProductMediaService::class)->rules(),
            'clear_draft_media' => ['nullable', 'array', 'max:110'], 'clear_draft_media.*' => ['string', 'max:100'],
        ]);
        $payload = Arr::only($data, self::FIELDS);
        $payload['variations'] = array_map(fn ($row) => Arr::only($row, ['type', 'value', 'sku', 'price', 'price_adjustment', 'stock']), $payload['variations'] ?? []);
        $staged = [];
        try {
            foreach (Arr::dot($request->allFiles()) as $field => $file) {
                if ($field !== 'image' && $field !== 'product_video' && ! preg_match('/\A(?:gallery_images\.\d+|variations\.\d+\.image)\z/', $field)) {
                    continue;
                }
                $path = $file->store('product-drafts/'.$this->actor($request)->id, 'private');
                if (! is_string($path)) {
                    throw new \RuntimeException('Draft media could not be saved.');
                }
                $staged[$field] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'variant_key' => $this->variantKey($payload, $field)];
            }

            return DB::transaction(function () use ($request, $draft, $payload, $data, $staged): ProductDraft {
                $this->lockApplicant($request);
                $locked = $draft ? ProductDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail() : new ProductDraft(['user_id' => $this->actor($request)->id]);
                $this->authorize($request, $locked);
                $uploads = $locked->uploads ?? [];
                $removed = [];
                foreach ($uploads as $field => $upload) {
                    if (in_array($field, $data['clear_draft_media'] ?? [], true) || isset($staged[$field])
                        || (str_starts_with($field, 'gallery_images.') && $request->hasFile('gallery_images'))
                        || (str_starts_with($field, 'variations.') && $this->variantKey($payload, $field) !== ($upload['variant_key'] ?? null))) {
                        $removed[] = $upload['path'];
                        unset($uploads[$field]);
                    }
                }
                $locked->fill(['payload' => $payload, 'uploads' => [...$uploads, ...$staged]])->save();
                DB::afterCommit(fn () => $this->deleteFiles($removed));

                return $locked;
            });
        } catch (\Throwable $exception) {
            $this->deleteFiles(array_column($staged, 'path'));
            throw $exception;
        }
    }

    /** Only called for an owned, locked draft by the normal product submission. */
    public function hydrate(Request $request, ProductDraft $draft): Request
    {
        $this->authorize($request, $draft);
        $request->mergeIfMissing($draft->payload);
        $incoming = $request->allFiles();
        $files = $incoming;
        foreach ($draft->uploads as $field => $upload) {
            if (in_array($field, (array) $request->input('clear_draft_media', []), true)
                || ($field === 'image' && $request->boolean('remove_cover'))
                || (str_starts_with($field, 'gallery_images.') && isset($incoming['gallery_images']))
                || (str_starts_with($field, 'variations.') && $this->variantKey($request->all(), $field) !== ($upload['variant_key'] ?? null))
                || $request->hasFile($field)) {
                continue;
            }
            if (! Storage::disk('private')->exists($upload['path'])) {
                throw ValidationException::withMessages(['image' => 'A saved draft file is missing. Save a replacement or remove it before submitting.']);
            }
            Arr::set($files, $field, new UploadedFile(Storage::disk('private')->path($upload['path']), $upload['name'], null, null, true));
        }
        // Validation may already have populated Laravel's convertedFiles cache.
        $hydrated = Request::createFrom($request);
        $hydrated->files->replace($files);

        return $hydrated;
    }

    public function authorize(Request $request, ?ProductDraft $draft = null): void
    {
        $actor = $this->actor($request);
        abort_unless($actor->role === 'seller' && ($actor->status === 'approved' || ($actor->status === 'pending' && $request->routeIs('applicant-drafts.*') && $request->attributes->get('draft_applicant') === $actor)), 403);
        if ($draft) {
            abort_unless((int) $draft->user_id === $actor->id, 404);
            abort_if($draft->submitted_at !== null, 409, 'This draft has already been submitted. Edit the submitted product instead.');
        }
    }

    public function actor(Request $request): User
    {
        $actor = $request->user() ?? $request->attributes->get('draft_applicant');
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    /** Recheck revocation while holding the account lock before private writes. */
    public function lockApplicant(Request $request): void
    {
        if ($request->routeIs('applicant-drafts.*')) {
            User::whereKey($this->actor($request)->id)->lockForUpdate()->firstOrFail();
            $fresh = app(ApplicantDraftSession::class)->resolve($request);
            abort_unless($fresh, 403, 'Your application status changed. Sign in again.');
            $request->attributes->set('draft_applicant', $fresh);
        }
    }

    private function variantKey(array $payload, string $field): ?string
    {
        if (! preg_match('/\Avariations\.(\d+)\.image\z/', $field, $match)) {
            return null;
        }
        $row = $payload['variations'][$match[1]] ?? null;

        return $row ? trim($row['type'] ?? '').':'.trim($row['value'] ?? '') : null;
    }

    public function deleteFiles(array $paths): void
    {
        try {
            if ($paths && ! Storage::disk('private')->delete($paths)) {
                report(new \RuntimeException('Some retired private draft files could not be removed.'));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
