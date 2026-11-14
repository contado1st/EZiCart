<?php

namespace App\Services;

use App\Models\BundleDeal;
use App\Models\FlashSale;

class CartPromotionPricingService
{
    /** Product and variation locks are already held by authoritative placement. */
    public function apply(array $lines, bool $lock): array
    {
        $slots = FlashSale::whereIn('product_id', array_column($lines, 'product_id'))->whereNull('ended_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->whereColumn('allocated_quantity', '<', 'quantity_limit')->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('product_id');
        $deals = BundleDeal::whereIn('seller_id', array_column($lines, 'seller_id'))->whereNull('ended_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn ($q) => $q->whereIn('gift_product_id', array_column($lines, 'product_id'))->orWhereHas('products', fn ($p) => $p->whereIn('products.id', array_column($lines, 'product_id'))))
            ->with('products')->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        if ($slots->isEmpty() && $deals->isEmpty()) {
            return $lines;
        }
        $remaining = $slots->map(fn ($slot) => $slot->quantity_limit - $slot->allocated_quantity)->all();
        uasort($lines, fn ($a, $b) => [$a['product_id'], $a['variation_id'] ?? 0] <=> [$b['product_id'], $b['variation_id'] ?? 0]);
        $output = [];
        foreach ($lines as $key => $line) {
            $line['source_key'] = $key;
            $slot = $slots->get($line['product_id']);
            $flashUnits = $slot && $slot->unit_cents < $line['unit_cents'] ? min($line['quantity'], $remaining[$line['product_id']]) : 0;
            if ($flashUnits > 0) {
                $flash = $line;
                $flash['quantity'] = $flashUnits;
                $flash['price'] = $slot->unit_cents / 100;
                $flash['unit_cents'] = $slot->unit_cents;
                $flash['item_cents'] = $slot->unit_cents * $flashUnits;
                $flash['pricing_snapshot'] = [...$flash['pricing_snapshot'], 'unit_cents' => $slot->unit_cents, 'flash_sale_id' => $slot->id, 'price_kind' => 'Flash price'];
                $output[] = $flash;
                $remaining[$line['product_id']] -= $flashUnits;
            }
            if ($line['quantity'] > $flashUnits) {
                $line['quantity'] -= $flashUnits;
                $line['item_cents'] = $line['unit_cents'] * $line['quantity'];
                $output[] = $line;
            }
        }
        foreach ($deals as $deal) {
            $ids = $deal->products->modelKeys();
            $qualifying = array_keys(array_filter($output, fn ($line) => $line['seller_id'] === (int) $deal->seller_id && in_array($line['product_id'], $ids, true)));
            $units = array_sum(array_map(fn ($key) => $output[$key]['quantity'], $qualifying));
            if ($units < $deal->threshold) {
                continue;
            }
            if ($deal->kind === 'percent') {
                $eligible = array_sum(array_map(fn ($key) => $output[$key]['item_cents'], $qualifying));
                $discount = intdiv($eligible * $deal->percent + 50, 100);
                $shares = $remainders = [];
                foreach ($qualifying as $key) {
                    [$shares[$key], $remainders[$key]] = CheckoutPricingService::proportionalShare($discount, $output[$key]['item_cents'], $eligible);
                }
                $left = $discount - array_sum($shares);
                arsort($remainders, SORT_NUMERIC);
                foreach ($remainders as $key => $value) {
                    if ($left-- <= 0) {
                        break;
                    }
                    $shares[$key]++;
                }
                foreach ($qualifying as $key) {
                    $output[$key]['item_cents'] -= $shares[$key];
                    $output[$key]['pricing_snapshot'] += ['bundle_deal_id' => $deal->id, 'bundle_name' => $deal->name, 'price_kind' => 'Bundle price'];
                }
            } else {
                $free = intdiv($units, $deal->threshold) * $deal->gift_quantity;
                foreach (array_keys($output) as $key) {
                    $line = $output[$key];
                    if ($line['product_id'] !== (int) $deal->gift_product_id || $line['seller_id'] !== (int) $deal->seller_id || $free < 1) {
                        continue;
                    }
                    $count = min($free, $line['quantity']);
                    $free -= $count;
                    $output[$key]['quantity'] = $count;
                    $output[$key]['item_cents'] = 0;
                    $output[$key]['pricing_snapshot'] += ['bundle_deal_id' => $deal->id, 'bundle_name' => $deal->name, 'price_kind' => 'Free gift'];
                    if ($count < $line['quantity']) {
                        $line['quantity'] -= $count;
                        $line['item_cents'] = $line['quantity'] * $line['unit_cents'];
                        $output[] = $line;
                    }
                }
            }
        }
        // Split uneven cent allocations into actual units, not rounded average prices.
        $result = [];
        foreach ($output as $line) {
            $low = intdiv($line['item_cents'], $line['quantity']);
            $highUnits = $line['item_cents'] % $line['quantity'];
            foreach ([[$line['quantity'] - $highUnits, $low], [$highUnits, $low + 1]] as [$quantity, $unit]) {
                if ($quantity === 0) {
                    continue;
                }
                $segment = $line;
                $segment['quantity'] = $quantity;
                $segment['price'] = $unit / 100;
                $segment['unit_cents'] = $unit;
                $segment['item_cents'] = $unit * $quantity;
                $segment['pricing_snapshot']['pre_bundle_unit_cents'] = $line['unit_cents'];
                $segment['pricing_snapshot']['unit_cents'] = $unit;
                $result[] = $segment;
            }
        }

        return $result;
    }
}
