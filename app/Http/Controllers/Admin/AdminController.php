<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Services\AdministrativeAuditService;
use App\Services\OrderTransitionService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function dashboard()
    {
        // 1. Total 10% Platform Commission Collected (Completed orders)
        $platformEarnings = OrderFinance::query()->where('remittance_status', 'VERIFIED')
            ->whereHas('order', fn ($query) => $query->where('status', 'COMPLETED')->whereDoesntHave('refundExecutions'))
            ->sum('commission_cents') / 100;

        // 2. Gross Merchandise Value (GMV - All non-cancelled orders)
        $grossMerchandiseValue = Order::whereNotIn('status', ['DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER', 'CANCELLED'])
            ->sum('total_amount');

        // 3. System Metrics
        $stats = [
            'buyers' => User::where('role', 'buyer')->count(),
            'sellers' => User::where('role', 'seller')->where('status', 'approved')->count(),
            'products' => Product::where('compliance_status', 'approved')->where('is_archived', false)->count(),
            'orders' => Order::count(),
            'delivered' => Order::whereNotNull('delivered_at')->count(),
            'failed_attempts' => DeliveryAttempt::where('outcome', 'failed')->count(),
            'total_commission' => $platformEarnings,
            'gmv' => $grossMerchandiseValue,
            'pending_users' => User::where('role', 'seller')->where('status', 'pending')->count(),
            'pending_product_reviews' => Product::where('compliance_status', 'pending_review')->count(),
            'active_parcels' => Order::whereIn('status', [
                'READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY',
            ])->count(),
        ];

        // 4. Pending Registrations Queue
        $pendingUsers = User::where('role', 'seller')->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        // 5. System-wide Recent Transactions
        $recentOrders = Order::with(['seller', 'buyer'])
            ->latest()
            ->take(6)
            ->get();

        return view('admin.dashboard', compact('stats', 'pendingUsers', 'recentOrders'));
    }

    public function index()
    {
        $pendingUsers = User::where('role', 'seller')->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('admin.registrations', compact('pendingUsers'));
    }

    public function approve(User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        abort_unless($this->authenticatedUser()->canManageAccount($user), 403);
        DB::transaction(function () use ($user, $notifications): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->authenticatedUser()->canManageAccount($lockedUser), 403);
            abort_unless(
                $lockedUser->role !== 'admin' && $lockedUser->status === 'pending',
                422,
                'Only pending accounts can be approved.'
            );

            $lockedUser->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $this->authenticatedUser()->id,
                'rejection_reason' => null,
            ])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('approved'));
            $this->audits->record($this->authenticatedUser(), $lockedUser, 'account_approved', ['status' => 'pending'], ['status' => 'approved'], 'Application and supporting documents reviewed.');
        });

        return back()->with('success', "Account for {$user->first_name} {$user->last_name} ({$user->role}) has been approved.");
    }

    public function reject(Request $request, User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        abort_unless($this->authenticatedUser()->canManageAccount($user), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        DB::transaction(function () use ($user, $notifications, $validated): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->authenticatedUser()->canManageAccount($lockedUser), 403);
            abort_unless(
                $lockedUser->role !== 'admin' && $lockedUser->status === 'pending',
                422,
                'Only pending accounts can be rejected.'
            );

            $lockedUser->forceFill([
                'status' => 'rejected',
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => trim($validated['reason']),
            ])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('rejected'));
            $this->audits->record($this->authenticatedUser(), $lockedUser, 'account_rejected', ['status' => 'pending'], ['status' => 'rejected'], $validated['reason']);
        });

        return back()->with('success', "Account for {$user->first_name} {$user->last_name} ({$user->role}) has been rejected.");
    }

    public function cancelOrder(Request $request, Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $actor = $this->authenticatedUser();

        $transitions->transition(
            $order,
            $actor,
            OrderStatus::Cancelled,
            'admin_cancelled',
            $actor->municipality,
            'Admin cancelled the order: '.$validated['reason'],
        );

        return back()->with('success', "Order {$order->order_number} cancelled and reserved inventory restored.");
    }
}
