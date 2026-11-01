<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $roleDashboardRoute = match ($request->user()?->role) {
            'admin', 'ecommerce_admin' => 'admin.dashboard',
            'logistics_admin' => 'logistics-admin.dashboard',
            'courier' => 'courier.dashboard',
            'sorting_center' => 'logistics.dashboard',
            default => null,
        };

        if ($roleDashboardRoute !== null) {
            return redirect()->route($roleDashboardRoute);
        }

        $categories = [
            ['name' => 'Beauty', 'icon' => "\u{1F484}"],
            ['name' => 'Home & Living', 'icon' => "\u{1F3E0}"],
            ['name' => 'Fashion', 'icon' => "\u{1F457}"],
            ['name' => 'Electronics', 'icon' => "\u{1F3A7}"],
            ['name' => 'Groceries', 'icon' => "\u{1F6D2}"],
        ];

        $query = Product::query()->availableForMarketplace()
            ->with('seller');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        // Search by keyword
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->query('search').'%')
                    ->orWhere('description', 'like', '%'.$request->query('search').'%');
            });
        }

        $products = $query->latest()->paginate(12)->withQueryString();

        $view = $request->user() ? 'home' : 'home-guest';

        return view($view, compact('categories', 'products'));
    }

    public function showProduct(Request $request, Product $product, ProductRecommendationService $recommendations)
    {
        abort_if($product->compliance_status !== 'approved' || $product->is_archived || $product->stock <= 0, 404);

        $product->load(['seller', 'media', 'variations' => fn ($query) => $query->where('is_active', true)]);
        abort_if($product->variations()->exists() && $product->variations->isEmpty(), 404);

        if ($request->user()?->role === 'buyer') {
            $recommendations->recordView($request->user(), $product);
        }

        return view('products.show', compact('product'));
    }
}
