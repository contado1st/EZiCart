<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', Rule::when($request->filled('min_price'), 'gte:min_price')],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'sort' => ['nullable', Rule::in(['relevance', 'latest', 'rating', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters['search'] = trim($filters['search'] ?? '');
        $filters['sort'] = $filters['sort'] ?? 'relevance';
        $searchTerm = $filters['search'];
        $sortOptions = ['relevance' => 'Relevance', 'latest' => 'Latest', 'rating' => 'Top rated', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];
        $base = Product::query()->availableForMarketplace();
        $categories = (clone $base)->whereNotNull('category')->where('category', '<>', '')->distinct()->orderBy('category')->pluck('category');
        $provinces = User::query()->whereHas('products', fn (Builder $products) => $products->availableForMarketplace())
            ->whereNotNull('province')->where('province', '<>', '')->distinct()->orderBy('province')->pluck('province');

        $query = $base->with('seller:id,business_name,first_name,municipality,province')
            ->withAvg('reviews as catalog_rating', 'rating')->withCount('reviews as catalog_review_count');

        if ($searchTerm !== '') {
            $pattern = '%'.strtr(mb_strtolower($searchTerm), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(function (Builder $products) use ($pattern): void {
                $products->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(products.category) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('seller', fn (Builder $sellers) => $sellers->whereRaw("LOWER(business_name) LIKE ? ESCAPE '!'", [$pattern]));
            });
        }
        if (filled($filters['category'] ?? null)) {
            $query->where('category', $filters['category']);
        }
        if (filled($filters['province'] ?? null)) {
            $query->whereHas('seller', fn (Builder $sellers) => $sellers->where('province', $filters['province']));
        }
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if (isset($filters['rating'])) {
            $query->where(Review::query()->selectRaw('AVG(rating)')->whereColumn('reviews.product_id', 'products.id'), '>=', (int) $filters['rating']);
        }

        match ($filters['sort']) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('catalog_rating')->orderByDesc('catalog_review_count'),
            'latest' => $query->orderByDesc('created_at'),
            default => $searchTerm !== ''
                ? $query->orderByRaw('CASE WHEN LOWER(products.name) = ? THEN 0 ELSE 1 END', [mb_strtolower($searchTerm)])
                : $query->orderByDesc('created_at'),
        };
        $products = $query->orderByDesc('id')->paginate(24)->appends(Arr::except($filters, 'page'));

        $activeFilters = [];
        foreach (['category' => 'Category', 'province' => 'Ships from', 'rating' => 'Minimum rating'] as $key => $label) {
            if (filled($filters[$key] ?? null)) {
                $activeFilters[] = ['label' => $label.': '.$filters[$key].($key === 'rating' ? ' stars' : ''), 'query' => Arr::except($filters, [$key, 'page'])];
            }
        }
        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $activeFilters[] = [
                'label' => 'Price: ₱'.number_format((float) ($filters['min_price'] ?? 0), 2).' – '.(isset($filters['max_price']) ? '₱'.number_format((float) $filters['max_price'], 2) : 'Any'),
                'query' => Arr::except($filters, ['min_price', 'max_price', 'page']),
            ];
        }

        return view('search.index', compact('products', 'categories', 'provinces', 'filters', 'searchTerm', 'sortOptions', 'activeFilters'));
    }
}
