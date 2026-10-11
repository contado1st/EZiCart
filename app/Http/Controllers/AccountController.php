<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\PsgcAddressService;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(): View
    {
        $user = $this->authenticatedUser()->load('serviceAreas');

        return view('account.edit', compact('user'));
    }

    public function update(Request $request, PsgcAddressService $psgcAddressService): RedirectResponse
    {
        $user = $this->authenticatedUser();
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:2'],
            'contact_no' => ['required', 'string', 'max:20'],
            'region_code' => ['required', 'regex:/^\d{10}$/'],
            'province_code' => ['required', 'regex:/^(?:\d{10}|NCR)$/'],
            'municipality_code' => ['required', 'regex:/^\d{10}$/'],
            'barangay_code' => ['required', 'regex:/^\d{10}$/'],
            'house_number' => ['required', 'string', 'max:100'],
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

        $validated = $request->validate($rules);
        $address = $psgcAddressService->resolve($validated);
        unset($validated['region_code'], $validated['province_code'], $validated['municipality_code'], $validated['barangay_code']);
        $user->update(array_merge($validated, $address));

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
