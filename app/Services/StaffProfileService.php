<?php

namespace App\Services;

use App\Models\StaffProfileRevision;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffProfileService
{
    public const FIELDS = ['first_name', 'last_name', 'middle_initial', 'contact_no', 'region', 'province', 'municipality', 'barangay', 'street_address', 'house_number', 'postal_code', 'vehicle_type', 'vehicle_category', 'vehicle_description', 'plate_number', 'license_number', 'license_expires_at', 'profile_display_name', 'profile_bio', 'profile_avatar_path', 'profile_banner_path', 'profile_theme', 'business_name', 'line_of_business'];

    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications, private RegistrationIdentityService $identities) {}

    public function canView(User $actor, User $staff): bool
    {
        return in_array($staff->role, ['seller', 'courier', 'sorting_center'], true)
            && $actor->canManageAccount($staff);
    }

    public function canViewHistory(User $actor, User $staff): bool
    {
        return $actor->id === $staff->id || $this->canView($actor, $staff);
    }

    public function reviewRoute(User $subject): string
    {
        return $subject->role === 'seller' ? 'admin.profile-reviews.index' : 'logistics-admin.profile-reviews.index';
    }

    public function submit(User $actor, User $staff, array $values, string $submissionReason = 'Profile changes await the responsible administrator’s review.'): StaffProfileRevision
    {
        $relatedHub = $actor->role === 'sorting_center' && $staff->role === 'courier'
            && ((int) $staff->logistics_owner_id === $actor->id || $staff->serviceAreas()->where('sorting_center_id', $actor->id)->exists());
        abort_unless($actor->status === 'approved' && ($this->canViewHistory($actor, $staff) || $relatedHub), 403);
        abort_unless(in_array($staff->role, ['seller', 'courier', 'sorting_center'], true), 403);

        return DB::transaction(function () use ($actor, $staff, $values, $submissionReason) {
            $staff = User::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            if (StaffProfileRevision::where('pending_user_id', $staff->id)->exists()) {
                throw ValidationException::withMessages(['profile' => 'A profile update is already awaiting review.']);
            }
            $values = Arr::only($values, self::FIELDS);
            if ($staff->role !== 'courier') {
                $values = Arr::except($values, ['vehicle_type', 'vehicle_category', 'vehicle_description', 'plate_number', 'license_number', 'license_expires_at']);
            }
            if ($staff->role !== 'seller') {
                $values = Arr::except($values, ['business_name', 'line_of_business']);
            }
            if (isset(config('rider-vehicles.types')[$values['vehicle_type'] ?? ''])) {
                $values['vehicle_category'] = config('rider-vehicles.types')[$values['vehicle_type']];
            }
            validator($values, [
                'first_name' => ['sometimes', 'required', 'string', 'min:2', 'max:50'], 'last_name' => ['sometimes', 'required', 'string', 'min:2', 'max:50'],
                'contact_no' => ['sometimes', 'required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
                'plate_number' => ['nullable', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]{1,19}$/'],
                'license_number' => ['nullable', 'regex:/^[A-Za-z]\d{2}-\d{2}-\d{6}$/'],
                'vehicle_category' => ['nullable', Rule::in(array_keys(config('rider-vehicles.categories')))],
                'vehicle_type' => ['nullable', Rule::in(array_filter([...array_keys(config('rider-vehicles.types')), $staff->vehicle_type]))],
                'profile_theme' => ['sometimes', Rule::in(array_keys(ProfileAppearanceService::THEMES))],
                'business_name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            ])->validate();
            $values = array_filter($values, fn ($value, $key) => (string) $staff->getRawOriginal($key) !== (string) $value, ARRAY_FILTER_USE_BOTH);
            if ($values === []) {
                throw ValidationException::withMessages(['profile' => 'Change at least one profile field before submitting.']);
            }
            $this->validateIdentity($staff, $values);
            $revision = StaffProfileRevision::create(['user_id' => $staff->id, 'submitted_by' => $actor->id, 'pending_user_id' => $staff->id,
                'previous_values' => Arr::only($staff->getRawOriginal(), array_keys($values)), 'proposed_values' => $values]);
            $this->audits->record($actor, $revision, 'staff_profile_submitted', $revision->previous_values, $values, $submissionReason);
            $roles = $staff->role === 'seller' ? ['admin', 'ecommerce_admin'] : ['logistics_admin'];
            $this->notifications->send(User::whereIn('role', $roles)->where('status', 'approved')->get(), new WebOperationsNotification(['message' => 'A profile update requires review.', 'url' => route($this->reviewRoute($staff))]));

            return $revision;
        }, 3);
    }

    public function review(User $actor, StaffProfileRevision $revision, bool $approve, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $revision, $approve, $reason) {
            // Match submission lock order: subject first, then its proposal.
            $staff = User::whereKey($revision->user_id)->lockForUpdate()->firstOrFail();
            $revision = StaffProfileRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canView($actor, $staff), 403);
            if ($revision->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'This update was already reviewed.']);
            }
            if ($approve) {
                foreach ($revision->previous_values as $key => $value) {
                    if ((string) $staff->getRawOriginal($key) !== (string) $value) {
                        throw ValidationException::withMessages(['review' => 'Official details changed since submission. Reject this request and ask for a new one.']);
                    }
                }
                $values = $this->validateIdentity($staff, $revision->proposed_values);
                if ($staff->role === 'courier' && array_intersect(array_keys($values), ['vehicle_type', 'plate_number'])) {
                    $values += ['vehicle_capacity_grams' => null, 'vehicle_capacity_verified_by' => null, 'vehicle_capacity_verified_at' => null];
                }
                $staff->forceFill($values)->save();
            } elseif (! filled($reason)) {
                throw ValidationException::withMessages(['reason' => 'Give a reason for rejecting the update.']);
            }
            $revision->update(['status' => $approve ? 'approved' : 'rejected', 'pending_user_id' => null, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'reason' => $reason]);
            $this->audits->record($actor, $revision, 'staff_profile_'.$revision->status, $revision->previous_values, $revision->proposed_values, $reason ?: 'Verified staff information approved.');
            $this->notifications->send($staff, new WebOperationsNotification(['message' => 'Your profile update was '.$revision->status.'.'.($reason ? ' '.$reason : ''), 'url' => route('account.profile.edit').'#profile-requests']));
        }, 3);
    }

    private function validateIdentity(User $staff, array $values): array
    {
        foreach (['contact_no' => ['canonical_mobile', 'normalizeMobile'], 'plate_number' => ['canonical_plate', 'canonicalPlate'], 'license_number' => ['canonical_license_number', 'canonicalCode']] as $field => [$canonical, $method]) {
            if (array_key_exists($field, $values) && ! filled($values[$field])) {
                $values[$canonical] = null;
            }
            if (array_key_exists($field, $values) && filled($values[$field])) {
                $normalized = $this->identities->{$method}($values[$field]);
                validator([$canonical => $normalized], [$canonical => [Rule::unique('users', $canonical)->ignore($staff->id)]])->validate();
                $values[$canonical] = $normalized;
            }
        }

        return $values;
    }
}
