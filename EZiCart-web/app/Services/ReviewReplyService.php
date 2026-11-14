<?php

namespace App\Services;

use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewReplyService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function save(User $seller, Review $review, string $body, ?int $version = null): void
    {
        DB::transaction(function () use ($seller, $review, $body, $version) {
            $locked = Review::whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($seller->role === 'seller' && $seller->status === 'approved' && (int) $locked->order->seller_id === $seller->id, 403);
            $reply = ReviewReply::where('review_id', $locked->id)->lockForUpdate()->first();
            if ($version === null && $reply) {
                abort_unless($reply->body === $body, 409, 'A seller reply already exists. Edit it instead.');

                return;
            }
            if ($version !== null) {
                abort_unless($reply && (int) $reply->version === $version, 409, 'The reply changed. Reload before editing.');
            }
            $before = $reply?->only(['body', 'version']) ?? [];
            $reply ??= new ReviewReply(['review_id' => $locked->id, 'seller_id' => $seller->id, 'version' => 0]);
            $reply->fill(['body' => $body, 'version' => $reply->version + 1])->save();
            $this->audits->record($seller, $reply, 'seller_review_reply_saved', $before, $reply->only(['body', 'version']), 'Seller responded to verified feedback; buyer feedback is unchanged.');
        });
    }
}
