<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BuyerAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProfileApiController extends Controller
{
    /**
     * Update buyer personal info
     */
    public function updateProfile(Request $request, BuyerAddressService $addressBook): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'contact_no' => ['required', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'region' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'digits:4'],
        ]);

        $buyer->update($validated);
        $addressBook->syncProfileLocation($buyer);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $buyer->id,
                'first_name' => $buyer->first_name,
                'last_name' => $buyer->last_name,
                'name' => trim("{$buyer->first_name} {$buyer->last_name}"),
                'email' => $buyer->email,
                'contact_no' => $buyer->contact_no,
                'birthday' => $buyer->birthday?->format('Y-m-d'),
                'region' => $buyer->region,
                'province' => $buyer->province,
                'municipality' => $buyer->municipality,
                'barangay' => $buyer->barangay,
                'street_address' => $buyer->street_address,
                'house_number' => $buyer->house_number,
                'postal_code' => $buyer->postal_code,
                'profile_display_name' => $buyer->profile_display_name,
                'profile_bio' => $buyer->profile_bio,
                'profile_theme' => $buyer->profile_theme ?: 'rose',
                'has_avatar' => !empty($buyer->profile_avatar_path),
                'has_banner' => !empty($buyer->profile_banner_path),
                'avatar_url' => $buyer->profile_avatar_path ? url('/api/account/profile/media/avatar?v='.($buyer->updated_at?->timestamp ?? time())) : null,
                'banner_url' => $buyer->profile_banner_path ? url('/api/account/profile/media/banner?v='.($buyer->updated_at?->timestamp ?? time())) : null,
            ],
        ]);
    }

    /**
     * Update profile appearance (display name, bio, theme, avatar, banner)
     */
    public function updateAppearance(Request $request, \App\Services\ProfileAppearanceService $profiles): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'profile_display_name' => ['nullable', 'string', 'min:2', 'max:50'],
            'profile_bio' => ['nullable', 'string', 'max:1000'],
            'profile_theme' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Services\ProfileAppearanceService::THEMES))],
            'remove_avatar' => ['nullable', 'boolean'],
            'remove_banner' => ['nullable', 'boolean'],
            'avatar' => [\Illuminate\Validation\Rule::prohibitedIf($request->boolean('remove_avatar')), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner' => [\Illuminate\Validation\Rule::prohibitedIf($request->boolean('remove_banner')), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if (empty($validated['profile_theme'])) {
            $validated['profile_theme'] = $user->profile_theme ?: 'rose';
        }

        $profiles->update($user, $validated);
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile appearance updated successfully.',
            'user' => [
                'profile_display_name' => $user->profile_display_name,
                'profile_bio' => $user->profile_bio,
                'profile_theme' => $user->profile_theme ?: 'rose',
                'has_avatar' => !empty($user->profile_avatar_path),
                'has_banner' => !empty($user->profile_banner_path),
                'avatar_url' => $user->profile_avatar_path ? url('/api/account/profile/media/avatar?v='.($user->updated_at?->timestamp ?? time())) : null,
                'banner_url' => $user->profile_banner_path ? url('/api/account/profile/media/banner?v='.($user->updated_at?->timestamp ?? time())) : null,
            ],
        ]);
    }

    /**
     * Serve private media for authenticated user or approved seller
     */
    public function media(Request $request, string $type): mixed
    {
        abort_unless(in_array($type, ['avatar', 'banner'], true), 404);
        $user = $request->user();
        if (!$user && $request->filled('token')) {
            $token = \App\Models\ApiToken::findByPlainTextToken($request->query('token'));
            $user = $token?->tokenable;
        }
        abort_unless($user, 401);
        $path = $user->{'profile_'.$type.'_path'};
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('private')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('private')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $buyer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided current password does not match our records.',
            ], 422);
        }

        if (Hash::check($validated['password'], $buyer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'New password must be different from your current password.',
            ], 422);
        }

        $buyer->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        // Revoke other active sessions on other devices
        $currentToken = \App\Models\ApiToken::findByPlainTextToken($request->bearerToken());
        if ($currentToken) {
            $buyer->apiTokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }
}

