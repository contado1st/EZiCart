<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $isUnverified = $user->status === 'unverified';

            if ($user->status !== 'approved' || $isUnverified) {
                $user->forceFill(['remember_token' => null])->save();
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = $isUnverified
                    ? 'Please verify your email address to access your account.'
                    : 'Your account is no longer active. Please contact support.';

                return redirect()->route('login')->with('error', $message);
            }
        }

        return $next($request);
    }
}
