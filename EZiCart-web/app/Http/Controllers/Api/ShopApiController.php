<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ProductCategoryService;
use App\Services\ShopFollowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopApiController extends Controller
{
    /**
     * Get seller shop details and products
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $seller = User::where('id', $id)
            ->where('role', 'seller')
            ->where('status', 'approved')
            ->first();

        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $search = trim((string) $request->input('q', ''));
        $category = $request->input('category');
        $sort = $request->input('sort', 'latest');
        $perPage = min((int) $request->input('per_page', 12), 40);

        $base = Product::query()
            ->where('user_id', $seller->id)
            ->availableForMarketplace();

        $productCount = (clone $base)->count();
        $categories = (clone $base)->distinct()->orderBy('category')->pluck('category')->filter()->values();

        $reviews = Review::query()->whereHas('product', fn (Builder $q) => $q->where('user_id', $seller->id)->where('compliance_status', 'approved')->where('is_archived', false));
        $reviewCount = (clone $reviews)->count();
        $rating = $reviewCount > 0 ? (float) (clone $reviews)->avg('rating') : 0.0;

        $query = (clone $base)
            ->with([
                'seller:id,business_name,first_name,last_name,municipality,province',
                'media',
                'variations' => fn ($q) => $q->where('is_active', true),
            ])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews');

        if ($search !== '') {
            $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]));
        }

        if (filled($category) && $category !== 'All') {
            app(ProductCategoryService::class)->filter($query, $category);
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('rating')->orderByDesc('reviews_count'),
            default => $query->orderByDesc('created_at'),
        };

        $products = $query->paginate($perPage);

        $followerCount = $seller->shopFollowers()->count();
        $user = $request->user();
        if (! $user && ($token = $request->bearerToken())) {
            $apiToken = \App\Models\ApiToken::findByPlainTextToken($token);
            if ($apiToken && $apiToken->isValid() && $apiToken->user?->status === 'approved') {
                $user = $apiToken->user;
            }
        }

        $isFollowing = false;
        if ($user && $user->role === 'buyer') {
            $isFollowing = $user->followedShops()->whereKey($seller->id)->exists();
        }

        $onlineMinutes = (int) config('web-commerce.online_minutes', 15);
        $recent = $seller->last_web_activity_at && $seller->last_web_activity_at->gte(now()->subMinutes($onlineMinutes));
        $lastActiveText = $recent
            ? 'Active recently'
            : ($seller->last_web_activity_at ? 'Last active '.$seller->last_web_activity_at->diffForHumans() : 'Activity not recorded yet');

        $shopAvailability = method_exists($seller, 'effectiveShopAvailability')
            ? $seller->effectiveShopAvailability()
            : ($seller->shop_availability ?? 'open');

        $availabilityNote = match ($shopAvailability) {
            'vacation' => 'Seller on vacation. New orders are paused.',
            'closed' => 'Shop temporarily closed. New orders are paused.',
            default => 'Shop open',
        };

        $inactiveWarningDays = (int) config('web-commerce.inactive_warning_days', 30);
        $inactivityWarning = ($seller->last_web_activity_at && $seller->last_web_activity_at->lte(now()->subDays($inactiveWarningDays)))
            ? 'This seller has been inactive for '.(int) $seller->last_web_activity_at->diffInDays(now()).' days. Consider contacting the shop before purchasing.'
            : null;

        $displayName = $seller->profile_display_name ?: $seller->business_name ?: trim("{$seller->first_name} {$seller->last_name}");
        $avatarUrl = $seller->profile_photo_path ? url("/profiles/{$seller->id}/media/avatar?v=".($seller->updated_at?->timestamp ?? 0)) : null;
        $bannerUrl = $seller->profile_banner_path ? url("/profiles/{$seller->id}/media/banner?v=".($seller->updated_at?->timestamp ?? 0)) : null;

        return response()->json([
            'success' => true,
            'shop' => [
                'id' => $seller->id,
                'name' => $displayName,
                'display_name' => $displayName,
                'business_name' => $seller->business_name,
                'owner_name' => trim("{$seller->first_name} {$seller->last_name}"),
                'description' => $seller->line_of_business ?? 'Verified Merchant on EZiCart',
                'about' => $seller->profile_bio ?: 'This seller has not added a shop introduction yet.',
                'bio' => $seller->profile_bio ?: '',
                'line_of_business' => $seller->line_of_business,
                'location' => trim("{$seller->municipality}, {$seller->province}"),
                'rating' => round((float) $rating, 1),
                'review_count' => $reviewCount,
                'product_count' => $productCount,
                'follower_count' => $followerCount,
                'is_following' => $isFollowing,
                'member_since' => $seller->created_at?->format('M Y'),
                'profile_theme' => $seller->profile_theme ?: 'rose',
                'avatar_url' => $avatarUrl,
                'banner_url' => $bannerUrl,
                'has_avatar' => (bool) $seller->profile_photo_path,
                'has_banner' => (bool) $seller->profile_banner_path,
                'last_active' => $lastActiveText,
                'is_active_recently' => (bool) $recent,
                'shop_availability' => $shopAvailability,
                'availability_note' => $availabilityNote,
                'inactivity_warning' => $inactivityWarning,
                'rating_note' => 'Rating includes reviews of approved, non-archived products, including items currently out of stock.',
                'categories' => $categories,
            ],
            'products' => [
                'data' => $products->items(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Follow or unfollow a shop
     */
    public function follow(Request $request, int $id, ShopFollowService $follows): JsonResponse
    {
        $seller = User::where('id', $id)
            ->where('role', 'seller')
            ->where('status', 'approved')
            ->first();

        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $validated = $request->validate([
            'following' => ['required', 'boolean'],
        ]);

        $buyer = $request->user();
        $follows->set($buyer, $seller, (bool) $validated['following']);

        $followerCount = $seller->shopFollowers()->count();

        return response()->json([
            'success' => true,
            'following' => (bool) $validated['following'],
            'follower_count' => $followerCount,
            'message' => $validated['following']
                ? 'You are now following '.$seller->business_name
                : 'You have unfollowed this store.',
        ]);
    }

    /**
     * Get list of shops followed by the buyer
     */
    public function followedShops(Request $request): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();

        $shops = $buyer->followedShops()
            ->where('role', 'seller')
            ->where('status', 'approved')
            ->orderByPivot('created_at', 'desc')
            ->withCount(['products' => fn ($q) => $q->availableForMarketplace()])
            ->get()
            ->map(function ($seller) {
                return [
                    'id' => $seller->id,
                    'name' => $seller->profile_display_name ?: $seller->business_name ?: trim("{$seller->first_name} {$seller->last_name}"),
                    'display_name' => $seller->profile_display_name ?: $seller->business_name ?: trim("{$seller->first_name} {$seller->last_name}"),
                    'business_name' => $seller->business_name,
                    'location' => trim(implode(', ', array_filter([$seller->municipality, $seller->province]))) ?: 'Philippines',
                    'product_count' => $seller->products_count,
                    'member_since' => $seller->created_at?->format('M Y'),
                    'profile_theme' => $seller->profile_theme ?: 'rose',
                    'avatar_url' => $seller->profile_photo_path ? url("/profiles/{$seller->id}/media/avatar?v=".($seller->updated_at?->timestamp ?? 0)) : null,
                    'has_avatar' => (bool) $seller->profile_photo_path,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $shops,
            'shops' => $shops,
        ]);
    }
}

