<?php

namespace App\Http\Controllers;

use App\Models\StaffProfileRevision;
use App\Models\User;
use App\Services\BuyerAddressService;
use App\Services\PsgcAddressService;
use App\Services\StaffProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AccountController extends Controller
{
    public function edit(BuyerAddressService $addressBook): View
    {
        $user = $this->authenticatedUser()->load('serviceAreas');
        $followedShops = collect();
        if ($user->role === 'buyer') {
            $addressBook->importProfileAddress($user);
            $user->loadCount('addresses');
            $followedShops = $user->followedShops()->where('role', 'seller')->where('status', 'approved')->orderByPivot('created_at', 'desc')->paginate(6, ['users.*'], 'shops_page')->withQueryString();
        }

        $profileRevisions = in_array($user->role, ['seller', 'courier', 'sorting_center'], true)
            ? StaffProfileRevision::where('user_id', $user->id)->with('reviewer')->latest()->paginate(10, ['*'], 'requests_page')->withQueryString()
            : null;

        return view('account.edit', compact('user', 'followedShops', 'profileRevisions'));
    }

    public function update(Request $request, PsgcAddressService $psgcAddressService, BuyerAddressService $addressBook): RedirectResponse
    {
        $user = $this->authenticatedUser();
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:2'],
            'contact_no' => ['required', 'string', 'max:20'],
            ...$psgcAddressService->validationRules(),
        ];

        if ($user->role === 'seller') {
            $rules['business_name'] = ['required', 'string', 'max:255'];
            $rules['line_of_business'] = ['nullable', 'string', 'max:255', Rule::in([...config('web-commerce.categories'), ...array_keys(config('web-commerce.category_aliases')), $user->line_of_business])];
        }

        if ($user->role === 'courier') {
            $rules['vehicle_type'] = ['nullable', Rule::in(array_filter([...array_keys(config('rider-vehicles.types')), $user->vehicle_type]))];
            $rules['plate_number'] = ['nullable', 'string', 'max:20'];
            $rules['vehicle_category'] = ['nullable', Rule::in(array_keys(config('rider-vehicles.categories')))];
            $rules['vehicle_description'] = ['nullable', 'string', 'max:120'];
            $rules['license_number'] = ['nullable', 'regex:/^[A-Za-z]\d{2}-\d{2}-\d{6}$/'];
            $rules['license_expires_at'] = ['nullable', 'date', 'after:today'];
        }

        $validated = $request->validate($rules);
        $hasAddress = $request->hasAny(['address_mode', 'province', 'municipality', 'region_code', 'manual_province']);
        $address = $hasAddress ? $psgcAddressService->resolve($validated) : [];
        unset(
            $validated['address_mode'],
            $validated['region_code'],
            $validated['province_code'],
            $validated['municipality_code'],
            $validated['barangay_code'],
            $validated['manual_region'],
            $validated['manual_province'],
            $validated['manual_municipality'],
            $validated['manual_barangay'],
        );
        if (in_array($user->role, ['seller', 'courier', 'sorting_center'], true)) {
            app(StaffProfileService::class)->submit($user, $user, array_merge($validated, $address));

            return back()->with('success', 'Your proposed profile changes were submitted for admin review. Official details remain in effect until approved.');
        }
        DB::transaction(function () use ($user, $validated, $address, $hasAddress, $addressBook): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->update(array_merge($validated, $address));
            if ($user->role === 'buyer' && $hasAddress) {
                $addressBook->syncProfileLocation($user);
            }
        });

        return back()->with('success', 'Your account details have been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols()],
        ]);

        Auth::logoutOtherDevices($validated['current_password']);
        $this->authenticatedUser()->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', 'Your password has been changed.');
    }
}
