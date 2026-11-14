<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Voucher;
use App\Services\BuyerCartService;
use App\Services\CheckoutPricingService;
use App\Services\ShopAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartApiController extends Controller
{
    /**
     * Get buyer's saved cart items from database (same as web)
     */
    public function index(Request $request, BuyerCartService $carts): JsonResponse
    {
        $buyer = $request->user();
        $cart = $carts->display($buyer);

        $items = [];
        foreach ($cart as $key => $line) {
            $items[] = [
                'cart_key' => (string) $key,
                'product_id' => (int) $line['product_id'],
                'variation_id' => $line['variation_id'] ? (int) $line['variation_id'] : null,
                'quantity' => (int) $line['quantity'],
                'name' => $line['name'],
                'price' => (float) $line['price'],
                'image_path' => $line['image_path'],
                'seller_id' => (int) $line['seller_id'],
                'seller_name' => $line['seller_name'],
                'variation_info' => $line['variation_info'],
                'available' => (bool) $line['available'],
                'unavailable_reason' => $line['unavailable_reason'],
                'max_quantity' => (int) $line['max_quantity'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'count' => count($items),
                'total_quantity' => array_sum(array_column($items, 'quantity')),
                'subtotal' => (float) collect($items)->sum(fn ($line) => $line['price'] * $line['quantity']),
            ],
        ]);
    }

    /**
     * Add item to buyer's saved cart (same as web)
     */
    public function addItem(Request $request, BuyerCartService $carts, CheckoutPricingService $pricing): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variation_id' => ['nullable', 'integer', 'exists:product_variations,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $product = Product::with('seller')->findOrFail($validated['product_id']);
        if ($product->compliance_status !== 'approved' || $product->is_archived || $product->stock <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Product is currently unavailable.',
            ], 422);
        }

        $variationId = empty($validated['variation_id']) ? null : (int) $validated['variation_id'];
        if ($variationId) {
            $valid = ProductVariation::whereKey($variationId)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->exists();
            if (! $valid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Choose an available variation belonging to this product.',
                ], 422);
            }
        }

        try {
            $key = BuyerCartService::key($product->id, $variationId);
            $checkItems = [$key => ['product_id' => $product->id, 'variation_id' => $variationId, 'quantity' => (int) $validated['quantity']]];
            app(ShopAvailabilityService::class)->assertOpen($checkItems);
            $pricing->quote($checkItems);

            $carts->add($request->user(), $product->id, $variationId, (int) $validated['quantity']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Unable to add item to cart.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return $this->index($request, $carts);
    }

    /**
     * Update quantity of item in buyer's cart (same as web)
     */
    public function updateItem(Request $request, string $key, BuyerCartService $carts): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $quantity = (int) $validated['quantity'];

        try {
            if ($quantity <= 0) {
                $carts->remove($request->user(), $key);
            } else {
                $carts->update($request->user(), $key, $quantity);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return $this->index($request, $carts);
    }

    /**
     * Remove item from buyer's cart (same as web)
     */
    public function removeItem(Request $request, string $key, BuyerCartService $carts): JsonResponse
    {
        try {
            $carts->remove($request->user(), $key);
        } catch (\Throwable) {
            // Ignore if key didn't exist
        }

        return $this->index($request, $carts);
    }

    /**
     * Clear all items from buyer's cart
     */
    public function clearCart(Request $request, BuyerCartService $carts): JsonResponse
    {
        DB::table('buyer_carts')->where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared.',
            'data' => [
                'items' => [],
                'count' => 0,
                'total_quantity' => 0,
                'subtotal' => 0.0,
            ],
        ]);
    }

    /**
     * Sync local items with server cart
     */
    public function sync(Request $request, BuyerCartService $carts): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        if (! empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                try {
                    $carts->add(
                        $request->user(),
                        (int) $item['product_id'],
                        empty($item['variation_id']) ? null : (int) $item['variation_id'],
                        (int) $item['quantity']
                    );
                } catch (\Throwable) {
                    // Skip invalid / out-of-stock items during sync
                }
            }
        }

        return $this->index($request, $carts);
    }

    /**
     * Calculate quote for cart lines and voucher
     */
    public function quote(Request $request, CheckoutPricingService $pricing): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'integer', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'in:COD,WALLET'],
            'checkout_token' => ['nullable', 'uuid'],
            'destination' => ['nullable', 'array'],
            'destination.province' => ['required_with:destination', 'string', 'max:120'],
            'destination.municipality' => ['required_with:destination', 'string', 'max:120'],
            'destination.barangay' => ['nullable', 'string', 'max:120'],
        ]);

        $voucher = null;
        if (! empty($validated['voucher_code'])) {
            $voucher = Voucher::where('code', Str::upper(trim($validated['voucher_code'])))->first();
            if (! $voucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher code not found.',
                ], 422);
            }
        }

        try {
            $quote = $pricing->quote(
                $validated['items'],
                $voucher,
                false,
                [
                    'address' => $validated['destination'] ?? $request->user()?->only(['province', 'municipality', 'barangay']) ?? [],
                    'payment_method' => $validated['payment_method'] ?? 'COD',
                ]
            );
            if (count(array_unique(array_column($quote['lines'], 'seller_id'))) > 1) {
                throw ValidationException::withMessages(['items' => 'Choose products from one shop per checkout. Items from other shops stay saved in your cart.']);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $checkoutToken = $validated['checkout_token'] ?? (string) Str::uuid();

        return response()->json([
            'success' => true,
            'data' => [
                'subtotal' => $quote['subtotal_cents'] / 100,
                'discount' => $quote['discount_cents'] / 100,
                'shipping_fee' => $quote['shipping_cents'] / 100,
                'total' => $quote['total_cents'] / 100,
                'quote_hash' => $quote['hash'],
                'checkout_token' => $checkoutToken,
                'voucher' => $voucher ? [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'discount_cents' => $quote['discount_cents'],
                ] : null,
                'lines' => array_values(array_map(function ($line) {
                    return [
                        'product_id' => $line['product_id'],
                        'variation_id' => $line['variation_id'],
                        'variation_info' => $line['variation_info'],
                        'name' => $line['name'],
                        'price' => $line['price'],
                        'quantity' => $line['quantity'],
                        'item_total' => $line['item_cents'] / 100,
                        'seller_id' => $line['seller_id'],
                        'seller_name' => $line['seller_name'],
                        'image_path' => $line['image_path'],
                    ];
                }, $quote['lines'])),
            ],
        ]);
    }
}
