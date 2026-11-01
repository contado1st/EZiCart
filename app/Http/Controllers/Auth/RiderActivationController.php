<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RiderActivationController extends Controller
{
    public function show(string $token): View
    {
        $activation = DB::table('rider_activation_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $activation) {
            return view('auth.activate-rider', [
                'token' => null,
                'activationError' => 'This activation link is invalid or has already been used. Ask your logistics company to send a new one.',
            ]);
        }

        if (now()->greaterThanOrEqualTo($activation->expires_at)) {
            return view('auth.activate-rider', [
                'token' => null,
                'activationError' => 'This activation link has expired. Ask your logistics company to send a new one.',
            ]);
        }

        $rider = \App\Models\User::query()->find($activation->user_id);
        if (! $rider || $rider->role !== 'courier' || $rider->status !== 'pending_activation') {
            return view('auth.activate-rider', [
                'token' => null,
                'activationError' => 'This rider activation link is no longer active. Contact your logistics company for help.',
            ]);
        }

        return view('auth.activate-rider', ['token' => $token, 'activationError' => null]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        $activated = DB::transaction(function () use ($validated): bool {
            $activation = DB::table('rider_activation_tokens')
                ->where('token_hash', hash('sha256', $validated['token']))
                ->lockForUpdate()
                ->first();

            if (! $activation || now()->greaterThanOrEqualTo($activation->expires_at)) {
                throw ValidationException::withMessages(['token' => 'This activation link has expired or was already used. Ask your logistics company to send a new one.']);
            }

            $rider = \App\Models\User::query()->whereKey($activation->user_id)->lockForUpdate()->first();
            if (! $rider || $rider->role !== 'courier' || $rider->status !== 'pending_activation') {
                throw ValidationException::withMessages(['token' => 'This rider activation link is no longer active. Ask your logistics company for help.']);
            }

            $rider->forceFill([
                'password' => Hash::make($validated['password']),
                'status' => 'approved',
                'email_verified_at' => now(),
            ])->save();
            DB::table('rider_activation_tokens')->where('id', $activation->id)->delete();

            return true;
        });

        return redirect()->route('login')->with('success', $activated
            ? 'Your rider account is active. You can now sign in.'
            : 'The activation link could not be used. Ask your logistics company for a new link.');
    }
}
