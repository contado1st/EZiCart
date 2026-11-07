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
        $subtotal = collect($cart)->sum(fn ($line) => $line['price'] * $line['quantity']);

        return view('cart.index', compact('cart', 'groups', 'subtotal'));
    }

    public function add(Request $request, Product $product, CheckoutPricingService $pricing, BuyerCartService $carts): RedirectResponse|JsonResponse
    {
        abort_unless($product->compliance_status === 'approved' && ! $product->is_archived && $product->stock > 0, 404);
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:20'], 'variation_id' => ['nullable', 'integer', 'exists:product_variations,id'], 'intent' => ['nullable', 'in:buy_now']]);
        $variationId = empty($validated['variation_id']) ? null : (int) $validated['variation_id'];
        if ($variationId) {
            abort_unless(ProductVariation::whereKey($variationId)->where('product_id', $product->id)->where('is_active', true)->exists(), 422, 'Choose an available variation belonging to this product.');
        }
        $key = BuyerCartService::key($product->id, $variationId);
        $items = [$key => ['product_id' => $product->id, 'variation_id' => $variationId, 'quantity' => (int) $validated['quantity']]];
        app(ShopAvailabilityService::class)->assertOpen($items);
        $pricing->quote($items);
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

    private function beginDraft(Request $request, array $items, string $mode): void
    {
        $request->session()->forget(['checkout_token', 'checkout_quote_hash', 'applied_voucher']);
        $request->session()->put('checkout_context', ['mode' => $mode, 'items' => $items]);
    }
}
