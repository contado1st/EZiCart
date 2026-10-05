<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Services\OrderTransitionService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        // 1. Total 10% Platform Commission Collected (Completed orders)
        $platformEarnings = Order::whereIn('status', ['DELIVERED', 'COMPLETED'])
            ->sum('commission_fee');

        // 2. Gross Merchandise Value (GMV - All non-cancelled orders)
        $grossMerchandiseValue = Order::whereNotIn('status', ['DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER', 'CANCELLED'])
            ->sum('total_amount');

        // 3. System Metrics
        $stats = [
            'total_commission' => $platformEarnings,
            'gmv' => $grossMerchandiseValue,
            'pending_users' => User::where('status', 'pending')->count(),
            'pending_product_reviews' => Product::where('compliance_status', 'pending_review')->count(),
            'active_parcels' => Order::whereIn('status', [
                'READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY',
            ])->count(),
        ];

        // 4. Pending Registrations Queue (Sellers & Couriers requiring verification)
        $pendingUsers = User::where('status', 'pending')
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
        $pendingUsers = User::where('status', 'pending')->latest()->paginate(15);

        return view('admin.registrations', compact('pendingUsers'));
    }

    public function approve(User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        DB::transaction(function () use ($user, $notifications): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role !== 'admin' && $lockedUser->status === 'pending', 422, 'Only pending accounts can be approved.');

            $lockedUser->forceFill(['status' => 'approved'])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('approved'));
        });

        return back()->with('success', "Account for {$user->first_name} {$user->last_name} ({$user->role}) has been approved.");
    }

    public function reject(User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        DB::transaction(function () use ($user, $notifications): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role !== 'admin' && $lockedUser->status === 'pending', 422, 'Only pending accounts can be rejected.');

            $lockedUser->forceFill(['status' => 'rejected'])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('rejected'));
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
