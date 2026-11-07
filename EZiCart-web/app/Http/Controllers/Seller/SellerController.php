<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\CheckoutPricingService;

class SellerController extends Controller
{
    public function dashboard()
    {
        $seller = $this->authenticatedUser();

        // Accrued payable differs from settled cash and report recognition timing.
        $netSales = $seller->sellerOrders()
            ->whereIn('status', ['DELIVERED', 'COMPLETED'])
            ->with('finance')
            ->withSum(['refundExecutions as refunded_cents' => fn ($query) => $query->where('status', 'EXECUTED')], 'amount_cents')
            ->get()->sum(function ($order): int {
                $payable = $order->finance?->seller_payable_cents
                    ?? CheckoutPricingService::cents($order->subtotal - $order->discount_amount + $order->platform_discount_amount - $order->commission_fee);

                return max(0, $payable - (int) $order->refunded_cents);
            }) / 100;
        $reviews = Review::whereHas('product', fn ($query) => $query->where('user_id', $seller->id));
        $reviewCount = (clone $reviews)->count();

        $stats = [
            'total_sales' => $netSales,
            'pending_orders' => $seller->sellerOrders()->where('status', 'PLACED')->count(),
            'low_stock' => $seller->products()->where('stock', '<', 10)->where('is_archived', false)->count(),
            'rating' => $reviewCount ? number_format((float) $reviews->avg('rating'), 1) : 'Unrated',
            'review_count' => $reviewCount,
        ];

        $recentOrders = $seller->sellerOrders()
            ->with(['buyer', 'items'])
            ->latest()
            ->take(5)
            ->get();

        return view('seller.dashboard', compact('stats', 'recentOrders'));
    }
}
