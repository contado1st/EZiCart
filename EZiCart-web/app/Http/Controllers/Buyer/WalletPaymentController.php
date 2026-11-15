<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use App\Models\Order;
use App\Services\WalletOrderPaymentService;
use Illuminate\Http\Request;

class WalletPaymentController extends Controller
{
    public function show(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        abort_unless($order->payment_method === 'WALLET' && $order->wallet_payment_due_at !== null, 404);
        $account = FinancialAccount::where('key', 'user:'.$order->buyer_id.':available')->where('user_id', $order->buyer_id)->first();

        return view('buyer.orders.wallet-payment', ['order' => $order, 'account' => $account]);
    }

    public function pay(Request $request, Order $order, WalletOrderPaymentService $payments)
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $request->validate(['password' => ['required', 'string', 'current_password']]);
        $payments->pay($order, $request->user());

        return redirect()->route('buyer.orders.show', $order)->with('success', 'Demo wallet payment recorded. The seller has been notified. No COD is due.');
    }

    public function cancel(Request $request, Order $order, WalletOrderPaymentService $payments)
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $payments->cancel($order, $request->user());

        return redirect()->route('buyer.dashboard', ['order_group' => 'cancelled'])->with('success', 'Unpaid reservation cancelled. Stock and eligible voucher use were restored; no money was charged.');
    }
}
