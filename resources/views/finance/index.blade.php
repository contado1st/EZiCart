@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="container workflow-admin-page">
    <header class="dash-header"><div><h1 class="dash-title">Financial records</h1><p class="dash-subtitle">COD collection, verified cash remittances, refunds, and seller settlements.</p></div></header>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <section class="dash-panel"><h2>Order balances and COD</h2>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Order</th><th>COD due</th><th>Collected</th><th>Remittance</th><th>Actions</th></tr></thead><tbody>
        @forelse($finances as $finance)<tr>
            <td>{{ $finance->order->order_number }}</td><td>₱{{ number_format($finance->due_cents / 100, 2) }}</td>
            <td>{{ $finance->collected_cents === null ? 'Not recorded' : '₱'.number_format($finance->collected_cents / 100, 2) }}</td>
            <td>{{ str_replace('_', ' ', $finance->remittance_status) }}@if($finance->remitted_cents !== null)<p>Submitted: ₱{{ number_format($finance->remitted_cents / 100, 2) }}</p>@endif</td>
            <td>
                @if($finance->platform_subsidy_cents > 0)
                    <p>Platform promotion: ₱{{ number_format($finance->platform_subsidy_cents / 100, 2) }} — {{ $finance->funded_cents >= $finance->platform_subsidy_cents ? 'funded' : 'funding pending' }}</p>
                    @if($actor->isCommerceAdmin() && $finance->funded_cents < $finance->platform_subsidy_cents)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('finance.subsidy', $finance->order) }}" data-decision="finance.subsidy" data-decision-title="Record subsidy funding?" data-decision-message="Check the amount and evidence before recording this funding entry." data-decision-label="Record funding" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf
                            @include('components.finance-evidence-fields', ['prefix' => 'subsidy-'.$finance->id])<button class="dash-btn-sm" type="submit">Record platform promotion funding</button>
                        </form>
                    @endif
                @endif
                @if($finance->receipt_path && ($actor->isCommerceAdmin() || $actor->isLogisticsAdmin() || $actor->role === 'courier'))<a href="{{ route('finance.evidence', ['remittance', $finance->id]) }}">Cash receipt</a>@endif
                @if($actor->role === 'courier' && in_array($finance->remittance_status, ['COLLECTED', 'DISCREPANCY'], true))
                    <form method="POST" enctype="multipart/form-data" action="{{ route('finance.remittance', $finance->order) }}" data-decision="finance.remittance" data-decision-title="Submit COD remittance?" data-decision-message="Check the recorded amount and supporting evidence before submitting the collected cash for verification." data-decision-label="Submit remittance" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf
                        <label for="remitted-{{ $finance->id }}">Amount handed over</label><input id="remitted-{{ $finance->id }}" name="amount" type="number" min="0" step="0.01" required>
                        @include('components.finance-evidence-fields', ['prefix' => 'remittance-'.$finance->id])
                        <button class="dash-btn-sm" type="submit">Submit cash remittance</button>
                    </form>
                @endif
                @if($actor->isLogisticsAdmin() && $finance->remittance_status === 'SUBMITTED')
                    <form method="POST" action="{{ route('finance.remittance.verify', $finance->order) }}" data-decision="finance.remittance.verify" data-decision-title="Verify COD remittance?" data-decision-message="Verify the amount and evidence against the cash received before confirming." data-decision-label="Verify remittance" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Receipt verification notes<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Verify cash received</button></form>
                @endif
                @if($actor->role === 'seller' && $finance->order->status === 'COMPLETED' && $finance->remittance_status === 'VERIFIED')
                    <p>Seller payable: ₱{{ number_format($finance->seller_payable_cents / 100, 2) }} after commission.</p>
                    <form method="POST" action="{{ route('finance.payout.request', $finance->order) }}" data-decision="finance.payout.request" data-decision-title="Request seller payout?" data-decision-message="Review the order and payout details before submitting the request." data-decision-label="Request payout" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button class="dash-btn-sm" type="submit">Request seller payout</button></form>
                @endif
            </td>
        </tr>@empty<tr><td colspan="5">No financial records available.</td></tr>@endforelse
        </tbody></table></div>{{ $finances->links() }}
    </section>
    <section class="dash-panel"><h2>Refunds</h2><p>Authorization records a decision. Execution records an external refund supported by a transaction reference and proof.</p>
        @forelse($refunds as $refund)<article><h3>{{ $refund->order->order_number }} — ₱{{ number_format($refund->amount_cents / 100, 2) }}</h3><p>{{ $refund->status }} {{ $refund->reference }}</p>
            @if($refund->evidence_path && ($actor->isCommerceAdmin() || $actor->role === 'buyer'))<a href="{{ route('finance.evidence', ['refund', $refund->id]) }}">Refund proof</a>@endif
            @if($actor->isCommerceAdmin() && $refund->status === 'AUTHORIZED')<form method="POST" enctype="multipart/form-data" action="{{ route('finance.refund.execute', $refund) }}" data-decision="finance.refund.execute" data-decision-title="Record refund execution?" data-decision-message="Verify the recipient, amount and proof of payment before recording this refund." data-decision-label="Record refund" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf
                @include('components.finance-evidence-fields', ['prefix' => 'refund-'.$refund->id])<button class="dash-btn-sm" type="submit">Record external refund</button>
            </form>@endif
        </article>@empty<p>No refund records.</p>@endforelse{{ $refunds->links() }}
    </section>
    <section class="dash-panel"><h2>Seller payouts</h2>
        @forelse($payouts as $payout)<article><h3>{{ $payout->order->order_number }} — ₱{{ number_format($payout->amount_cents / 100, 2) }}</h3><p>{{ $payout->status }} {{ $payout->reference }}</p>
            @if($payout->evidence_path)<a href="{{ route('finance.evidence', ['payout', $payout->id]) }}">Settlement proof</a>@endif
            @if($actor->isCommerceAdmin() && $payout->status === 'REQUESTED')<form method="POST" action="{{ route('finance.payout.approve', $payout) }}" data-decision="finance.payout.approve" data-decision-title="Approve seller payout?" data-decision-message="Review the payout eligibility and amount before approving." data-decision-label="Approve payout" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Approval reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Approve payout</button></form>@endif
            @if($actor->isCommerceAdmin() && $payout->status === 'APPROVED')<form method="POST" enctype="multipart/form-data" action="{{ route('finance.payout.settle', $payout) }}" data-decision="finance.payout.settle" data-decision-title="Record payout settlement?" data-decision-message="Verify the seller payment and supporting evidence before recording settlement." data-decision-label="Record settlement" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf
                @include('components.finance-evidence-fields', ['prefix' => 'payout-'.$payout->id])<button class="dash-btn-sm" type="submit">Record external payout settlement</button>
            </form>@endif
        </article>@empty<p>No payout records.</p>@endforelse{{ $payouts->links() }}
    </section>
</main>
@endsection
