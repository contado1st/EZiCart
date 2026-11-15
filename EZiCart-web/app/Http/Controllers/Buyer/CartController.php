<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\BuyerCartService;
use App\Services\CheckoutPricingService;
use App\Services\ShopAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, BuyerCartService $carts): View
    {
        $cart = $carts->display($request->user());
        $groups = collect($cart)->groupBy('seller_id', preserveKeys: true);
        $subtotal = collect($cart)->sum(fn ($line) => ($line['item_cents'] ?? round($line['price'] * $line['quantity'] * 100)) / 100);

        return view('cart.index', compact('cart', 'groups', 'subtotal'));
    }

    public function add(Request $request, Product $product, CheckoutPricingService $pricing, BuyerCartService $carts): RedirectResponse|JsonResponse
    {
        abort_unless($product->isListingVisible() && $product->stock > 0, 404);
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:20'], 'variation_id' => ['nullable', 'integer', 'exists:product_variations,id'], 'intent' => ['nullable', 'in:buy_now']]);
        $variationId = empty($validated['variation_id']) ? null : (int) $validated['variation_id'];
        if ($variationId) {
            abort_unless(ProductVariation::whereKey($variationId)->where('product_id', $product->id)->where('is_active', true)->exists(), 422, 'Choose an available variation belonging to this product.');
        }
        $key = BuyerCartService::key($product->id, $variationId);
        $items = [$key => ['product_id' => $product->id, 'variation_id' => $variationId, 'quantity' => (int) $validated['quantity']]];
        app(ShopAvailabilityService::class)->assertOpen($items);
        $pricing->quote($items, context: ['buyer' => $request->user()]);
        if (($validated['intent'] ?? null) === 'buy_now') {
            $this->beginDraft($request, $items, 'buy_now');

            return redirect()->route('checkout.index');
        }
        $carts->add($request->user(), $product->id, $variationId, (int) $validated['quantity']);
        $cart = $carts->display($request->user());
        $request->session()->put('cart', $cart);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Item added to your saved cart.', 'count' => count($cart)]);
        }

        return redirect()->route('cart.index')->with('success', 'Item added to your saved cart.');
    }

    public function selectionQuote(Request $request, BuyerCartService $carts, CheckoutPricingService $pricing): JsonResponse
    {
        $data = $request->validate(['selected_keys' => ['required', 'array', 'min:1', 'max:200'], 'selected_keys.*' => ['required', 'string', 'distinct', 'regex:/^\d+(?:_\d+)?$/']]);
        $items = $carts->selected($request->user(), $data['selected_keys']);
        $quote = $pricing->quote($items, context: ['buyer' => $request->user()]);

        $lines = [];
        foreach ($quote['lines'] as $key => $line) {
            $source = (string) ($line['source_key'] ?? $key);
            $lines[$source]['item_cents'] = ($lines[$source]['item_cents'] ?? 0) + $line['item_cents'];
            $lines[$source]['segments'][] = ['quantity' => $line['quantity'], 'unit_cents' => $line['unit_cents'], 'label' => $line['pricing_snapshot']['price_kind'] ?? 'Current price'];
        }

        return response()->json(['subtotal_cents' => $quote['subtotal_cents'], 'lines' => $lines, 'message' => 'Current selected-item promotions. Shipping and vouchers are confirmed at checkout.']);
    }

    public function select(Request $request, BuyerCartService $carts): RedirectResponse
    {
        $data = $request->validate(['selected_keys' => ['required', 'array', 'min:1', 'max:200'], 'selected_keys.*' => ['required', 'string', 'distinct', 'regex:/^\d+(?:_\d+)?$/']], ['selected_keys.required' => 'Select at least one item to check out.']);
        $this->beginDraft($request, $carts->selected($request->user(), $data['selected_keys']), 'cart');

        return redirect()->route('checkout.index');
    }

    public function update(Request $request, string $cartKey, BuyerCartService $carts): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:20']]);
        $carts->update($request->user(), $cartKey, (int) $data['quantity']);
        $request->session()->put('cart', $carts->display($request->user()));

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, string $cartKey, BuyerCartService $carts): RedirectResponse
    {
        $carts->remove($request->user(), $cartKey);
        $request->session()->put('cart', $carts->display($request->user()));

        return back()->with('success', 'Item removed from cart.');
    }

    public function changeVariation(Request $request, string $cartKey, BuyerCartService $carts): RedirectResponse
    {
        $data = $request->validate(['variation_id' => ['required', 'integer', 'min:1']]);
        $newKey = $carts->changeVariation($request->user(), $cartKey, (int) $data['variation_id']);
        if ($request->session()->get('checkout_context.mode') === 'cart') {
            $request->session()->forget(['checkout_context', 'checkout_token', 'checkout_quote_hash', 'applied_voucher']);
        }
        $request->session()->put('cart', $carts->display($request->user()));

        return redirect()->route('cart.index')->with('success', 'Variation updated. Prices and availability have been refreshed.')
            ->with('cart_variation_change', ['previous_key' => $cartKey, 'new_key' => $newKey]);
    }

    private function beginDraft(Request $request, array $items, string $mode): void
    {
        $request->session()->forget(['checkout_token', 'checkout_quote_hash', 'applied_voucher']);
        $request->session()->put('checkout_context', ['mode' => $mode, 'items' => $items]);
    }
}
