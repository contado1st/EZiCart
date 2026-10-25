<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProfileAppearanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileAppearanceController extends Controller
{
    public function update(Request $request, ProfileAppearanceService $profiles)
    {
        $data = $request->validate([
            'profile_display_name' => ['nullable', 'string', 'min:2', 'max:50'],
            'profile_bio' => ['nullable', 'string', 'max:1000'],
            'profile_theme' => ['required', Rule::in(array_keys(ProfileAppearanceService::THEMES))],
            'remove_avatar' => ['nullable', 'boolean'], 'remove_banner' => ['nullable', 'boolean'],
            'avatar' => [Rule::prohibitedIf($request->boolean('remove_avatar')), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', Rule::dimensions()->minWidth(96)->minHeight(96)->maxWidth(2048)->maxHeight(2048)],
            'banner' => [Rule::prohibitedIf($request->boolean('remove_banner')), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', Rule::dimensions()->minWidth(600)->minHeight(200)->maxWidth(4096)->maxHeight(2048)],
        ]);
        $profiles->update($request->user(), $data);

        return back()->with('success', 'Your profile appearance has been saved.');
    }

    public function media(Request $request, User $user, string $type)
    {
        abort_unless(in_array($type, ['avatar', 'banner'], true), 404);
        abort_unless(($user->role === 'seller' && $user->status === 'approved') || ($request->user()?->id === $user->id && $user->status === 'approved'), 404);
        $path = $user->{'profile_'.$type.'_path'};
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
