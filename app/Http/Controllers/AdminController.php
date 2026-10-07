<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
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
            'gmv' => $grossMerchandiseValue,
            'pending_users' => User::where('status', 'pending')->count(),
            'pending_products' => Product::where('status', 'pending')->count(),
            'active_parcels' => Order::whereIn('status', [
                'READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY',
            ])->count(),
        ];

        // 4. Pending Registrations Queue (Sellers & Couriers requiring verification)
        $pendingUsers = User::where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        // 5. Pending Products Queue
        $pendingProducts = Product::with('seller')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        // 6. System-wide Recent Transactions
        $recentOrders = Order::with(['seller', 'buyer'])
            ->latest()
            ->take(6)
            ->get();

        return view('admin.dashboard', compact('stats', 'pendingUsers', 'pendingProducts', 'recentOrders'));
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

    /**
     * List products requiring approval or filtered by status.
     */
    public function products(Request $request)
    {
        $status = $request->query('status', 'pending');

        $query = Product::with(['seller', 'images', 'variations']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $products = $query->latest()->paginate(15)->withQueryString();

        $pendingCount = Product::where('status', 'pending')->count();
        $approvedCount = Product::where('status', 'approved')->count();
        $rejectedCount = Product::where('status', 'rejected')->count();

        return view('admin.products.index', compact('products', 'status', 'pendingCount', 'approvedCount', 'rejectedCount'));
    }

    /**
     * Show full product details for admin inspection.
     */
    public function showProduct(Product $product)
    {
        $product->load(['seller', 'images', 'variations', 'vouchers']);

        return view('admin.products.show', compact('product'));
    }

    /**
     * Approve a product for listing in marketplace.
     */
    public function approveProduct(Product $product)
    {
        $product->update([
            'status' => 'approved',
            'rejection_reason' => null,
        ]);

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$product->name}' has been approved and is now live on the marketplace.");
    }

    /**
     * Reject a product with feedback reason.
     */
    public function rejectProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $product->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$product->name}' has been rejected with feedback reason.");
    }
}
