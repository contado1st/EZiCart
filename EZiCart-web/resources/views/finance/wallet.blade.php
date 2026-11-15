@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="web-operations">
    <header class="dash-header"><div><h1 class="dash-title">Finance &amp; Wallet</h1><p class="dash-subtitle">{{ $actor->isCommerceAdmin() ? 'Platform treasury, balances and financial records.' : 'Your balances, transactions and financial records.' }} Sandbox accounting credits. This is not a live e-money or bank account; no real cash-in or cash-out is provided.</p></div><a href="{{ route('finance.statements', 'wallet') }}" class="dash-btn-sm">Export statement</a></header>
    @include('components.feature-feedback')
    <x-finance-navigation />
    <section class="workspace-metrics" aria-label="Financial balances">
        <x-workspace-metric label="Available PHP" :value="number_format($available->balance_cents / 100, 2)" icon="money" detail="Eligible for spending; platform treasury is shared" />
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
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>When</th><th>Transaction</th><th>Account</th><th>Change PHP</th><th>Before PHP</th><th>After PHP</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr><td>{{ $entry->created_at }}</td><td>{{ $entry->transaction->reference }}<br>{{ str_replace('_', ' ', $entry->transaction->type) }}</td><td>{{ $entry->account->bucket }} · {{ $entry->account->type }}</td><td>{{ number_format($entry->delta_cents / 100, 2) }}</td><td>{{ number_format($entry->balance_before_cents / 100, 2) }}</td><td>{{ number_format($entry->balance_after_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="6">No posted transactions.</td></tr>@endforelse
        </tbody></table></div>{{ $entries->links() }}
    </section>
    @include('finance.partials.demo-funding')
    <section class="dash-panel" id="wallet-withdrawals"><h2>Withdrawal requests</h2><p>Requests reserve available funds immediately. Seller order payout requests reserve eligible proceeds in a separate hold. Execution is a sandbox ledger operation, not a bank transfer.</p>
        @if(in_array($actor->role, ['buyer', 'seller', 'courier', 'sorting_center'], true))<form method="POST" action="{{ route('finance.wallet.withdraw') }}" data-decision="financial_action" data-decision-title="Request a withdrawal?" data-decision-message="This reserves the amount from available funds. Administrator review and sandbox execution are separate steps." data-decision-label="Request withdrawal">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', Str::uuid()) }}"><label>Amount PHP<input name="amount" type="number" min="0.01" max="1000000" step="0.01" value="{{ old('amount') }}" required></label><label>Sandbox destination reference<input name="destination" minlength="5" maxlength="500" value="{{ old('destination') }}" required placeholder="Test destination only"></label><button type="submit" class="dash-btn-sm">Request withdrawal</button></form>@endif
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Reference</th><th>Account</th><th>Amount PHP</th><th>Status</th><th>Review</th></tr></thead><tbody>
        @forelse($withdrawals as $withdrawal)<tr><td>{{ $withdrawal->reference }}</td><td>#{{ $withdrawal->user_id }}</td><td>{{ number_format($withdrawal->amount_cents / 100, 2) }}</td><td>{{ $withdrawal->status }}</td><td>@if(($actor->isCommerceAdmin() || $actor->isLogisticsAdmin()) && in_array($withdrawal->status, ['REQUESTED', 'APPROVED'], true))<form method="POST" action="{{ route('finance.wallet.withdrawals.review', $withdrawal) }}" data-decision="financial_action" data-decision-title="Record this financial action?" data-decision-message="Review the amount, recipient, evidence and funding source before proceeding." data-decision-label="Confirm action">@csrf<select name="decision">@if($withdrawal->status === 'REQUESTED')<option value="approve">Approve</option><option value="reject">Reject and release hold</option>@else<option value="settle">Settle in sandbox</option>@endif</select><label>Reason<input name="reason" minlength="5" maxlength="500" required></label>@if($withdrawal->status === 'APPROVED')<label>Sandbox execution reference<input name="reference" minlength="5" maxlength="120" required></label>@endif<button class="dash-btn-sm" type="submit">Record decision</button></form>@else View status above @endif</td></tr>@empty<tr><td colspan="5">No withdrawal requests.</td></tr>@endforelse
        </tbody></table></div>{{ $withdrawals->links() }}
    </section>
</main>
@endsection
