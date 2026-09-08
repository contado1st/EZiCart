<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $shippingFee = 50.00;
        $total = $subtotal + $shippingFee;
        $user = auth()->user();

        return view('checkout.index', compact('cart', 'subtotal', 'shippingFee', 'total', 'user'));
    }

    public function process(Request $request)
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $validated = $request->validate([
            'recipient_name'    => 'required|string|max:255',
            'recipient_contact' => 'required|string|max:20',
            'province'          => 'required|string',
            'municipality'      => 'required|string',
            'barangay'          => 'required|string',
            'street_address'    => 'required|string',
            'payment_method'    => 'required|string|in:COD,GCash,Bank Transfer',
            'notes'             => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            // Group cart items by seller
            $groupedCart = [];
            foreach ($cart as $item) {
                $product = Product::lockForUpdate()->find($item['id']);

                if (!$product || $product->stock < $item['quantity']) {
                    DB::rollBack();
                    return back()->with('error', "Sorry, {$item['name']} is out of stock or does not have enough inventory.");
                }

                $groupedCart[$product->user_id][] = [
                    'product'  => $product,
                    'quantity' => $item['quantity'],
                    'price'    => $item['price'],
                ];
            }

            // Create an order for each seller group
            foreach ($groupedCart as $sellerId => $items) {
                $sellerSubtotal = 0;
                foreach ($items as $itm) {
                    $sellerSubtotal += $itm['price'] * $itm['quantity'];
                }

                $commissionFee = $sellerSubtotal * 0.10; // 10% Platform Commission
                $shippingFee = 50.00;
                $orderTotal = $sellerSubtotal + $shippingFee;

                $order = Order::create([
                    'order_number'      => 'EZC-' . strtoupper(Str::random(10)),
                    'buyer_id'          => auth()->id(),
                    'seller_id'         => $sellerId,
                    'recipient_name'    => $validated['recipient_name'],
                    'recipient_contact' => $validated['recipient_contact'],
                    'province'          => $validated['province'],
                    'municipality'      => $validated['municipality'],
                    'barangay'          => $validated['barangay'],
                    'street_address'    => $validated['street_address'],
                    'subtotal'          => $sellerSubtotal,
                    'shipping_fee'      => $shippingFee,
                    'commission_fee'    => $commissionFee,
                    'total_amount'      => $orderTotal,
                    'payment_method'    => $validated['payment_method'],
                    'status'            => 'PLACED',
                    'notes'             => $validated['notes'] ?? null,
                ]);

                foreach ($items as $itm) {
                    OrderItem::create([
                        'order_id'     => $order->id,
                        'product_id'   => $itm['product']->id,
                        'product_name' => $itm['product']->name,
                        'unit_price'   => $itm['price'],
                        'quantity'     => $itm['quantity'],
                        'item_total'   => $itm['price'] * $itm['quantity'],
                    ]);

                    // Deduct stock
                    $itm['product']->decrement('stock', $itm['quantity']);
                }
            }

            DB::commit();

            session()->forget('cart');

            return redirect()->route('buyer.dashboard')->with('success', 'Order placed successfully! Waiting for seller preparation.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to place order: ' . $e->getMessage());
        }
    }
}