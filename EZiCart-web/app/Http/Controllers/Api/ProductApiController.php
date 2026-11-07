<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    /**
     * Get paginated products for the mobile app
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $category = $request->input('category');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'latest');
        $perPage = min((int) $request->input('per_page', 12), 50);

        $query = Product::query()
            ->availableForMarketplace()
            ->with(['seller:id,business_name,first_name,last_name,municipality,province', 'variations' => fn ($q) => $q->where('is_active', true)])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews');

        if ($search !== '') {
            $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(function (Builder $q) use ($pattern) {
                $q->whereRaw('LOWER(name) LIKE ? ESCAPE \'!\'', [$pattern])
                    ->orWhereRaw('LOWER(description) LIKE ? ESCAPE \'!\'', [$pattern])
                    ->orWhereRaw('LOWER(category) LIKE ? ESCAPE \'!\'', [$pattern])
                    ->orWhereHas('seller', fn (Builder $s) => $s->whereRaw('LOWER(business_name) LIKE ? ESCAPE \'!\'', [$pattern]));
            });
        }

        if (filled($category) && $category !== 'All') {
            $query->where('category', $category);
        }

        if (is_numeric($minPrice)) {
            $query->where('price', '>=', (float) $minPrice);
        }

        if (is_numeric($maxPrice)) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->latest('id'),
        };

        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }

    /**
     * Get single product details
     */
    public function show(Request $request, int $id, ProductRecommendationService $recommendations): JsonResponse
    {
        $product = Product::with([
            'seller:id,business_name,first_name,last_name,municipality,province,created_at',
            'media',
            'variations' => fn ($q) => $q->where('is_active', true),
            'reviews.user:id,first_name,last_name',
        ])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews')
            ->find($id);

        if (! $product || $product->compliance_status !== 'approved' || $product->is_archived || $product->stock <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available.',
            ], 404);
        }

        // Track recommendation view if buyer is authenticated
        if ($request->user() && $request->user()->role === 'buyer') {
            try {
                $recommendations->recordView($request->user(), $product);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Get category list with iconography and counts
     */
    public function categories(): JsonResponse
    {
        $defaultCategories = [
            ['name' => 'Beauty', 'icon' => 'face'],
            ['name' => 'Home & Living', 'icon' => 'home'],
            ['name' => 'Fashion', 'icon' => 'checkroom'],
            ['name' => 'Electronics', 'icon' => 'devices'],
            ['name' => 'Groceries', 'icon' => 'shopping_basket'],
        ];

        $counts = Product::query()
            ->availableForMarketplace()
            ->whereNotNull('category')
            ->groupBy('category')
            ->selectRaw('category, count(*) as count')
            ->pluck('count', 'category')
            ->toArray();

        $categories = array_map(function ($item) use ($counts) {
            return [
                'name' => $item['name'],
                'icon' => $item['icon'],
                'product_count' => $counts[$item['name']] ?? 0,
            ];
        }, $defaultCategories);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}

