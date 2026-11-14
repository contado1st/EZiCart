@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="web-operations">
    <header class="dash-header"><div><h1 class="dash-title">Pay with EZiCart Wallet</h1><p class="dash-subtitle">{{ $order->order_number }} · School demo funds only</p></div><a class="dash-btn-sm" href="{{ route('buyer.dashboard', ['order_group' => 'to_pay']) }}">Back to To Pay</a></header>
    @include('components.feature-feedback')
    <section class="dash-panel">
        <h2>{{ $order->isAwaitingWalletPayment() ? 'Reserved, awaiting payment' : 'Review this order' }}</h2>
        <p>Leaving this page or closing your browser keeps the same unpaid reservation until its deadline. Use Back to To Pay to resume it. If payment was already confirmed, reopening shows the recorded payment; repeating it does not charge again. This is an internal school-demo wallet, with no external gateway callback.</p>
        <p>Stock and any applied voucher are reserved until {{ $order->wallet_payment_due_at->format('M d, Y h:i A') }}. Seller preparation starts after the full payment is posted.</p>
        <dl class="grid gap-4 sm:grid-cols-3 my-4">
            <div><dt>Order total</dt><dd class="font-semibold">PHP {{ number_format($order->total_amount, 2) }}</dd></div>
            <div><dt>Available demo balance</dt><dd class="font-semibold">PHP {{ number_format(($account?->balance_cents ?? 0) / 100, 2) }}</dd></div>
            <div><dt>Additional demo funds needed</dt><dd class="font-semibold">PHP {{ number_format(max(0, \App\Services\CheckoutPricingService::cents($order->total_amount) - ($account?->balance_cents ?? 0)) / 100, 2) }}</dd></div>
        </dl>
        @if($account?->frozen)<p role="alert">Your wallet is on hold. Ask administration to review it. Do not make another payment to clear a hold.</p>@endif
        @if($order->isAwaitingWalletPayment() && $order->wallet_payment_due_at->isFuture())
            <a class="dash-btn-sm" href="{{ route('finance.wallet', ['workspace_tab' => 'wallet-funding']) }}">Request demo funds</a>
            <p>Only available funds can be spent. Pending earnings, promotion reserves and withdrawal holds cannot pay for this order.</p>
            <form class="grid gap-3 sm:max-w-md" action="{{ route('buyer.orders.wallet-payment.pay', $order) }}" method="POST" data-decision="wallet_payment" data-decision-title="Pay from your demo wallet?" data-decision-message="This debits the full order total once and notifies the seller. No COD is due after payment." data-decision-label="Confirm wallet payment" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                @csrf
                <label for="wallet-password">Confirm your password</label><input id="wallet-password" name="password" type="password" autocomplete="current-password" required>
                <button class="dash-btn-sm" type="submit" @disabled($account?->frozen || ($account?->balance_cents ?? 0) < \App\Services\CheckoutPricingService::cents($order->total_amount))>Pay PHP {{ number_format($order->total_amount, 2) }}</button>
            </form>
            <form class="mt-4" action="{{ route('buyer.orders.wallet-payment.cancel', $order) }}" method="POST" data-decision="wallet_cancel" data-decision-title="Cancel this unpaid reservation?" data-decision-message="Reserved stock and eligible voucher usage will be released. No wallet funds have been charged." data-decision-label="Cancel reservation" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button class="dash-btn-sm dash-btn-outline" type="submit">Cancel unpaid reservation</button></form>
        @else
            <p role="status">{{ $order->wallet_transaction_id ? 'A wallet payment is already recorded. Check the order for payment holds, cancellation or refund information.' : 'This reservation is expired or cancelled. It cannot be paid. Expired stock and voucher reservations are released by the scheduled cleanup.' }}</p>
        @endif
        <a class="dash-btn-sm mt-4" href="{{ route('buyer.orders.show', $order) }}">View order and payment history</a>
    </section>
</main>
@endsection
