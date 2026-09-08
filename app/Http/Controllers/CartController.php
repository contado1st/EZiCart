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
        return view('cart.index', compact('cart'));
    }

    public function add(Request $request, Product $product)
    {
        $request->validate([
            'quantity'     => 'required|integer|min:1',
            'variation_id' => 'nullable|exists:product_variations,id',
        ]);

        $quantity = (int) $request->input('quantity', 1);
        $variationId = $request->input('variation_id');

        $variation = null;
        $price = $product->price;
        $variationText = null;

        if ($variationId) {
            $variation = ProductVariation::where('id', $variationId)
                ->where('product_id', $product->id)
                ->first();

            if ($variation) {
                $price = $product->price + $variation->price_adjustment;
                $variationText = "{$variation->type}: {$variation->value}";
            }
        }

        $cart = session()->get('cart', []);
        $cartKey = $product->id . ($variationId ? '_' . $variationId : '');

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
        } else {
            $cart[$cartKey] = [
                'product_id'     => $product->id,
                'variation_id'   => $variationId,
                'variation_info' => $variationText,
                'name'           => $product->name,
                'price'          => $price,
                'quantity'       => $quantity,
                'image_path'     => $product->image_path,
                'seller_id'      => $product->user_id,
                'seller_name'    => $product->seller->business_name ?? 'Merchant',
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