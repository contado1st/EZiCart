<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ShopFollowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SellerShopController extends Controller
{
    public function show(Request $request, User $seller)
    {
        $this->assertPublicSeller($seller);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:latest,price_asc,price_desc,rating'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters['sort'] ??= 'latest';
        $base = Product::query()->where('user_id', $seller->id)->availableForMarketplace();
        $productCount = (clone $base)->count();
        $categories = (clone $base)->distinct()->orderBy('category')->pluck('category')->filter();
        $reviews = Review::query()->whereHas('product', fn (Builder $q) => $q->where('user_id', $seller->id)->where('compliance_status', 'approved')->where('is_archived', false));
        $rating = (clone $reviews)->avg('rating');
        $reviewCount = (clone $reviews)->count();
        $query = $base->withAvg('reviews as shop_rating', 'rating')->withCount('reviews as shop_review_count');
        if (filled($filters['q'] ?? null)) {
            $pattern = '%'.strtr(mb_strtolower(trim($filters['q'])), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]));
        }
        if (filled($filters['category'] ?? null)) {
            $query->where('category', $filters['category']);
        }
        match ($filters['sort']) {
            'price_asc' => $query->orderBy('price'), 'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('shop_rating')->orderByDesc('shop_review_count'),
            default => $query->orderByDesc('created_at'),
        };
        $products = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $followerCount = $seller->shopFollowers()->count();
        $following = $request->user()?->role === 'buyer' && $request->user()->followedShops()->whereKey($seller->id)->exists();

        return response()->view('shops.show', compact('seller', 'products', 'categories', 'filters', 'productCount', 'rating', 'reviewCount', 'followerCount', 'following'))->header('Cache-Control', 'private, no-store');
    }

    public function follow(Request $request, User $seller, ShopFollowService $follows)
    {
        $this->assertPublicSeller($seller);
        abort_unless($request->user()->role === 'buyer', 403);
        $data = $request->validate(['following' => ['required', 'boolean']]);
        $follows->set($request->user(), $seller, (bool) $data['following']);

        return back()->with('success', $data['following'] ? 'You are now following this shop.' : 'You have unfollowed this shop.');
    }

    private function assertPublicSeller(User $seller): void
    {
        abort_unless($seller->role === 'seller' && $seller->status === 'approved', 404);
    }
}
