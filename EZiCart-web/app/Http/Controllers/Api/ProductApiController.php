<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
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
        $ratingFilter = $request->input('rating');
        $province = $request->input('province');
        $sort = $request->input('sort', 'relevance');
        $perPage = min((int) $request->input('per_page', 12), 50);

        $query = Product::query()
            ->availableForMarketplace()
            ->with([
                'seller:id,business_name,first_name,last_name,municipality,province',
                'media',
                'variations' => fn ($q) => $q->where('is_active', true),
            ])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews')
            ->withSum('orderItems as sales_count', 'quantity');

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
            $aliases = config('web-commerce.category_aliases', []);
            $canonical = $aliases[$category] ?? $category;
            $matchingCategories = [$canonical];
            foreach ($aliases as $alias => $target) {
                if ($target === $canonical) {
                    $matchingCategories[] = $alias;
                }
            }
            $query->whereIn('category', array_unique($matchingCategories));
        }

        if (is_numeric($minPrice)) {
            $query->where('price', '>=', (float) $minPrice);
        }

        if (is_numeric($maxPrice)) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        if (is_numeric($ratingFilter) && (int) $ratingFilter >= 1) {
            $query->where(Review::query()->selectRaw('COALESCE(AVG(rating), 0)')->whereColumn('reviews.product_id', 'products.id'), '>=', (int) $ratingFilter);
        }

        if (filled($province) && $province !== 'All' && $province !== 'All locations') {
            $query->whereHas('seller', fn (Builder $s) => $s->where('province', $province));
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'top_sales', 'sales' => $query->orderByDesc('sales_count')->latest('id'),
            'rating' => $query->orderByDesc('rating')->orderByDesc('reviews_count'),
            'latest' => $query->latest('id'),
            default => $search !== ''
                ? $query->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [mb_strtolower($search)])
                    ->latest('id')
                : $query->latest('id'),
        };

        $products = $query->paginate($perPage);

        // If search term is present, find related shops selling matching items or matching business name
        $relatedShops = [];
        if ($search !== '') {
            $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $relatedShops = User::query()
                ->where('role', 'seller')
                ->where('status', 'approved')
                ->where(function (Builder $q) use ($pattern) {
                    $q->whereRaw('LOWER(business_name) LIKE ? ESCAPE \'!\'', [$pattern])
                        ->orWhereHas('products', function (Builder $pq) use ($pattern) {
                            $pq->availableForMarketplace()
                                ->whereRaw('LOWER(name) LIKE ? ESCAPE \'!\'', [$pattern]);
                        });
                })
                ->withCount(['products' => fn ($q) => $q->availableForMarketplace()])
                ->limit(3)
                ->get()
                ->map(function ($seller) {
                    $reviews = Review::query()->whereHas('product', fn ($q) => $q->where('user_id', $seller->id)->availableForMarketplace());
                    $reviewCount = (clone $reviews)->count();
                    $avgRating = $reviewCount > 0 ? (float) (clone $reviews)->avg('rating') : 0.0;
                    $sampleProducts = Product::query()
                        ->where('user_id', $seller->id)
                        ->availableForMarketplace()
                        ->limit(3)
                        ->get(['id', 'name', 'price', 'image_path']);

                    return [
                        'id' => $seller->id,
                        'name' => $seller->business_name ?: trim("{$seller->first_name} {$seller->last_name}"),
                        'business_name' => $seller->business_name,
                        'location' => trim("{$seller->municipality}, {$seller->province}"),
                        'rating' => round((float) $avgRating, 1),
                        'review_count' => $reviewCount,
                        'product_count' => $seller->products_count,
                        'sample_products' => $sampleProducts,
                    ];
                });
        }

        $provinces = User::query()
            ->where('role', 'seller')
            ->where('status', 'approved')
            ->whereHas('products', fn (Builder $p) => $p->availableForMarketplace())
            ->whereNotNull('province')
            ->where('province', '!=', '')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'related_shops' => $relatedShops,
            'provinces' => $provinces,
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }

    /**
     * Get live search suggestions for products and shops
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if ($query === '' || mb_strlen($query) < 1) {
            return response()->json([
                'success' => true,
                'products' => [],
                'shops' => [],
            ]);
        }

        $pattern = '%'.strtr(mb_strtolower($query), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        $products = Product::query()
            ->availableForMarketplace()
            ->where(function (Builder $q) use ($pattern) {
                $q->whereRaw('LOWER(name) LIKE ? ESCAPE \'!\'', [$pattern])
                    ->orWhereRaw('LOWER(category) LIKE ? ESCAPE \'!\'', [$pattern]);
            })
            ->orderByRaw('CASE WHEN LOWER(name) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($query).'%'])
            ->limit(8)
            ->get(['id', 'name', 'price', 'category', 'image_path', 'user_id']);

        $shops = User::query()
            ->where('role', 'seller')
            ->where('status', 'approved')
            ->whereRaw('LOWER(business_name) LIKE ? ESCAPE \'!\'', [$pattern])
            ->withCount(['products' => fn ($q) => $q->availableForMarketplace()])
            ->limit(3)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->business_name ?: trim("{$s->first_name} {$s->last_name}"),
                'location' => trim("{$s->municipality}, {$s->province}"),
                'product_count' => $s->products_count,
            ]);

        return response()->json([
            'success' => true,
            'products' => $products,
            'shops' => $shops,
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
            ->withSum('orderItems as sales_count', 'quantity')
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
     * Get category list with iconography and counts from web config
     */
    public function categories(): JsonResponse
    {
        $canonicalCategories = [
            ['name' => 'Pet Supplies', 'icon' => 'pets'],
            ['name' => 'Electronics and Gadgets', 'icon' => 'devices'],
            ['name' => "Women's Apparel", 'icon' => 'woman'],
            ['name' => "Men's Apparel", 'icon' => 'man'],
            ['name' => 'Kids and Baby', 'icon' => 'child_care'],
            ['name' => 'Home and Garden', 'icon' => 'yard'],
            ['name' => 'Sports and Outdoors', 'icon' => 'sports_basketball'],
            ['name' => 'Health and Beauty', 'icon' => 'spa'],
            ['name' => 'Books and Media', 'icon' => 'menu_book'],
            ['name' => 'Food and Gourmet', 'icon' => 'restaurant'],
            ['name' => 'Furniture and Office Equipment', 'icon' => 'chair'],
            ['name' => 'Jewelry and Watches', 'icon' => 'watch'],
        ];

        $aliases = config('web-commerce.category_aliases', []);

        $rawCounts = Product::query()
            ->availableForMarketplace()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->groupBy('category')
            ->selectRaw('category, count(*) as count')
            ->pluck('count', 'category')
            ->toArray();

        $resolvedCounts = [];
        foreach ($rawCounts as $categoryName => $count) {
            $canonical = $aliases[$categoryName] ?? $categoryName;
            $resolvedCounts[$canonical] = ($resolvedCounts[$canonical] ?? 0) + $count;
        }

        $categories = array_map(function ($item) use ($resolvedCounts) {
            return [
                'name' => $item['name'],
                'icon' => $item['icon'],
                'product_count' => $resolvedCounts[$item['name']] ?? 0,
            ];
        }, $canonicalCategories);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
