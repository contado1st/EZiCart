<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Handle item selection checkboxes passed from cart view
        $selectedKeys = $request->input('selected_items');

        if ($selectedKeys && is_array($selectedKeys)) {
            $checkoutItems = array_intersect_key($cart, array_flip($selectedKeys));
            session()->put('checkout_items', $checkoutItems);
        } else {
            // Fallback to active session items or full cart if none specified
            $checkoutItems = session()->get('checkout_items', $cart);
        }

        if (empty($checkoutItems)) {
            return redirect()->route('cart.index')->with('error', 'Please select at least one item to proceed to checkout.');
        }

        $subtotal = 0;
        foreach ($checkoutItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $discount = 0.00;
        $appliedVoucher = session()->get('applied_voucher');

        if ($appliedVoucher) {
            $voucher = Voucher::find($appliedVoucher['id']);
            if ($voucher && $voucher->isValidForAmount($subtotal)) {
                $discount = $voucher->calculateDiscount($subtotal);
            } else {
                session()->forget('applied_voucher');
                $appliedVoucher = null;
            }
        }

        $shippingFee = 50.00;
        $total = max(0, $subtotal - $discount) + $shippingFee;
        $user = auth()->user();

        return view('checkout.index', [
            'cart'           => $checkoutItems,
            'subtotal'       => $subtotal,
            'discount'       => $discount,
            'appliedVoucher' => $appliedVoucher,
            'shippingFee'    => $shippingFee,
            'total'          => $total,
            'user'           => $user,
        ]);
    }

    public function applyVoucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string',
        ]);

        $code = strtoupper(trim($request->input('voucher_code')));
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return back()->with('error', 'Voucher code not found.');
        }

        $cart = session()->get('checkout_items', session()->get('cart', []));
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        if (!$voucher->isValidForAmount($subtotal)) {
            return back()->with('error', "Voucher cannot be applied. Minimum spend requirement: ₱" . number_format($voucher->min_spend, 2));
        }

        session()->put('applied_voucher', [
            'id'    => $voucher->id,
            'code'  => $voucher->code,
            'type'  => $voucher->type,
            'value' => $voucher->value,
        ]);

        return back()->with('success', "Voucher '{$voucher->code}' applied successfully!");
    }

    public function removeVoucher()
    {
        session()->forget('applied_voucher');
        return back()->with('success', 'Voucher removed.');
    }

    public function process(Request $request)
    {
        // Only process items that were checked for checkout
        $checkoutItems = session()->get('checkout_items', session()->get('cart', []));

        if (empty($checkoutItems)) {
            return redirect()->route('cart.index')->with('error', 'Your checkout session is empty or expired.');
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

        $appliedVoucherData = session()->get('applied_voucher');
        $voucher = null;
        if ($appliedVoucherData) {
            $voucher = Voucher::find($appliedVoucherData['id']);
        }

        DB::beginTransaction();

        try {
            $groupedCart = [];
            $cartTotalSubtotal = 0;

            foreach ($checkoutItems as $cartKey => $item) {
                $productId = $item['product_id'] ?? $item['id'];
                $product = Product::lockForUpdate()->find($productId);

                if (!$product || $product->stock < $item['quantity']) {
                    DB::rollBack();
                    return back()->with('error', "Sorry, {$item['name']} is out of stock or has insufficient inventory.");
                }

                if (!empty($item['variation_id'])) {
                    $variation = ProductVariation::lockForUpdate()->find($item['variation_id']);
                    if (!$variation || $variation->stock < $item['quantity']) {
                        DB::rollBack();
                        return back()->with('error', "Sorry, the selected variation for {$item['name']} does not have enough stock.");
                    }
                }

                $itemSubtotal = $item['price'] * $item['quantity'];
                $cartTotalSubtotal += $itemSubtotal;

                $groupedCart[$product->user_id][] = [
                    'cart_key'       => $cartKey,
                    'product'        => $product,
                    'variation_id'   => $item['variation_id'] ?? null,
                    'variation_info' => $item['variation_info'] ?? null,
                    'quantity'       => $item['quantity'],
                    'price'          => $item['price'],
                ];
            }

            // Compute overall discount
            $totalDiscount = 0.00;
            if ($voucher && $voucher->isValidForAmount($cartTotalSubtotal)) {
                $totalDiscount = $voucher->calculateDiscount($cartTotalSubtotal);
                $voucher->increment('used_count');
            }

            foreach ($groupedCart as $sellerId => $items) {
                $sellerSubtotal = 0;
                foreach ($items as $itm) {
                    $sellerSubtotal += $itm['price'] * $itm['quantity'];
                }

                // Proportional discount allocation if voucher was applied
                $sellerDiscount = ($cartTotalSubtotal > 0) 
                    ? round(($sellerSubtotal / $cartTotalSubtotal) * $totalDiscount, 2) 
                    : 0.00;

                $commissionFee = max(0, $sellerSubtotal - $sellerDiscount) * 0.10;
                $shippingFee = 50.00;
                $orderTotal = max(0, $sellerSubtotal - $sellerDiscount) + $shippingFee;

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
                    'voucher_code'      => $voucher ? $voucher->code : null,
                    'discount_amount'   => $sellerDiscount,
                    'shipping_fee'      => $shippingFee,
                    'commission_fee'    => $commissionFee,
                    'total_amount'      => $orderTotal,
                    'payment_method'    => $validated['payment_method'],
                    'status'            => 'PLACED',
                    'notes'             => $validated['notes'] ?? null,
                ]);

                foreach ($items as $itm) {
                    OrderItem::create([
                        'order_id'       => $order->id,
                        'product_id'     => $itm['product']->id,
                        'variation_info' => $itm['variation_info'] ?? null,
                        'product_name'   => $itm['product']->name,
                        'unit_price'     => $itm['price'],
                        'price'          => $itm['price'],
                        'quantity'       => $itm['quantity'],
                        'item_total'     => $itm['price'] * $itm['quantity'],
                        'subtotal'       => $itm['price'] * $itm['quantity'],
                    ]);

                    $itm['product']->decrement('stock', $itm['quantity']);

                    if (!empty($itm['variation_id'])) {
                        ProductVariation::where('id', $itm['variation_id'])->decrement('stock', $itm['quantity']);
                    }
                }
            }

            DB::commit();

            // Remove only the purchased items from the main cart session
            $mainCart = session()->get('cart', []);
            foreach (array_keys($checkoutItems) as $purchasedKey) {
                unset($mainCart[$purchasedKey]);
            }
            session()->put('cart', $mainCart);

            // Clean up checkout-specific session data
            session()->forget(['checkout_items', 'applied_voucher']);

            return redirect()->route('buyer.dashboard')->with('success', 'Order placed successfully! Waiting for seller preparation.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to place order: ' . $e->getMessage());
        }
    }
}