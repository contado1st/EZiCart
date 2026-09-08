<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        // 1. Total 10% Platform Commission Collected (Completed orders)
        $platformEarnings = Order::whereIn('status', ['DELIVERED', 'COMPLETED'])
            ->sum('commission_fee');

        // 2. Gross Merchandise Value (GMV - All non-cancelled orders)
        $grossMerchandiseValue = Order::whereNotIn('status', ['DELIVERY_FAILED', 'RETURNED'])
            ->sum('total_amount');

        // 3. System Metrics
        $stats = [
            'total_commission' => $platformEarnings,
            'gmv'              => $grossMerchandiseValue,
            'pending_users'    => User::where('status', 'pending')->count(),
            'active_parcels'   => Order::whereIn('status', [
                'READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'
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

    public function approve(User $user)
    {
        $user->update(['status' => 'approved']);
        return back()->with('success', "Account for {$user->first_name} {$user->last_name} ({$user->role}) has been approved.");
    }

    public function reject(User $user)
    {
        $user->update(['status' => 'rejected']);
        return back()->with('success', "Account for {$user->first_name} {$user->last_name} ({$user->role}) has been rejected.");
    }
}