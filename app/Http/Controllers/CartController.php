<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', []);

        // Calculate initial subtotal for order summary
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        return view('cart.index', compact('cart', 'subtotal'));
    }

    public function add(Request $request, Product $product)
    {
        // Enforce product approval and availability
        if ($product->status !== 'approved' || $product->is_archived || $product->stock <= 0) {
            return back()->with('error', 'This item is not currently available for purchase.');
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
            'variation_id' => 'nullable|exists:product_variations,id',
        ]);

        $quantity = (int) $request->input('quantity', 1);
        $variationId = $request->input('variation_id');

        $variation = null;
        $price = $product->discounted_price; // Use effective discounted price
        $variationText = null;
        $itemImage = $product->image_path;

        if ($variationId) {
            $variation = ProductVariation::where('id', $variationId)
                ->where('product_id', $product->id)
                ->first();

            if ($variation) {
                $price = $variation->discounted_price;
                $variationText = "{$variation->type}: {$variation->value}";

                if ($variation->image_path) {
                    $itemImage = $variation->image_path;
                }
            }
        }

        $cart = session()->get('cart', []);
        $cartKey = $product->id.($variationId ? '_'.$variationId : '');

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
        } else {
            $cart[$cartKey] = [
                'cart_key' => $cartKey,
                'product_id' => $product->id,
                'id' => $product->id,
                'variation_id' => $variationId,
                'variation_info' => $variationText,
                'name' => $product->name,
                'price' => $price,
                'quantity' => $quantity,
                'stock' => $variation ? $variation->stock : ($product->stock ?? 99),
                'image_path' => $itemImage,
                'seller_id' => $product->user_id,
                'business_name' => $product->seller->business_name ?? 'Merchant',
            ];
        }

        session()->put('cart', $cart);

        return redirect()->route('cart.index')->with('success', 'Item added to your cart.');
    }

    public function update(Request $request, $cartKey)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session()->get('cart', []);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] = (int) $request->quantity;
            session()->put('cart', $cart);

            return back()->with('success', 'Cart updated.');
        }

        return back()->with('error', 'Item not found in cart.');
    }

    public function remove($cartKey)
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$cartKey])) {
            unset($cart[$cartKey]);
            session()->put('cart', $cart);

            return back()->with('success', 'Item removed from cart.');
        }

        return back()->with('error', 'Item not found in cart.');
    }
}
