<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\StoreCategory;
use App\Models\User;
use App\Services\ProductCategoryService;
use App\Services\ProductDiscoveryService;
use App\Services\ProductPriceService;
use App\Services\ShopFollowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SellerShopController extends Controller
{
    public function show(Request $request, User $seller, ProductDiscoveryService $discovery)
    {
        $this->assertPublicSeller($seller);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100'],
            'store_category' => ['nullable', 'string', 'max:30'],
            'sort' => ['nullable', 'in:latest,price_asc,price_desc,rating,best_selling'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters['sort'] ??= 'latest';
        $base = Product::query()->where('user_id', $seller->id)->availableForMarketplace();
        $productCount = (clone $base)->count();
        $categories = (clone $base)->distinct()->orderBy('category')->pluck('category')->filter();
        $categories = collect(app(ProductCategoryService::class)->browseOptions($categories));
        $reviews = Review::query()->whereHas('product', fn (Builder $q) => $q->where('user_id', $seller->id)->where('compliance_status', 'approved')->where('is_archived', false));
        $rating = (clone $reviews)->avg('rating');
        $reviewCount = (clone $reviews)->count();
        $query = $base->withVariantPrices()->withAvg('reviews as shop_rating', 'rating')->withCount('reviews as shop_review_count');
        $storeCategories = StoreCategory::where('seller_id', $seller->id)->where('is_visible', true)
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhereHas('parent', fn ($p) => $p->where('is_visible', true)))
            ->with('parent')->orderBy('sort_order')->orderBy('id')->get();
        if (filled($filters['store_category'] ?? null)) {
            if ($filters['store_category'] === 'uncategorized') {
                $query->whereDoesntHave('storeCategories');
            } else {
                abort_unless(ctype_digit($filters['store_category']) && $storeCategories->contains('id', (int) $filters['store_category']), 404);
                $query->whereHas('storeCategories', fn ($q) => $q->whereKey((int) $filters['store_category']));
            }
        }
        if (filled($filters['q'] ?? null)) {
            $pattern = '%'.strtr(mb_strtolower(trim($filters['q'])), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]));
        }
        if (filled($filters['category'] ?? null)) {
            app(ProductCategoryService::class)->filter($query, $filters['category']);
        }
        match ($filters['sort']) {
            'best_selling' => $discovery->bestSelling($query),
            'price_asc' => $query->orderByRaw(ProductPriceService::minimumSql().' ASC'), 'price_desc' => $query->orderByRaw(ProductPriceService::minimumSql().' DESC'),
            'rating' => $query->orderByDesc('shop_rating')->orderByDesc('shop_review_count'),
            default => $query->orderByDesc('created_at'),
        };
        $products = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $followerCount = $seller->shopFollowers()->count();
        $following = $request->user()?->role === 'buyer' && $request->user()->followedShops()->whereKey($seller->id)->exists();

        return response()->view('shops.show', compact('seller', 'products', 'categories', 'storeCategories', 'filters', 'productCount', 'rating', 'reviewCount', 'followerCount', 'following'))->header('Cache-Control', 'private, no-store');
    }

    public function follow(Request $request, User $seller, ShopFollowService $follows)
    {
        $this->assertPublicSeller($seller);
        abort_unless($request->user()->role === 'buyer', 403);
        $data = $request->validate(['following' => ['required', 'boolean']]);
        $follows->set($request->user(), $seller, (bool) $data['following']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'following' => (bool) $data['following'],
                'follower_count' => $seller->shopFollowers()->count(),
                'message' => $data['following'] ? 'You are now following this shop.' : 'You have unfollowed this shop.',
            ]);
        }

        return back()->with('success', $data['following'] ? 'You are now following this shop.' : 'You have unfollowed this shop.');
    }

    public function followIntent(User $seller)
    {
        $this->assertPublicSeller($seller);

        return redirect()->route('shops.show', $seller)->with('success', 'You can now follow this shop. Select Follow shop to confirm.');
    }

    private function assertPublicSeller(User $seller): void
    {
        abort_unless($seller->role === 'seller' && $seller->status === 'approved', 404);
    }
}
