<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductRecommendationService
{
    public function recordView(User $buyer, Product $product): void
    {
        DB::table('product_interactions')->upsert([
            'user_id' => $buyer->id, 'product_id' => $product->id, 'views' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ], ['user_id', 'product_id'], ['updated_at']);
    }

    public function query(User $buyer): Builder
    {
        $scores = DB::table('product_interactions')->join('products', 'products.id', '=', 'product_interactions.product_id')
            ->where('product_interactions.user_id', $buyer->id)->where('product_interactions.updated_at', '>=', now()->subDays(90))
            ->select('products.category')->selectRaw('COUNT(*) as score')->groupBy('products.category')->pluck('score', 'category');
        $purchases = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')->where('orders.buyer_id', $buyer->id)
            ->whereIn('orders.status', ['DELIVERED', 'COMPLETED'])->select('products.category')->selectRaw('COUNT(*) as score')
            ->groupBy('products.category')->pluck('score', 'category');
        foreach ($purchases as $category => $score) {
            $scores[$category] = ($scores[$category] ?? 0) + $score * 3;
        }
        $query = Product::query()->availableForMarketplace()->whereHas('seller', fn ($q) => $q->where('status', 'approved'))
            ->with('seller')->withAvg('reviews as catalog_rating', 'rating')->withCount('reviews as catalog_review_count');
        if ($scores->isNotEmpty()) {
            $parts = [];
            $bindings = [];
            foreach ($scores->sortDesc()->take(20) as $category => $score) {
                $parts[] = 'WHEN category = ? THEN (? + 0)';
                array_push($bindings, $category, (int) $score);
            }
            $query->orderByRaw('(CASE '.implode(' ', $parts).' ELSE 0 END) DESC', $bindings);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
