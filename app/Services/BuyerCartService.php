<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerCartService
{
    public function __construct(private CheckoutPricingService $pricing) {}

    /** Import a pre-existing session cart only before this buyer has a saved cart. */
    public function read(User $buyer, array $legacy = []): array
    {
        $record = DB::table('buyer_carts')->where('user_id', $buyer->id)->first();
        if ($record) {
            return json_decode($record->items, true, flags: JSON_THROW_ON_ERROR);
        }
        if ($legacy === []) {
            return [];
        }

        return DB::transaction(function () use ($buyer, $legacy): array {
            $this->lockBuyer($buyer);
            $record = DB::table('buyer_carts')->where('user_id', $buyer->id)->first();
            if ($record) {
                return json_decode($record->items, true, flags: JSON_THROW_ON_ERROR);
            }
            $items = [];
            foreach ($legacy as $line) {
                $id = filter_var($line['product_id'] ?? null, FILTER_VALIDATE_INT);
                $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || ! $quantity || $quantity < 1 || $quantity > 20) {
                    continue;
                }
                $variation = empty($line['variation_id']) ? null : (int) $line['variation_id'];
                $items[self::key($id, $variation)] = ['product_id' => $id, 'variation_id' => $variation, 'quantity' => $quantity];
            }
            $this->write($buyer, $items);

            return $items;
        }, 3);
    }

    public static function key(int $productId, ?int $variationId = null): string
    {
        return $productId.($variationId ? '_'.$variationId : '');
    }

    /** Display current catalog information without discarding unavailable saved lines. */
    public function display(User $buyer): array
    {
        $items = $this->read($buyer);
        $products = Product::with('seller')->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
        $variations = ProductVariation::whereIn('product_id', $products->modelKeys())->get()->groupBy('product_id');
        foreach ($items as &$line) {
            $product = $products->get($line['product_id']);
            $options = $variations->get($line['product_id'], collect());
            $variation = $line['variation_id'] ? $options->firstWhere('id', $line['variation_id']) : null;
            $stock = $product ? min(20, $product->stock, $variation ? $variation->stock : 20) : 0;
            $price = $product ? CheckoutPricingService::cents($product->price) + ($variation ? CheckoutPricingService::cents($variation->price_adjustment) : 0) : 0;
            $reason = match (true) {
                ! $product || $product->is_archived || $product->compliance_status !== 'approved' || $product->seller?->status !== 'approved' => 'Product is currently unavailable.',
                ! $product->seller->acceptsWebOrders() => 'This shop is temporarily unavailable.',
                $line['variation_id'] && (! $variation || ! $variation->is_active) => 'This variation is unavailable.',
                ! $line['variation_id'] && $options->isNotEmpty() => 'Choose a variation on the product page.',
                $stock < 1 => 'Currently out of stock.',
                $line['quantity'] > $stock => 'Only '.$stock.' available. Update the quantity.',
                $price < 1 => 'Price is unavailable.',
                default => null,
            };
            $line += ['name' => $product?->name ?? 'Unavailable product', 'price' => $price / 100,
                'unit_cents' => $price, 'image_path' => $variation?->image_path ?: $product?->image_path,
                'seller_id' => $product?->user_id ?? 0, 'seller_name' => $product?->seller?->business_name ?: 'Shop unavailable',
                'variation_info' => $variation ? $variation->type.': '.$variation->value : null,
                'available' => $reason === null, 'unavailable_reason' => $reason, 'max_quantity' => $stock];
        }
        unset($line);

        return $items;
    }

    public function add(User $buyer, int $productId, ?int $variationId, int $quantity): void
    {
        $this->mutate($buyer, function (array $items) use ($productId, $variationId, $quantity): array {
            $key = self::key($productId, $variationId);
            $items[$key] = ['product_id' => $productId, 'variation_id' => $variationId, 'quantity' => ($items[$key]['quantity'] ?? 0) + $quantity];
            $this->pricing->quote(array_filter($items, fn ($line) => $line['product_id'] === $productId));

            return $items;
        });
    }

    public function update(User $buyer, string $key, int $quantity): void
    {
        $this->mutate($buyer, function (array $items) use ($key, $quantity): array {
            abort_unless(isset($items[$key]), 404);
            $items[$key]['quantity'] = $quantity;
            $productId = $items[$key]['product_id'];
            $this->pricing->quote(array_filter($items, fn ($line) => $line['product_id'] === $productId));

            return $items;
        });
    }

    public function remove(User $buyer, string $key): void
    {
        $this->mutate($buyer, function (array $items) use ($key): array {
            abort_unless(isset($items[$key]), 404);
            unset($items[$key]);

            return $items;
        });
    }

    public function selected(User $buyer, array $keys): array
    {
        $items = $this->read($buyer);
        $selected = [];
        foreach ($keys as $key) {
            if (! isset($items[$key])) {
                throw ValidationException::withMessages(['selected_keys' => 'Your cart changed. Select the items you want to buy again.']);
            }
            $selected[$key] = $items[$key];
        }
        $this->pricing->quote($selected);
        app(WebCheckoutService::class)->assertShopsOpen($selected);

        return $selected;
    }

    /** Purchase and remove exactly the reviewed quantities under the buyer lock. */
    public function complete(User $buyer, string $token, array $selected, Closure $place): Purchase
    {
        return DB::transaction(function () use ($buyer, $token, $selected, $place): Purchase {
            $this->lockBuyer($buyer);
            if (Purchase::where('buyer_id', $buyer->id)->where('checkout_token', $token)->exists()) {
                return $place();
            }
            $items = $this->read($buyer);
            foreach ($selected as $key => $line) {
                if (! isset($items[$key]) || $items[$key]['quantity'] < $line['quantity']) {
                    throw ValidationException::withMessages(['cart' => 'Your saved cart changed. Return to your cart and select your items again.']);
                }
                $items[$key]['quantity'] -= $line['quantity'];
                if ($items[$key]['quantity'] === 0) {
                    unset($items[$key]);
                }
            }
            $purchase = $place();
            $this->write($buyer, $items);

            return $purchase;
        }, 3);
    }

    private function mutate(User $buyer, Closure $change): void
    {
        DB::transaction(function () use ($buyer, $change): void {
            $this->lockBuyer($buyer);
            $this->write($buyer, $change($this->read($buyer)));
        }, 3);
    }

    private function lockBuyer(User $buyer): void
    {
        abort_unless($buyer->role === 'buyer', 403);
        if (DB::getDriverName() === 'sqlite') {
            DB::table('users')->where('id', $buyer->id)->update(['updated_at' => DB::raw('updated_at')]);
        }
        User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
    }

    private function write(User $buyer, array $items): void
    {
        DB::table('buyer_carts')->upsert([['user_id' => $buyer->id, 'items' => json_encode($items, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]], ['user_id'], ['items', 'updated_at']);
    }
}
