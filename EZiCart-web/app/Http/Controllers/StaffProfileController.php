<?php

namespace App\Http\Controllers;

use App\Models\StaffProfileRevision;
use App\Models\User;
use App\Services\StaffProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffProfileController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isCommerceAdmin() || $request->user()->isLogisticsAdmin(), 403);
        abort_unless($request->user()->status === 'approved', 403);
        $staff = User::whereIn('role', array_intersect($request->user()->managedAccountRoles(), ['seller', 'courier', 'sorting_center']))
            ->with('serviceAreas')->orderBy('first_name')->orderBy('id')->paginate(25)->withQueryString();

        return view('staff.index', compact('staff'));
    }

    public function show(Request $request, User $staff, StaffProfileService $profiles)
    {
        abort_unless($profiles->canView($request->user(), $staff), 403);
        $staff->load('serviceAreas', 'logisticsOwner');
        $canViewHistory = $profiles->canViewHistory($request->user(), $staff);
        $revisions = $canViewHistory ? StaffProfileRevision::where('user_id', $staff->id)->with('reviewer')->latest()->paginate(25) : null;

        return view('staff.show', compact('staff', 'revisions', 'canViewHistory'));
    }

    public function reviews(Request $request)
    {
        abort_unless($request->user()->isCommerceAdmin() || $request->user()->isLogisticsAdmin(), 403);
        $filters = $request->validate(['status' => ['nullable', 'in:pending,approved,rejected,all']]);
        $status = $filters['status'] ?? 'pending';
        $revisions = StaffProfileRevision::with(['user', 'reviewer'])
            ->whereHas('user', fn ($query) => $query->whereIn('role', $request->user()->managedAccountRoles()))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();
        $reviewRoute = $request->user()->isCommerceAdmin() ? 'admin.profile-reviews.review' : 'logistics-admin.profile-reviews.review';

        return view('staff.reviews', compact('revisions', 'status', 'reviewRoute'));
    }

    public function review(Request $request, StaffProfileRevision $revision, StaffProfileService $profiles)
    {
        abort_unless($profiles->canView($request->user(), $revision->user), 403);
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
        return $this->proposedMedia($request, $revision, 'avatar', app(StaffProfileService::class));
    }

    public function proposedMedia(Request $request, StaffProfileRevision $revision, string $type, StaffProfileService $profiles)
    {
        abort_unless(in_array($type, ['avatar', 'banner'], true), 404);
        abort_unless($request->user()->id === $revision->user_id || $profiles->canView($request->user(), $revision->user), 403);

        $values = $request->query('version') === 'before' ? $revision->previous_values : $revision->proposed_values;

        return $this->image($values['profile_'.$type.'_path'] ?? null);
    }

    private function image(?string $path)
    {
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
