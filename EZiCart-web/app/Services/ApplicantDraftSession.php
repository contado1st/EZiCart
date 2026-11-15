<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class ApplicantDraftSession
{
    public function begin(Request $request, User $applicant): void
    {
        abort_unless($applicant->role === 'seller' && $applicant->status === 'pending' && $applicant->email_verified_at !== null, 403);
        $request->session()->put('applicant_drafts', [
            'user_id' => $applicant->id, 'expires_at' => now()->addMinutes(30)->timestamp,
            'fingerprint' => $this->fingerprint($applicant), 'tab' => $this->tab($request),
        ]);
    }

    public function resolve(Request $request): ?User
    {
        $capability = $request->session()->get('applicant_drafts');
        if (! is_array($capability) || (int) ($capability['expires_at'] ?? 0) <= now()->timestamp
            || ! hash_equals($capability['tab'] ?? '', $this->tab($request))) {
            return null;
        }
        $applicant = User::find($capability['user_id'] ?? 0);

        return $applicant && $applicant->role === 'seller' && $applicant->status === 'pending'
            && $applicant->email_verified_at !== null && hash_equals($capability['fingerprint'] ?? '', $this->fingerprint($applicant))
            ? $applicant : null;
    }

    private function fingerprint(User $user): string
    {
        return hash('sha256', $user->password.'|'.$user->email.'|'.(int) $user->web_auth_version);
    }

    private function tab(Request $request): string
    {
        return hash('sha256', strtolower((string) ($request->header('X-Ezi-Tab-Session') ?? $request->query('__tab') ?? 'classic')));
    }
}
