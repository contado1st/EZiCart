<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Voucher;
use App\Services\ProductCategoryService;
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

        $categories = array_map(fn ($name) => ['name' => $name, 'icon' => 'package1'], app(ProductCategoryService::class)->browseOptions(Product::availableForMarketplace()->distinct()->pluck('category')));

        $query = Product::query()->availableForMarketplace()
            ->with('seller');

        // Filter by category
        if ($request->filled('category')) {
            app(ProductCategoryService::class)->filter($query, $request->query('category'));
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
        $filters = $request->validate(['rating' => ['nullable', 'integer', 'between:1,5'], 'comments' => ['nullable', 'boolean'], 'recommendations_page' => ['nullable', 'integer', 'min:1']]);
        abort_if($product->compliance_status !== 'approved' || $product->is_archived || $product->stock <= 0, 404);

        $product->load(['seller', 'media', 'variations' => fn ($query) => $query->where('is_active', true)]);
        abort_if($product->variations()->exists() && $product->variations->isEmpty(), 404);

        if ($request->user()?->role === 'buyer') {
            $recommendations->recordView($request->user(), $product);
        }

        $ratingCounts = $product->reviews()->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');
        $reviewCount = (int) $ratingCounts->sum();
        $averageRating = $reviewCount ? round($ratingCounts->sum(fn ($count, $rating) => $count * $rating) / $reviewCount, 1) : 0;
        $reviews = $product->reviews()->with('buyer')
            ->when($filters['rating'] ?? null, fn ($query, $rating) => $query->where('rating', $rating))
            ->when($request->boolean('comments'), fn ($query) => $query->whereNotNull('comment')->where('comment', '<>', ''))
            ->latest()->paginate(5, ['*'], 'reviews_page')->withQueryString();
        $sameShop = Product::availableForMarketplace()->where('user_id', $product->user_id)->whereKeyNot($product->id)
            ->withAvg('reviews as display_rating', 'rating')->withCount('reviews')->latest()->limit(6)->get();
        $suggestions = Product::availableForMarketplace()->where('user_id', '<>', $product->user_id)
            ->whereHas('seller', fn ($query) => $query->where('role', 'seller')->where('status', 'approved'))
            ->with('seller')->withAvg('reviews as display_rating', 'rating')->withCount('reviews')
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$product->category])->latest()->orderByDesc('id')
            ->paginate(24, ['*'], 'recommendations_page')->withQueryString()->fragment('you-may-also-like');
        $shopProductCount = $product->seller->products()->availableForMarketplace()->count();
        $followerCount = $product->seller->shopFollowers()->count();
        $vouchers = Voucher::query()->where('seller_id', $product->user_id)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->where(fn ($query) => $query->whereNull('recipient_limit')->orWhereColumn('claimed_count', '<', 'recipient_limit'))
            ->latest()->limit(3)->get();

        return view('products.show', compact('product', 'reviews', 'ratingCounts', 'reviewCount', 'averageRating', 'sameShop', 'suggestions', 'shopProductCount', 'followerCount', 'vouchers'));
    }
}
