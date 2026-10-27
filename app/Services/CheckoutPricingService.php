<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutPricingService
{
    public static function cents(int|float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /** Authoritative integer-cent calculation shared by the page and order creation. */
    public function quote(array $cart, ?Voucher $voucher = null, bool $lock = false): array
    {
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }
        $productIds = array_map(fn ($item) => $item['product_id'] ?? $item['id'] ?? 0, $cart);
        $products = Product::query()->with('seller')->whereIn('id', $productIds)->orderBy('id');
        $variations = ProductVariation::query()->whereIn('product_id', $productIds)->orderBy('id');
        if ($lock) {
            $products->lockForUpdate();
            $variations->lockForUpdate();
        }
        $products = $products->get()->keyBy('id');
        $variations = $variations->get()->keyBy('id');
        $lines = $groups = $productDemand = $variationDemand = [];

        foreach ($cart as $key => $item) {
            $product = $products->get($item['product_id'] ?? $item['id'] ?? 0);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (! $product || $product->compliance_status !== 'approved' || $product->is_archived || $product->seller?->status !== 'approved' || $quantity === false || $quantity < 1 || $quantity > 20) {
                throw ValidationException::withMessages(['cart' => 'An item is unavailable or has an invalid quantity. Return to your cart.']);
            }
            $variation = empty($item['variation_id']) ? null : $variations->get($item['variation_id']);
            if ((! empty($item['variation_id']) && (! $variation || $variation->product_id !== $product->id || ! $variation->is_active))
                || (empty($item['variation_id']) && $variations->contains(fn ($variant) => $variant->product_id === $product->id))) {
                throw ValidationException::withMessages(['variation_id' => 'Choose an available variation belonging to this product.']);
            }
            $price = self::cents($product->price) + ($variation ? self::cents($variation->price_adjustment) : 0);
            if ($price < 1) {
                throw ValidationException::withMessages(['cart' => 'An item has an invalid price. Contact the seller.']);
            }
            $productDemand[$product->id] = ($productDemand[$product->id] ?? 0) + $quantity;
            if ($variation) {
                $variationDemand[$variation->id] = ($variationDemand[$variation->id] ?? 0) + $quantity;
            }
            $line = [
                'product' => $product, 'variation' => $variation, 'product_id' => $product->id,
                'variation_id' => $variation?->id, 'variation_info' => $variation ? "{$variation->type}: {$variation->value}" : null,
                'name' => $product->name, 'quantity' => $quantity, 'price' => $price / 100,
                'unit_cents' => $price, 'item_cents' => $price * $quantity, 'seller_id' => $product->user_id,
                'seller_name' => $product->seller->business_name ?? 'Merchant', 'image_path' => $product->image_path,
            ];
            $lines[$key] = $line;
            $groups[$product->user_id]['lines'][$key] = $line;
        }
        foreach ($productDemand as $id => $quantity) {
            if ($products[$id]->stock < $quantity) {
                throw ValidationException::withMessages(['cart' => 'An item has insufficient stock. Update your cart.']);
            }
        }
        foreach ($variationDemand as $id => $quantity) {
            if ($variations[$id]->stock < $quantity) {
                throw ValidationException::withMessages(['cart' => 'A selected variation has insufficient stock.']);
            }
        }
        ksort($groups);
        $eligible = 0;
        $standardShipping = (int) (DB::table('commerce_settings')->where('key', 'shipping_per_seller_cents')->value('value') ?? config('commerce.shipping_per_seller_cents'));
        foreach ($groups as $sellerId => &$group) {
            $group['subtotal_cents'] = array_sum(array_column($group['lines'], 'item_cents'));
            $group['eligible'] = $voucher && ($voucher->seller_id === null || (int) $voucher->seller_id === (int) $sellerId);
            if ($group['eligible']) {
                $eligible += $group['subtotal_cents'];
            }
        }
        unset($group);
        if ($voucher && ($eligible < 1 || ! $voucher->isValidForAmount($eligible / 100))) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher is inactive, expired, exhausted, or does not meet the eligible seller minimum spend.']);
        }
        $discount = $voucher ? self::cents($voucher->calculateDiscount($eligible / 100)) : 0;
        $allocations = $remainders = [];
        foreach ($groups as $sellerId => $group) {
            if ($group['eligible']) {
                [$allocations[$sellerId], $remainders[$sellerId]] = $this->proportionalShare($discount, $group['subtotal_cents'], $eligible);
            }
        }
        $remaining = $discount - array_sum($allocations);
        arsort($remainders, SORT_NUMERIC);
        foreach ($remainders as $sellerId => $remainder) {
            if ($remaining < 1) {
                break;
            }
            $allocations[$sellerId]++;
            $remaining--;
        }
        foreach ($groups as $sellerId => &$group) {
            $group['discount_cents'] = $allocations[$sellerId] ?? 0;
            $group['platform_discount_cents'] = $voucher && $voucher->seller_id === null ? $group['discount_cents'] : 0;
            $group['shipping_cents'] = $standardShipping;
            $net = $group['subtotal_cents'] - $group['discount_cents'];
            $group['commission_cents'] = intdiv(($net + $group['platform_discount_cents']) * (int) config('commerce.commission_basis_points') + 5000, 10000);
            $group['total_cents'] = $net + $group['shipping_cents'];
            if ($group['total_cents'] > 9999999999) {
                throw ValidationException::withMessages(['cart' => 'A seller order exceeds the supported monetary amount. Reduce its quantity.']);
            }
        }
        unset($group);
        $fingerprint = array_map(fn ($line) => [$line['product_id'], $line['variation_id'], $line['name'], $line['variation_info'], $line['seller_id'], $line['quantity'], $line['unit_cents']], array_values($lines));
        sort($fingerprint);
        $total = array_sum(array_column($groups, 'total_cents'));

        return [
            'lines' => $lines, 'groups' => $groups, 'total_cents' => $total,
            'subtotal_cents' => array_sum(array_column($groups, 'subtotal_cents')),
            'discount_cents' => $discount, 'shipping_cents' => array_sum(array_column($groups, 'shipping_cents')),
            'standard_shipping_cents' => $standardShipping,
            'hash' => hash('sha256', json_encode([$fingerprint, $voucher?->id, $discount, $total], JSON_THROW_ON_ERROR)),
        ];
    }

    /** Exact floor and remainder of (a * b) / divisor without overflowing a 64-bit product. */
    private function proportionalShare(int $a, int $b, int $divisor): array
    {
        $quotient = $remainder = 0;
        $termQuotient = intdiv($a, $divisor);
        $termRemainder = $a % $divisor;
        while ($b > 0) {
            if ($b % 2 === 1) {
                $quotient += $termQuotient;
                $remainder += $termRemainder;
                if ($remainder >= $divisor) {
                    $quotient++;
                    $remainder -= $divisor;
                }
            }
            $b = intdiv($b, 2);
            $termQuotient *= 2;
            $termRemainder *= 2;
            if ($termRemainder >= $divisor) {
                $termQuotient++;
                $termRemainder -= $divisor;
            }
        }

        return [$quotient, $remainder];
    }
}
