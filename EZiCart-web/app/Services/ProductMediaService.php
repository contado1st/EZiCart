<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductMediaService
{
    public function rules(?Product $product = null): array
    {
        return [
            'gallery_images' => ['nullable', 'array', 'max:'.config('product-media.gallery_image_limit')],
            'gallery_images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('product-media.image_max_kb')],
            'product_video' => ['nullable', 'file', 'mimes:mp4,webm', 'mimetypes:video/mp4,video/webm', 'max:'.config('product-media.video_max_kb')],
            'remove_media' => $product ? ['nullable', 'array', 'max:9'] : ['prohibited'],
            'remove_media.*' => ['integer', 'distinct', Rule::exists('product_media', 'id')->where('product_id', $product?->id ?? 0)],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    /** Store validated uploads; caller deletes these if its database transaction fails. */
    public function stage(Request $request, bool $firstImageIsCover = false): array
    {
        $staged = [];
        try {
            $images = array_values((array) $request->file('gallery_images', []));
            if ($firstImageIsCover) {
                array_shift($images);
            }
            $files = array_map(fn ($file) => ['kind' => 'image', 'file' => $file], $images);
            if ($request->hasFile('product_video')) {
                $files[] = ['kind' => 'video', 'file' => $request->file('product_video')];
            }
            foreach ($files as $entry) {
                $path = $entry['file']->store('products/media', 'public');
                if (! is_string($path)) {
                    throw new RuntimeException('Product media could not be stored.');
                }
                $staged[] = ['kind' => $entry['kind'], 'path' => $path, 'mime_type' => $entry['file']->getMimeType()];
            }

            return $staged;
        } catch (Throwable $exception) {
            $this->deleteFiles(array_column($staged, 'path'));
            throw $exception;
        }
    }

    /** The caller must hold the product row lock (or own the newly inserted row). */
    public function apply(Product $product, array $staged, array $removeIds): void
    {
        $current = $product->media()->get();
        $replaceVideo = collect($staged)->contains('kind', 'video');
        $removed = $current->filter(fn ($media) => in_array($media->id, $removeIds) || ($replaceVideo && $media->kind === 'video'));
        $remaining = $current->whereNotIn('id', $removed->pluck('id'));
        $imageCount = $remaining->where('kind', 'image')->count() + collect($staged)->where('kind', 'image')->count();
        if ($imageCount > (int) config('product-media.gallery_image_limit')) {
            throw ValidationException::withMessages(['gallery_images' => 'Keep at most '.config('product-media.gallery_image_limit').' gallery images. Select existing images to remove before adding more.']);
        }
        $product->media()->whereIn('id', $removed->pluck('id'))->delete();
        $position = (int) $current->max('sort_order');
        foreach ($staged as $entry) {
            $product->media()->create([...$entry, 'sort_order' => ++$position]);
        }
        if ($removed->isNotEmpty()) {
            $paths = $removed->pluck('path')->all();
            DB::afterCommit(fn () => $this->deleteFiles($paths));
        }
    }

    public function deleteFiles(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            try {
                if (! Storage::disk('public')->delete($path)) {
                    report(new RuntimeException('A removed product media file could not be deleted.'));
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
