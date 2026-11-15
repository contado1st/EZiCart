<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductWishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistApiController extends Controller
{
    /**
     * Get paginated wishlisted products for the authenticated buyer
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 50);

        $products = $request->user()->wishlistedProducts()
            ->with([
                'seller:id,business_name,first_name,last_name,municipality,province,role,status',
                'media',
                'variations' => fn ($q) => $q->where('is_active', true),
            ])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews')
            ->withSum('orderItems as sales_count', 'quantity')
            ->orderByDesc('product_wishlists.created_at')
            ->orderByDesc('products.id')
            ->paginate($perPage);

        $items = collect($products->items())->map(function ($product) {
            $isAvailable = $product->isListingVisible()
                && $product->stock > 0
                && (! $product->usesVariations() || $product->variations->where('is_active', true)->where('stock', '>', 0)->isNotEmpty())
                && $product->seller?->role === 'seller'
                && $product->seller?->status === 'approved';

            $product->setAttribute('is_wishlisted', true);
            $product->setAttribute('available', $isAvailable);

            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }

    /**
     * Toggle wishlist state for a product
     */
    public function toggle(Request $request, ProductWishlistService $wishlist): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $buyer = $request->user();
        $isWishlisted = $buyer->wishlistedProducts()->where('products.id', $product->id)->exists();

        $wishlist->set($buyer, $product, ! $isWishlisted);

        $nowSaved = ! $isWishlisted;

        return response()->json([
            'success' => true,
            'is_wishlisted' => $nowSaved,
            'message' => $nowSaved ? 'Product added to your wishlist.' : 'Product removed from your wishlist.',
        ]);
    }

    /**
     * Add product to wishlist
     */
    public function store(Request $request, int $productId, ProductWishlistService $wishlist): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $buyer = $request->user();

        $wishlist->set($buyer, $product, true);

        return response()->json([
            'success' => true,
            'is_wishlisted' => true,
            'message' => 'Product added to your wishlist.',
        ]);
    }

    /**
     * Remove product from wishlist
     */
    public function destroy(Request $request, int $productId, ProductWishlistService $wishlist): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $buyer = $request->user();

        $wishlist->set($buyer, $product, false);

        return response()->json([
            'success' => true,
            'is_wishlisted' => false,
            'message' => 'Product removed from your wishlist.',
        ]);
    }
}
