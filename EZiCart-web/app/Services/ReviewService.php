<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReviewService
{
    public function rules(?Review $review = null): array
    {
        return ['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['required', 'image', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'version' => $review ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'remove_photos' => $review ? ['nullable', 'array', 'max:5'] : ['prohibited'],
            'remove_photos.*' => ['integer', 'distinct', Rule::exists('review_photos', 'id')->where('review_id', $review?->id ?? 0)->whereNull('removed_at')]];
    }

    public function authorizeOwner(Review $review, User $buyer): void
    {
        abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved'
            && (int) $review->buyer_id === $buyer->id && (int) $review->order?->buyer_id === $buyer->id, 404);
    }

    public function save(User $buyer, Order $order, array $data, array $files, ?Review $review = null): Review
    {
        abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved' && $order->buyer_id === $buyer->id, 403);
        if ($review) {
            $this->authorizeOwner($review, $buyer);
        }
        $staged = [];
        try {
            foreach ($files as $file) {
                $path = app(ImageUploadProcessor::class)->image($file, 'reviews/photos', 'private', 'photos', 1600, 1600, 16777216);
                if (! is_string($path)) {
                    throw new \RuntimeException('Review photo could not be saved.');
                }
                $staged[] = ['path' => $path, 'mime_type' => $file->getMimeType()];
            }

            return DB::transaction(function () use ($buyer, $order, $data, $staged, $review): Review {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->buyer_id === $buyer->id, 403);
                if ($review) {
                    $saved = Review::whereKey($review->id)->lockForUpdate()->firstOrFail();
                    $this->authorizeOwner($saved, $buyer);
                    abort_unless((int) $saved->order_id === $lockedOrder->id, 404);
                    if ($saved->revision_version !== (int) $data['version']) {
                        throw ValidationException::withMessages(['version' => 'This review changed in another tab. Refresh before editing again.']);
                    }
                    $this->record($saved, $buyer);
                } else {
                    abort_unless($lockedOrder->status === 'COMPLETED', 422, 'Only completed purchases can be reviewed.');
                    if (Review::where('order_id', $lockedOrder->id)->where('product_id', $data['product_id'])->exists()) {
                        throw ValidationException::withMessages(['review' => 'You have already reviewed this product for this order.']);
                    }
                    $items = $lockedOrder->items()->where('product_id', $data['product_id'])->get();
                    abort_if($items->isEmpty(), 422, 'This product is not part of this order.');
                    $saved = Review::create(['order_id' => $lockedOrder->id, 'product_id' => $data['product_id'], 'buyer_id' => $buyer->id, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null]);
                    $saved->forceFill(['purchase_context' => ['product_name' => $items->first()->product_name,
                        'variants' => $items->map(fn ($item) => $item->variation_info ?: 'Standard item')->unique()->values()->all()]])->save();
                }
                $remove = $data['remove_photos'] ?? [];
                if ($saved->photos()->whereNotIn('id', $remove)->count() + count($staged) > 5) {
                    throw ValidationException::withMessages(['photos' => 'Keep at most five photos. Select existing photos to remove before adding more.']);
                }
                $saved->photos()->whereIn('id', $remove)->update(['removed_at' => now()]);
                $saved->photos()->createMany($staged);
                $saved->forceFill(['rating' => $data['rating'], 'comment' => $data['comment'] ?? null,
                    'revision_version' => $review ? $saved->revision_version + 1 : 1])->save();
                $this->record($saved, $buyer);

                return $saved;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('private')->delete(array_column($staged, 'path'));
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['review' => 'A review already exists or changed. Refresh your purchase before trying again.']);
            }
            throw $exception;
        }
    }

    private function record(Review $review, User $actor): void
    {
        $review->revisions()->firstOrCreate(['version' => $review->revision_version], ['actor_id' => $actor->id,
            'snapshot' => ['rating' => $review->rating, 'comment' => $review->comment, 'photo_ids' => $review->photos()->pluck('id')->all()]]);
    }
}
