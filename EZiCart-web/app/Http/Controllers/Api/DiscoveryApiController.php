<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use App\Services\StockSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscoveryApiController extends Controller
{
    /**
     * Get personalized product recommendations ("For You")
     */
    public function forYou(Request $request, ProductRecommendationService $recommendations): JsonResponse
    {
        $buyer = $request->user();
        $perPage = min((int) $request->input('per_page', 16), 50);

        $products = $recommendations->query($buyer)
            ->with(['seller:id,business_name,municipality,province', 'variations' => fn ($q) => $q->where('is_active', true)])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }

    /**
     * Clear browsing history
     */
    public function resetHistory(Request $request): JsonResponse
    {
        $buyer = $request->user();
        DB::table('product_interactions')->where('user_id', $buyer->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Browsing history cleared. Recommendations will refresh based on your purchases.',
        ]);
    }

    /**
     * Get stock alert watchlist
     */
    public function stockAlerts(Request $request, StockSubscriptionService $subscriptions): JsonResponse
    {
        $buyer = $request->user();

        $watched = DB::table('stock_subscriptions')
            ->where('user_id', $buyer->id)
            ->pluck('product_id');

        $products = Product::with(['seller:id,business_name,municipality,province', 'variations'])
            ->whereIn('id', $watched)
            ->latest('id')
            ->get();

        $data = $products->map(function (Product $product) use ($subscriptions) {
            $state = $subscriptions->state($product);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image_path' => $product->image_path,
                'stock' => (int) $product->stock,
                'category' => $product->category,
                'seller_name' => $product->seller->business_name ?? 'Seller',
                'availability_state' => $state,
                'is_watched' => true,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Watch product for stock alerts
     */
    public function watchStock(int $id, Request $request, StockSubscriptionService $subscriptions): JsonResponse
    {
        $buyer = $request->user();
        $product = Product::with(['seller', 'variations'])->find($id);

        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        try {
            $subscriptions->watch($buyer, $product);

            return response()->json([
                'success' => true,
                'message' => "Stock alerts enabled for {$product->name}. We will notify you when availability changes.",
                'is_watched' => true,
                'availability_state' => $subscriptions->state($product),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unwatch product for stock alerts
     */
    public function unwatchStock(int $id, Request $request): JsonResponse
    {
        $buyer = $request->user();

        DB::table('stock_subscriptions')
            ->where('user_id', $buyer->id)
            ->where('product_id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock alert removed.',
            'is_watched' => false,
        ]);
    }
}

