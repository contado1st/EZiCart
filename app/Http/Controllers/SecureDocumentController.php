<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAttempt;
use App\Models\Dispute;
use App\Models\User;
use App\Services\UserDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureDocumentController extends Controller
{
    public function __construct(private readonly UserDocumentService $documents) {}

    public function userDocument(User $user, string $type): StreamedResponse
    {
        $actor = $this->authenticatedUser();
        $authorized = $actor->canManageAccount($user);
        if ($actor->role === 'sorting_center' && $user->role === 'courier') {
            if ($user->logistics_owner_id !== null) {
                $authorized = (int) $user->logistics_owner_id === (int) $actor->id;
            }
            $hasMultipleHubs = User::query()->where('role', 'sorting_center')->where('status', 'approved')->limit(2)->count() > 1;
            $assignedToHub = DB::table('area_user')
                ->join('areas', 'areas.id', '=', 'area_user.area_id')
                ->where('area_user.user_id', $user->id)
                ->where('area_user.is_active', true)
                ->where('areas.is_active', true)
                ->where(function ($query) use ($actor, $hasMultipleHubs): void {
                    $query->where('areas.sorting_center_id', $actor->id);
                    if (! $hasMultipleHubs) {
                        $query->orWhereNull('areas.sorting_center_id');
                    }
                })
                ->exists();
            $authorized = $user->logistics_owner_id !== null
                ? (int) $user->logistics_owner_id === (int) $actor->id
                : ($assignedToHub || ! $hasMultipleHubs);
        }
        abort_unless($authorized, 403);

        return $this->downloadUserDocument($user, $type);
    }

    public function ownDocument(string $type): StreamedResponse
    {
        return $this->downloadUserDocument($this->authenticatedUser(), $type);
    }

    public function disputeEvidence(Dispute $dispute): StreamedResponse
    {
        abort_unless($this->authenticatedUser()->isCommerceAdmin(), 403);
        abort_unless(is_string($dispute->evidence_path) && Storage::disk('private')->exists($dispute->evidence_path), 404);

        return Storage::disk('private')->download($dispute->evidence_path, basename($dispute->evidence_path));
    }

    public function deliveryProof(DeliveryAttempt $attempt): StreamedResponse
    {
        $actor = $this->authenticatedUser();
        $attempt->loadMissing('order');
        $authorized = $actor->isCommerceAdmin() || $actor->isLogisticsAdmin()
            || ($actor->role === 'buyer' && $attempt->order->buyer_id === $actor->id)
            || ($actor->role === 'seller' && $attempt->order->seller_id === $actor->id)
            || ($actor->role === 'courier' && $attempt->rider_id === $actor->id)
            || ($actor->role === 'sorting_center' && (int) $attempt->order->sorting_center_id === $actor->id);
        abort_unless($authorized, 403);
        abort_unless(is_string($attempt->proof_path) && Storage::disk('private')->exists($attempt->proof_path), 404);

        return Storage::disk('private')->download($attempt->proof_path, basename($attempt->proof_path));
    }

    private function downloadUserDocument(User $user, string $type): StreamedResponse
    {
        $path = $this->documents->path($user, $type);
        abort_unless(is_string($path), 404);

        $disk = Storage::disk('private');
        $mime = $disk->mimeType($path);
        if (request()->boolean('preview') && in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            return $disk->response($path, basename($path), [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
            ], 'inline');
        }

        return $disk->download($path, basename($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
