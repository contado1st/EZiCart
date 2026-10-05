<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Services\LogisticsExceptionService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminModerationController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role');
        $search = $request->query('search');

        $users = User::whereNotIn('role', ['admin'])
            ->withCount([
                'pickupDeliveries as active_pickups_count' => fn ($query) => $query->where('status', 'READY_FOR_PICKUP')
                    ->whereNotNull('pickup_claimed_at')->whereNull('seller_handover_at'),
                'finalDeliveries as active_parcels_count' => fn ($query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT']),
            ])
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return view('admin.moderation.index', compact('users', 'role', 'search'));
    }

    public function suspend(Request $request, User $user, TransactionAwareNotificationSender $notifications, LogisticsExceptionService $exceptions): RedirectResponse
    {
        abort_unless($user->role !== 'admin' && $user->status === 'approved', 422, 'Only approved accounts can be suspended here.');

        $validated = $request->validate([
            'suspension_reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($user, $validated, $notifications, $exceptions, $request): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role !== 'admin' && $lockedUser->status === 'approved', 422, 'Only approved accounts can be suspended here.');

            $lockedUser->forceFill([
                'status' => 'suspended',
                'suspension_previous_status' => $lockedUser->status,
                'suspension_source' => 'admin',
                'suspension_reason' => $validated['suspension_reason'],
                'suspended_at' => now(),
            ])->save();
            if ($lockedUser->role === 'courier') {
                $exceptions->openForRiderSuspension($lockedUser, $request->user(), $validated['suspension_reason']);
            }
            $notifications->send($lockedUser, new AccountStatusNotification('suspended', $validated['suspension_reason']));
        });

        return back()->with('success', "Account {$user->email} has been suspended.");
    }

    public function reactivate(Request $request, User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'restored_status' => ['nullable', 'in:approved,pending,rejected'],
        ]);

        $restoredStatus = DB::transaction(function () use ($user, $validated, $notifications): string {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role !== 'admin' && $lockedUser->status === 'suspended', 422, 'Only suspended accounts can be restored here.');

            $restoredStatus = $lockedUser->suspension_previous_status;
            if ($restoredStatus === null) {
                abort_unless(isset($validated['restored_status']), 422, 'This older suspension has no recorded prior status. Review the account and choose its correct status.');
                $restoredStatus = $validated['restored_status'];
            }

            abort_unless(in_array($restoredStatus, ['approved', 'pending', 'rejected'], true), 422, 'The recorded prior account status is invalid.');
            $lockedUser->forceFill([
                'status' => $restoredStatus,
                'suspension_previous_status' => null,
                'suspension_source' => null,
                'suspension_reason' => null,
                'suspended_at' => null,
            ])->save();
            $notifications->send($lockedUser, new AccountStatusNotification($restoredStatus));

            return $restoredStatus;
        });

        $message = match ($restoredStatus) {
            'approved' => "Account {$user->email} has been restored to active standing.",
            'pending' => "Account {$user->email} has been returned to the registration review queue.",
            default => "Account {$user->email} has been restored as rejected.",
        };

        return back()->with('success', $message);
    }
}
