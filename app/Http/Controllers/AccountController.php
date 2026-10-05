<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(): View
    {
        $user = $this->authenticatedUser()->load('serviceAreas');

        return view('account.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:2'],
            'contact_no' => ['required', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'street_address' => ['required', 'string', 'max:255'],
        ];

        if ($user->role === 'seller') {
            $rules['business_name'] = ['required', 'string', 'max:255'];
            $rules['line_of_business'] = ['nullable', 'string', 'max:255'];
        }

        if ($user->role === 'courier') {
            $rules['vehicle_type'] = ['nullable', 'string', 'max:100'];
            $rules['plate_number'] = ['nullable', 'string', 'max:20'];
        }

        $user->update($request->validate($rules));

        return back()->with('success', 'Your account details have been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        Auth::logoutOtherDevices($validated['current_password']);
        $this->authenticatedUser()->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', 'Your password has been changed.');
    }
}
