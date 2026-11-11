<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartApiController extends Controller
{
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
            'destination' => ['nullable', 'array'], 'destination.province' => ['required_with:destination', 'string', 'max:120'],
            'destination.municipality' => ['required_with:destination', 'string', 'max:120'], 'destination.barangay' => ['nullable', 'string', 'max:120'],
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
            $quote = $pricing->quote($validated['items'], $voucher, false, ['address' => $validated['destination'] ?? $request->user()?->only(['province', 'municipality', 'barangay']) ?? [], 'payment_method' => $validated['payment_method'] ?? 'COD']);
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
