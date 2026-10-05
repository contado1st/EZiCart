<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAttempt;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureDocumentController extends Controller
{
    /** @var array<string, string|list<string>> */
    private const USER_DOCUMENTS = [
        'identity' => ['id_path', 'id_upload_path'],
        'permit' => ['permit_path', 'business_permit_path'],
        'license' => 'license_path',
        'vehicle' => ['or_cr_path', 'or_cr_upload_path'],
    ];

    public function userDocument(User $user, string $type): StreamedResponse
    {
        $actor = $this->authenticatedUser();
        abort_unless($actor->role === 'admin' || ($actor->role === 'sorting_center' && $user->role === 'courier'), 403);

        return $this->downloadUserDocument($user, $type);
    }

    public function ownDocument(string $type): StreamedResponse
    {
        return $this->downloadUserDocument($this->authenticatedUser(), $type);
    }

    public function disputeEvidence(Dispute $dispute): StreamedResponse
    {
        abort_unless($this->authenticatedUser()->role === 'admin', 403);
        abort_unless(is_string($dispute->evidence_path) && Storage::disk('private')->exists($dispute->evidence_path), 404);

        return Storage::disk('private')->download($dispute->evidence_path, basename($dispute->evidence_path));
    }

    public function deliveryProof(DeliveryAttempt $attempt): StreamedResponse
    {
        $actor = $this->authenticatedUser();
        $attempt->loadMissing('order');
        $authorized = $actor->role === 'admin'
            || ($actor->role === 'buyer' && $attempt->order->buyer_id === $actor->id)
            || ($actor->role === 'courier' && $attempt->rider_id === $actor->id)
            || $actor->role === 'sorting_center';
        abort_unless($authorized, 403);
        abort_unless(is_string($attempt->proof_path) && Storage::disk('private')->exists($attempt->proof_path), 404);

        return Storage::disk('private')->download($attempt->proof_path, basename($attempt->proof_path));
    }

    private function downloadUserDocument(User $user, string $type): StreamedResponse
    {
        abort_unless(isset(self::USER_DOCUMENTS[$type]), 404);

        $fields = (array) self::USER_DOCUMENTS[$type];
        $path = collect($fields)->map(fn (string $field): ?string => $user->{$field})->first(fn (?string $candidate): bool => filled($candidate));
        abort_unless(is_string($path) && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->download($path, basename($path));
    }
}
