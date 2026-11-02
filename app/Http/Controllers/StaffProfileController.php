<?php

namespace App\Http\Controllers;

use App\Models\StaffProfileRevision;
use App\Models\User;
use App\Services\ProfileAppearanceService;
use App\Services\StaffProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StaffProfileController extends Controller
{
    public function index(Request $request, StaffProfileService $profiles)
    {
        abort_unless(in_array($request->user()->role, ['courier', 'sorting_center', 'logistics_admin'], true), 403);
        $staff = User::whereIn('role', ['courier', 'sorting_center'])->with('serviceAreas')->orderBy('first_name')->get()
            ->filter(fn ($person) => $profiles->canView($request->user(), $person));

        return view('staff.index', compact('staff'));
    }

    public function show(Request $request, User $staff, StaffProfileService $profiles)
    {
        abort_unless($profiles->canView($request->user(), $staff), 403);
        $staff->load('serviceAreas', 'logisticsOwner');
        $canViewHistory = $profiles->canViewHistory($request->user(), $staff);
        $revisions = $canViewHistory ? StaffProfileRevision::where('user_id', $staff->id)->with('reviewer')->latest()->paginate(10) : null;

        return view('staff.show', compact('staff', 'revisions', 'canViewHistory'));
    }

    public function update(Request $request, User $staff, StaffProfileService $profiles, ProfileAppearanceService $images)
    {
        abort_unless($request->user()->id === $staff->id && in_array($staff->role, ['courier', 'sorting_center'], true), 403);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50'], 'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'contact_no' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'profile_display_name' => ['nullable', 'string', 'min:2', 'max:50'], 'profile_bio' => ['nullable', 'string', 'max:1000'],
            'vehicle_type' => ['nullable', 'string', 'max:100'], 'vehicle_category' => ['nullable', 'in:two_wheel,four_wheel'], 'vehicle_description' => ['nullable', 'string', 'max:120'],
            'plate_number' => ['nullable', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]{1,19}$/'],
            'license_number' => ['nullable', 'regex:/^[A-Za-z]\d{2}-\d{2}-\d{6}$/'], 'license_expires_at' => ['nullable', 'date', 'after:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
        ]);
        if ($staff->role !== 'courier') {
            $data = collect($data)->except(['vehicle_type', 'vehicle_category', 'vehicle_description', 'plate_number', 'license_number', 'license_expires_at'])->all();
        }
        $path = null;
        try {
            if ($request->hasFile('avatar')) {
                $path = 'staff-proposals/'.$staff->id.'/'.Str::uuid().'.jpg';
                if (! Storage::disk('private')->put($path, $images->jpeg($request->file('avatar'), 400, 400, 'avatar'))) {
                    throw new \RuntimeException('The profile photo could not be saved.');
                }
                $data['profile_avatar_path'] = $path;
            }
            unset($data['avatar']);
            $profiles->submit($request->user(), $staff, $data);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }

        return back()->with('success', 'Profile changes submitted. Your verified details remain active until approved.');
    }

    public function reviews(Request $request)
    {
        abort_unless($request->user()->isLogisticsAdmin(), 403);
        $revisions = StaffProfileRevision::with(['user', 'reviewer'])->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest()->paginate(20);

        return view('staff.reviews', compact('revisions'));
    }

    public function review(Request $request, StaffProfileRevision $revision, StaffProfileService $profiles)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:5', 'max:1000']]);
        $profiles->review($request->user(), $revision, $data['decision'] === 'approve', $data['reason'] ?? null);

        return back()->with('success', 'Profile review recorded.');
    }

    public function avatar(Request $request, User $staff, StaffProfileService $profiles)
    {
        abort_unless($profiles->canView($request->user(), $staff), 403);

        return $this->image($staff->profile_avatar_path);
    }

    public function proposedAvatar(Request $request, StaffProfileRevision $revision)
    {
        abort_unless($request->user()->isLogisticsAdmin() || $request->user()->id === $revision->user_id, 403);

        return $this->image($revision->proposed_values['profile_avatar_path'] ?? null);
    }

    private function image(?string $path)
    {
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
