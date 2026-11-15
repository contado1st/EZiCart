<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\BuyerEmailChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailChangeController extends Controller
{
    public function store(Request $request, BuyerEmailChangeService $changes): RedirectResponse
    {
        $data = $request->validate(['new_email' => ['required', 'email', 'max:255'], 'email_password' => ['required', 'string']]);
        $changes->request($request->user(), $data['new_email'], $data['email_password']);

        return back()->with('success', 'A verification code was sent to your proposed email. Your current login email remains active.');
    }

    public function update(Request $request, BuyerEmailChangeService $changes): RedirectResponse
    {
        $data = $request->validate(['email_code' => ['required', 'digits:6']]);
        $buyer = $changes->confirm($request->user(), $data['email_code']);
        Auth::setUser($buyer);
        $request->session()->regenerate();
        $request->session()->put('web_auth_version', (int) $buyer->web_auth_version);

        return back()->with('success', 'Your verified login email has been updated. Other sessions for this buyer must sign in again.');
    }
}
