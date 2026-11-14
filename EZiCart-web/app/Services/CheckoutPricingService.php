<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
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
    public function quote(array $cart, ?Voucher $voucher = null, bool $lock = false, array $context = []): array
    {
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }
        $productIds = array_map(fn ($item) => $item['product_id'] ?? $item['id'] ?? 0, $cart);
        $products = Product::query()->with(['seller', 'currentSale'])->whereIn('id', $productIds)->orderBy('id');
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
            if (! $product || ! $product->isListingVisible() || $product->seller?->status !== 'approved' || $quantity === false || $quantity < 1 || $quantity > 20) {
                throw ValidationException::withMessages(['cart' => 'An item is unavailable or has an invalid quantity. Return to your cart.']);
            }
            $variation = empty($item['variation_id']) ? null : $variations->get($item['variation_id']);
            if ((! empty($item['variation_id']) && (! $variation || $variation->product_id !== $product->id || ! $variation->is_active))
                || (empty($item['variation_id']) && $variations->contains(fn ($variant) => $variant->product_id === $product->id))) {
                throw ValidationException::withMessages(['variation_id' => 'Choose an available variation belonging to this product.']);
            }
            $priceSnapshot = app(ProductPriceService::class)->resolve($product, $variation, false);
            $price = $priceSnapshot['unit_cents'];
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
                'unit_cents' => $price, 'item_cents' => $price * $quantity, 'seller_id' => $product->user_id, 'pricing_snapshot' => $priceSnapshot,
                'seller_name' => $product->seller->business_name ?? 'Merchant', 'image_path' => $product->image_path,
            ];
            $lines[$key] = $line;
            $groups[$product->user_id]['lines'][$key] = $line;
        }
        $lines = app(CartPromotionPricingService::class)->apply($lines, $lock);
        $groups = [];
        foreach ($lines as $key => $line) {
            $groups[$line['seller_id']]['lines'][$key] = $line;
        }
        foreach ($productDemand as $id => $quantity) {
            if ($products[$id]->stock < $quantity) {
                throw ValidationException::withMessages(['cart' => 'An item has insufficient stock. Update your cart.']);
            }
        }
        if (($context['buyer'] ?? null) instanceof User) {
            app(ProductPurchaseLimitService::class)->assertDemand($context['buyer'], $productDemand);
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
            $group['shipping_snapshot'] = app(DeliveryPricingService::class)->quote($group['lines'][array_key_first($group['lines'])]['product']->seller, $group['lines'], $context['address'] ?? [], $context['payment_method'] ?? 'COD');
            $group['subtotal_cents'] = array_sum(array_column($group['lines'], 'item_cents'));
            $eligibleIds = $voucher?->product_scoped ? $voucher->products->modelKeys() : null;
            $group['eligible_subtotal_cents'] = array_sum(array_map(fn ($line) => $eligibleIds === null || in_array($line['product_id'], $eligibleIds, true) ? $line['item_cents'] : 0, $group['lines']));
            $group['eligible'] = $voucher && ($voucher->seller_id === null || (int) $voucher->seller_id === (int) $sellerId) && $group['eligible_subtotal_cents'] > 0;
            if ($group['eligible']) {
                $eligible += $group['eligible_subtotal_cents'];
            }
        }
        unset($group);
        if ($voucher) {
            if ($eligible < 1) {
                throw ValidationException::withMessages(['voucher_code' => 'This voucher does not apply to the selected shop or items.']);
            }
            $reason = app(VoucherEligibilityService::class)->reason($voucher, $eligible);
            if ($reason) {
                throw ValidationException::withMessages(['voucher_code' => $reason['message']]);
            }
        }
        $shippingVoucher = $voucher?->discount_target === 'shipping';
        $eligibleShipping = array_sum(array_map(fn ($group) => $group['eligible'] ? $group['shipping_snapshot']['total_cents'] : 0, $groups));
        $discount = $voucher ? self::cents($voucher->calculateDiscount(($shippingVoucher ? $eligibleShipping : $eligible) / 100)) : 0;
        $allocations = $remainders = [];
        foreach ($groups as $sellerId => $group) {
            if ($group['eligible'] && $discount > 0) {
                [$allocations[$sellerId], $remainders[$sellerId]] = self::proportionalShare($discount, $shippingVoucher ? $group['shipping_snapshot']['total_cents'] : $group['eligible_subtotal_cents'], $shippingVoucher ? $eligibleShipping : $eligible);
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
            $group['discount_cents'] = $shippingVoucher ? 0 : ($allocations[$sellerId] ?? 0);
            $group['shipping_discount_cents'] = $shippingVoucher ? ($allocations[$sellerId] ?? 0) : 0;
            $group['platform_discount_cents'] = $voucher && $voucher->seller_id === null ? $group['discount_cents'] : 0;
            $group['shipping_cents'] = $group['shipping_snapshot']['total_cents'] - $group['shipping_discount_cents'];
            $net = $group['subtotal_cents'] - $group['discount_cents'];
            $group['commission_cents'] = intdiv(($net + $group['platform_discount_cents']) * (int) config('commerce.commission_basis_points') + 5000, 10000);
            $group['total_cents'] = $net + $group['shipping_cents'];
            $lineDiscounts = $lineRemainders = [];
            if ($group['discount_cents'] > 0) {
                $ids = $voucher?->product_scoped ? $voucher->products->modelKeys() : null;
                foreach ($group['lines'] as $key => $line) {
                    if ($ids === null || in_array($line['product_id'], $ids, true)) {
                        [$lineDiscounts[$key], $lineRemainders[$key]] = self::proportionalShare($group['discount_cents'], $line['item_cents'], $group['eligible_subtotal_cents']);
                    }
                }
                $left = $group['discount_cents'] - array_sum($lineDiscounts);
                arsort($lineRemainders, SORT_NUMERIC);
                foreach ($lineRemainders as $key => $remainder) {
                    if ($left-- <= 0) {
                        break;
                    }
                    $lineDiscounts[$key]++;
                }
            }
            foreach ($group['lines'] as $key => &$line) {
                $line['pricing_snapshot']['voucher_id'] = $voucher?->id;
                $line['pricing_snapshot']['voucher_discount_cents'] = $lineDiscounts[$key] ?? 0;
                $lines[$key] = $line;
            }
            unset($line);
            if ($group['total_cents'] > 9999999999) {
                throw ValidationException::withMessages(['cart' => 'A seller order exceeds the supported monetary amount. Reduce its quantity.']);
            }
        }
        unset($group);
        $fingerprint = array_map(fn ($line) => [$line['product_id'], $line['variation_id'], $line['name'], $line['variation_info'], $line['seller_id'], $line['quantity'], $line['unit_cents'], $line['pricing_snapshot']], array_values($lines));
        sort($fingerprint);
        $total = array_sum(array_column($groups, 'total_cents'));

        return [
            'lines' => $lines, 'groups' => $groups, 'total_cents' => $total,
            'subtotal_cents' => array_sum(array_column($groups, 'subtotal_cents')),
            'discount_cents' => $discount, 'shipping_cents' => array_sum(array_column($groups, 'shipping_cents')),
            'standard_shipping_cents' => $standardShipping,
            'hash' => hash('sha256', json_encode([$fingerprint, $voucher?->id, $discount, $total, array_column(array_values($groups), 'shipping_snapshot')], JSON_THROW_ON_ERROR)),
        ];
    }

    /** Exact floor and remainder of (a * b) / divisor without overflowing a 64-bit product. */
    public static function proportionalShare(int $a, int $b, int $divisor): array
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
