<?php

namespace App\Http\Middleware;

use App\Services\ApplicantDraftSession;
use Closure;
use Illuminate\Http\Request;

class AuthenticateApplicantDraft
{
    public function __construct(private ApplicantDraftSession $sessions) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->user() !== null) {
            $request->session()->forget('applicant_drafts');

            return redirect()->route('home');
        }
        $applicant = $this->sessions->resolve($request);
        if (! $applicant) {
            $request->session()->forget('applicant_drafts');

            return redirect()->route('login')->with('error', 'Your draft workspace expired or your application details changed. Sign in again. Saved drafts are retained.');
        }
        $request->attributes->set('draft_applicant', $applicant);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
