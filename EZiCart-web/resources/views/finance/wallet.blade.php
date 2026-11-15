@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="web-operations wallet-page">
    <header class="dash-header"><div><h1 class="dash-title">Finance &amp; Wallet</h1><p class="dash-subtitle">{{ $actor->isCommerceAdmin() ? 'Platform treasury, balances and financial records.' : 'Your balances, transactions and financial records.' }} Sandbox accounting credits. This is not a live e-money or bank account; no real cash-in or cash-out is provided.</p></div><div class="workspace-page-actions"><a href="{{ route('finance.statements', 'wallet') }}" class="dash-btn-sm dash-btn-outline"><x-shop-icon name="invoice" /> Export statement</a></div></header>
    @include('components.feature-feedback')
    <x-finance-navigation />
    <section class="workspace-metrics" aria-label="Financial balances">
        <x-workspace-metric label="Available PHP" :value="number_format($available->balance_cents / 100, 2)" icon="money" :detail="$actor->isCommerceAdmin() ? 'Shared platform treasury; eligible for spending' : 'Your available sandbox funds'" />
        <x-workspace-metric label="Pending PHP" :value="number_format($pending / 100, 2)" icon="lock" detail="Delivery, funding and dispute holds still apply" />
        <x-workspace-metric label="Reserved promotions PHP" :value="number_format($reserved / 100, 2)" icon="voucher" detail="Committed campaign budget; not yet fully consumed" />
        <x-workspace-metric label="Withdrawal holds PHP" :value="number_format($held / 100, 2)" icon="invoice" detail="Awaiting review or sandbox execution" />
        @if($actor->role === 'seller')<x-workspace-metric label="Seller payout holds PHP" :value="number_format($payoutReserved / 100, 2)" icon="lock" detail="Eligible proceeds reserved for requested settlements" />@endif
    </section>
    @if($actor->isLogisticsAdmin())
    <section class="dash-panel"><h2>Logistics control balances</h2><p>Operating allocations, net margin and unresolved work claims are separate from personal earnings. These are ledger allocations, not evidence that an external invoice has been paid.</p><div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Control account</th><th>Balance PHP</th></tr></thead><tbody>@forelse($logisticsPools as $pool)<tr><td>{{ str_replace('_', ' ', $pool->type) }}</td><td>{{ number_format($pool->balance_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="2">No logistics settlements posted.</td></tr>@endforelse</tbody></table></div></section>
    @endif
    @if($available->frozen)<p role="alert">This account is under financial investigation. Spending is frozen until authorized recovery is resolved.</p>@endif
    <x-workspace-tabs :tabs="[
        ['id' => 'wallet-history', 'label' => 'Transactions', 'count' => $entries->total()],
        ['id' => 'wallet-funding', 'label' => 'Add demo funds', 'count' => $intents->total()],
        ['id' => 'wallet-withdrawals', 'label' => 'Withdrawals', 'count' => $withdrawals->total()],
    ]" />
    <section class="dash-panel" id="wallet-history"><h2>Posted transactions</h2><p>Positive entries credit this account; negative entries debit it. Original entries remain unchanged when reversed.</p>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>When</th><th>Transaction</th><th>Account</th><th class="financial-amount">Change PHP</th><th class="financial-amount">Before PHP</th><th class="financial-amount">After PHP</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr><td>{{ $entry->created_at }}</td><td>{{ $entry->transaction->reference }}<br>{{ str_replace('_', ' ', $entry->transaction->type) }}</td><td>{{ $entry->account->bucket }} · {{ $entry->account->type }}</td><td class="financial-amount">{{ number_format($entry->delta_cents / 100, 2) }}</td><td class="financial-amount">{{ number_format($entry->balance_before_cents / 100, 2) }}</td><td class="financial-amount">{{ number_format($entry->balance_after_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="6">
            @if($entries->total() > 0)<p>No transactions on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $entries->url(1) }}">Return to first page</a>@else<p>No posted transactions.</p>@endif
        </td></tr>@endforelse
        </tbody></table></div>{{ $entries->links() }}
    </section>
    @include('finance.partials.demo-funding')
    <section class="dash-panel" id="wallet-withdrawals"><h2>Withdrawal requests</h2><p>Requests reserve available funds immediately. Seller order payout requests reserve eligible proceeds in a separate hold. Execution is a sandbox ledger operation, not a bank transfer.</p>
        @if(in_array($actor->role, ['buyer', 'seller', 'courier', 'sorting_center'], true))
        @php
            $withdrawalOld = old('wallet_form') === 'withdrawal' ? array_filter(session()->getOldInput(), fn ($value) => is_scalar($value)) : [];
        @endphp
        <form class="workspace-row-form" method="POST" action="{{ route('finance.wallet.withdraw') }}" data-decision="financial_action" data-decision-title="Request a withdrawal?" data-decision-message="This reserves the amount from available funds. Administrator review and sandbox execution are separate steps." data-decision-label="Request withdrawal">
            @csrf<input type="hidden" name="wallet_form" value="withdrawal"><input type="hidden" name="request_key" value="{{ $withdrawalOld['request_key'] ?? Str::uuid() }}">
            <label for="wallet-withdrawal-amount">Amount PHP<input id="wallet-withdrawal-amount" name="amount" type="number" min="0.01" max="1000000" step="0.01" value="{{ $withdrawalOld['amount'] ?? '' }}" required></label>
            <label for="wallet-withdrawal-destination">Sandbox destination reference<input id="wallet-withdrawal-destination" name="destination" minlength="5" maxlength="500" value="{{ $withdrawalOld['destination'] ?? '' }}" required placeholder="Test destination only"></label>
            <button type="submit" class="dash-btn-sm dash-btn-outline">Request withdrawal</button>
        </form>@endif
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Reference</th><th>Account</th><th class="financial-amount">Amount PHP</th><th>Status</th><th>Review</th></tr></thead><tbody>
        @forelse($withdrawals as $withdrawal)<tr><td>{{ $withdrawal->reference }}</td><td>#{{ $withdrawal->user_id }}</td><td class="financial-amount">{{ number_format($withdrawal->amount_cents / 100, 2) }}</td><td>{{ $withdrawal->status }}</td><td>
            @if(($actor->isCommerceAdmin() || $actor->isLogisticsAdmin()) && in_array($withdrawal->status, ['REQUESTED', 'APPROVED'], true))
                <form class="grid gap-2" method="POST" action="{{ route('finance.wallet.withdrawals.review', $withdrawal) }}" data-decision="financial_action" data-decision-title="Record this financial action?" data-decision-message="Review the amount, recipient, evidence and funding source before proceeding." data-decision-label="Confirm action">
                    @csrf<label for="withdrawal-decision-{{ $withdrawal->id }}">Decision</label><select id="withdrawal-decision-{{ $withdrawal->id }}" name="decision" required>@if($withdrawal->status === 'REQUESTED')<option value="approve">Approve</option><option value="reject">Reject and release hold</option>@else<option value="settle">Settle in sandbox</option>@endif</select>
                    <label>Reason<input name="reason" minlength="5" maxlength="500" required></label>
                    @if($withdrawal->status === 'APPROVED')<label>Sandbox execution reference<input name="reference" minlength="5" maxlength="120" required></label>@endif
                    <button class="dash-btn-sm dash-btn-outline" type="submit">Record decision</button>
                </form>
            @else View status above @endif
        </td></tr>@empty<tr><td colspan="5">@if($withdrawals->total() > 0)<p>No withdrawal requests on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $withdrawals->url(1) }}">Return to first page</a>@else<p>No withdrawal requests.</p>@endif</td></tr>@endforelse
        </tbody></table></div>{{ $withdrawals->links() }}
    </section>
</main>
@endsection
