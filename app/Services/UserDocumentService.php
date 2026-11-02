<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserDocumentService
{
    private const DOCUMENTS = [
        'identity' => ['label' => 'Valid ID', 'fields' => ['id_path', 'id_upload_path']],
        'permit' => ['label' => 'Business permit', 'fields' => ['permit_path', 'business_permit_path']],
        'bir' => ['label' => 'BIR Form 2303', 'fields' => ['bir_certificate_path']],
        'sec' => ['label' => 'SEC certificate', 'fields' => ['sec_certificate_path']],
        'representative' => ['label' => 'Representative ID', 'fields' => ['representative_id_path']],
        'license' => ['label' => 'Driver’s license', 'fields' => ['license_path']],
        'vehicle' => ['label' => 'Vehicle OR/CR', 'fields' => ['or_cr_path', 'or_cr_upload_path']],
    ];

    public function path(User $user, string $type): ?string
    {
        $type = $type === 'dti' ? 'permit' : $type;
        foreach (self::DOCUMENTS[$type]['fields'] ?? [] as $field) {
            $path = $user->getAttribute($field);
            if (is_string($path) && $path !== '' && Storage::disk('private')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function submittedForReview(User $user): array
    {
        $expected = match ($user->role) {
            'seller' => ['identity', 'permit'],
            'sorting_center' => ['identity', 'sec', 'bir'],
            'courier' => ['identity', 'license', 'vehicle'],
            default => ['identity'],
        };
        $documents = [];
        foreach (self::DOCUMENTS as $type => $document) {
            $submitted = collect($document['fields'])->contains(fn (string $field): bool => filled($user->getAttribute($field)));
            if (! $submitted && ! in_array($type, $expected, true)) {
                continue;
            }
            $documents[] = ['type' => $type, 'label' => $document['label'], 'available' => $this->path($user, $type) !== null, 'submitted' => $submitted];
        }

        return $documents;
    }
}
