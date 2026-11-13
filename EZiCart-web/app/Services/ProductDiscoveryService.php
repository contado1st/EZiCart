<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductDiscoveryService
{
    public function bestSelling(Builder $products): Builder
    {
        // Refund records do not identify returned quantities, so exclude an adjusted order's units.
        $units = OrderItem::query()->selectRaw('COALESCE(SUM(order_items.quantity), 0)')
            ->whereColumn('order_items.product_id', 'products.id')
            ->whereHas('order', fn (Builder $orders) => $orders->where('status', 'COMPLETED')
                ->whereColumn('orders.seller_id', 'products.user_id')
                ->whereDoesntHave('finance', fn ($finance) => $finance->where('remittance_status', 'VOID'))
                ->whereDoesntHave('refundExecutions', fn ($refunds) => $refunds->where('status', 'EXECUTED')));

        return $products->addSelect(['completed_sales_units' => $units])->orderByDesc('completed_sales_units');
    }

    /** Optional suggestions only; never replace literal search or expose unavailable catalog titles. */
    public function suggestions(Builder $catalog, string $search): array
    {
        $needle = $this->normalize($search);
        if (strlen($needle) < 4 || strlen($needle) > 80) {
            return [];
        }
        // Cap database results and edit-distance work, instead of hydrating the entire catalog.
        $names = (clone $catalog)->select('products.name')->reorder('products.id')
            ->whereHas('seller', fn ($q) => $q->where('role', 'seller')->where('status', 'approved'))
            ->limit(300)->toBase()->pluck('name')->unique();
        $matches = [];
        foreach ($names as $name) {
            $candidate = $this->normalize($name);
            if ($candidate === '' || strlen($candidate) > 80) {
                continue;
            }
            $distance = levenshtein($needle, $candidate);
            $length = max(strlen($needle), strlen($candidate));
            if ($distance > 0 && $distance <= max(1, (int) floor($length * 0.25))) {
                $matches[] = ['name' => $name, 'distance' => $distance / $length];
            }
        }
        usort($matches, fn ($a, $b) => ($a['distance'] <=> $b['distance']) ?: strcmp($a['name'], $b['name']));

        return array_column(array_slice($matches, 0, 3), 'name');
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($value))) ?? '';
    }
}
