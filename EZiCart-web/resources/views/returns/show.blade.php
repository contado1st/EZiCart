@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="web-operations">
    @include('components.feature-feedback')
    <header class="dash-header"><div><h1 class="dash-title">Return workflow · {{ $order->order_number }}</h1><p class="dash-subtitle">Receipt, inventory inspection and payment review are separate recorded actions.</p></div>
        <a class="dash-btn-sm" href="{{ $actor->role === 'seller' ? route('seller.orders.show', $order) : ($actor->role === 'buyer' ? route('buyer.orders.show', $order) : route('finance.index')) }}">Back to order records</a>
    </header>
    <section class="dash-panel"><h2>Recorded progress</h2><dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt>Courier handover</dt><dd>{{ $order->return_handed_to_seller_at?->format('d M Y H:i') ?? 'Not recorded' }}</dd></div>
        <div><dt>Seller receipt</dt><dd>{{ $summary['receipt_confirmed'] ? 'Receipt evidence recorded' : ($order->status === 'RETURNED_TO_SELLER' ? 'Return status recorded; detailed receipt evidence requires review' : 'Awaiting physical receipt') }}</dd></div>
        <div><dt>Inspection</dt><dd>{{ $order->restock_inspected_at?->format('d M Y H:i') ?? 'Not recorded' }}</dd></div>
        <div><dt>Buyer refund</dt><dd>{{ $summary['refund']?->status ?? 'No authorization recorded' }}</dd></div>
    </dl><p>Current parcel stage: <strong>{{ str_replace('_', ' ', $order->status) }}</strong>. Receipt alone does not restore stock or pay money. Buyer-refund review does not require approval of damaged units for restocking.</p></section>
    <x-workspace-tabs :default-tab="$actor->isCommerceAdmin() ? 'return-refund' : ($actor->isLogisticsAdmin() ? 'return-costs' : ($actor->role === 'seller' && $order->status === 'RETURNED_TO_SELLER' && !$order->restock_inspected_at ? 'return-inspection' : 'return-receipt'))" :tabs="[
        ['id' => 'return-receipt', 'label' => 'Receipt'], ['id' => 'return-inspection', 'label' => 'Inspection'], ['id' => 'return-refund', 'label' => 'Refund'], ['id' => 'return-costs', 'label' => 'Logistics costs'],
    ]" />

    <section class="dash-panel" id="return-receipt"><h2>Confirm physical seller receipt</h2>
        @if($order->status === 'RETURN_IN_TRANSIT' && !$order->return_handed_to_seller_at)<p>Wait for the assigned courier to scan and record the physical seller handover. No receipt or stock change is recorded by opening this page.</p>
        @elseif($order->status === 'RETURN_IN_TRANSIT' && $actor->role === 'seller' && (int) $order->seller_id === $actor->id)
            <p>Receive the parcel, verify the assigned return rider, then scan their badge. Inspect item condition separately after receipt.</p>
            <form class="web-form" method="POST" action="{{ route('seller.orders.confirmReturn', $order) }}" data-decision="seller-return-receipt" data-decision-title="Confirm physical return receipt?" data-decision-message="Confirm only after receiving this parcel from the assigned courier. Stock and refunds are handled separately." data-decision-label="Confirm receipt" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                @csrf<label class="web-wide" for="return-rider-badge">Return rider badge QR/code<input id="return-rider-badge" name="rider_badge" data-qr-input maxlength="100" placeholder="EZR:…" required autocomplete="off"></label><input type="hidden" name="method" value="manual">
                <div class="web-actions"><button class="dash-btn-sm" type="button" data-qr-start>Use camera</button><button class="dash-btn-sm" type="button" data-qr-stop hidden>Stop camera</button><button class="dash-btn-sm dash-btn-primary" type="submit">Confirm scanned return received</button></div><video data-qr-video playsinline hidden></video><p data-qr-status role="status">Camera, handheld scanner or manual badge code entry is supported.</p>
            </form>
        @elseif($summary['receipt_confirmed'])<p>Seller receipt evidence is recorded. Continue to inspection to decide which units are sellable.</p>
        @else<p>The current stage is {{ str_replace('_', ' ', $order->status) }}. Follow its handover/receipt workflow; missing historical evidence requires administrator review.</p>@endif
        <a class="dash-btn-sm" href="#return-inspection">Review inspection</a>
    </section>

    <section class="dash-panel" id="return-inspection"><h2>Inspect returned units</h2><p>Only explicitly approved sellable units re-enter stock. Damaged, missing or unsuitable units stay excluded. This inspection never authorizes a buyer refund or seller payout.</p>
        @if($actor->role === 'seller' && (int) $order->seller_id === $actor->id && $order->status === 'RETURNED_TO_SELLER' && !$order->restock_inspected_at && !$order->inventory_restored_at)
            @if($order->items->isEmpty())<p>Historical item quantities are missing. Ask administration to review the return.</p>@else
            <form class="web-form" method="POST" action="{{ route('seller.orders.return-inspection', $order) }}" data-decision="return-inspection" data-decision-title="Record these inspected quantities?" data-decision-message="Only the sellable quantities you enter will be restored. Other units remain excluded. This recorded inspection cannot be overwritten." data-decision-label="Record inspection" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                @csrf
                @foreach($order->items->chunk(25) as $chunkIndex => $items)
                    <details class="web-wide" @if($loop->first) open @endif><summary>Items {{ $chunkIndex * 25 + 1 }}–{{ $chunkIndex * 25 + $items->count() }} of {{ $order->items->count() }}</summary>
                    <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Purchased item / option</th><th>Expected units</th><th>Sellable units to restock</th></tr></thead><tbody>
                    @foreach($items as $item)<tr><td>{{ $item->product_name }}<div class="web-muted">{{ $item->variation_info }}</div></td><td>{{ $item->quantity }}</td><td><label for="restock-{{ $item->id }}">Sellable quantity</label><input id="restock-{{ $item->id }}" name="quantities[{{ $item->id }}]" type="number" min="0" max="{{ $item->quantity }}" step="1" value="{{ old('quantities.'.$item->id, 0) }}" required>@if(!$item->product_id || !$item->product)<p>Original catalog item unavailable. Leave zero and ask administration to review.</p>@endif</td></tr>@endforeach
                    </tbody></table></div></details>
                @endforeach
                <label class="web-wide" for="inspection-reason">Inspection notes — explain excluded/damaged quantities<textarea id="inspection-reason" name="reason" minlength="5" maxlength="500" required>{{ old('reason') }}</textarea></label><button class="dash-btn-sm dash-btn-primary" type="submit">Record inspection</button>
            </form>@endif
        @elseif($order->restock_inspected_at)
            <p>Inspection recorded {{ $order->restock_inspected_at->format('d M Y H:i') }}. {{ $order->inventory_restored_at ? 'Approved stock processing is recorded.' : 'No stock restoration was recorded; excluded units remain out of stock.' }}</p>
            @if($actor->isCommerceAdmin() || $actor->role === 'seller')
                @if($summary['lines']->isEmpty())<p>Legacy inspection recorded without a per-item breakdown. Quantities were not reconstructed.</p>@else
                @foreach($order->items->chunk(25) as $chunkIndex => $items)
                    <details class="web-wide" @if($loop->first) open @endif><summary>Items {{ $chunkIndex * 25 + 1 }}–{{ $chunkIndex * 25 + $items->count() }} of {{ $order->items->count() }}</summary>
                    <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Item</th><th>Expected</th><th>Restocked</th><th>Excluded</th></tr></thead><tbody>@foreach($items as $item)@php($line = $summary['lines']->get($item->id))<tr><td>{{ $item->product_name }} {{ $item->variation_info }}</td><td>{{ $line?->expected_quantity ?? 'Not recorded' }}</td><td>{{ $line?->restock_quantity ?? 'Not recorded' }}</td><td>{{ $line?->excluded_quantity ?? 'Not recorded' }}</td></tr>@endforeach</tbody></table></div></details>
                @endforeach
                @endif
                @if($summary['inspection_reason'])<p>Inspection notes: {{ $summary['inspection_reason'] }}</p>@endif
            @endif
        @elseif($order->inventory_restored_at)<p>Historical inventory restoration is already recorded. No second restoration is offered.</p>
        @else<p>The seller can inspect only after return receipt is recorded. Other modules cannot submit this assessment.</p>@endif
    </section>

    <section class="dash-panel" id="return-refund"><h2>Buyer refund review</h2><p>{{ $summary['refund_message'] }}</p>
        @if($summary['refund'])<p>Recorded refund: <strong>PHP {{ number_format($summary['refund']->amount_cents / 100, 2) }}</strong> · {{ $summary['refund']->status }}.</p>@endif
        <p>Seller sale proceeds stay subject to the existing return/refund blocks. A parcel status, receipt or inspection never creates spendable money.</p>
        @if($actor->isCommerceAdmin() && $summary['can_authorize_rts'])
            <form class="web-form" method="POST" action="{{ route('returns.authorize-wallet', $order) }}" data-decision="rts-refund-authorization" data-decision-title="Authorize this wallet RTS refund?" data-decision-message="This authorizes review of the full recorded payment after verified seller receipt. It does not send money. Execution remains a separate action." data-decision-label="Authorize refund" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                @csrf<label class="web-wide" for="rts-authorize-reason">Authorization reason<input id="rts-authorize-reason" name="reason" minlength="5" maxlength="500" required></label><button class="dash-btn-sm" type="submit">Authorize RTS wallet refund</button>
            </form>
        @endif
        @if($actor->isCommerceAdmin() && $summary['can_execute_wallet'])
            <form class="web-form" method="POST" action="{{ route('finance.refund.wallet', $summary['refund']) }}" data-decision="execute-return-refund" data-decision-title="Execute the authorized wallet refund?" data-decision-message="This posts money through the existing funded refund service. Confirm the order and full authorized amount before proceeding." data-decision-label="Credit buyer wallet" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                @csrf<label class="web-wide" for="wallet-refund-reason">Execution reason<input id="wallet-refund-reason" name="reason" minlength="5" maxlength="500" required></label><button class="dash-btn-sm dash-btn-primary" type="submit">Credit approved wallet refund</button>
            </form>
        @endif
        @if($actor->isCommerceAdmin() && $order->dispute)<a class="dash-btn-sm" href="{{ route('admin.disputes.show', $order->dispute) }}">Review marketplace dispute</a>@endif
        @if(in_array($actor->role, ['buyer', 'seller'], true))<a class="dash-btn-sm" href="{{ route('finance.wallet') }}">My wallet and statements</a>@endif
    </section>

    <section class="dash-panel" id="return-costs"><h2>Logistics return costs</h2><p>{{ $summary['cost_message'] }}</p><p>Transport compensation and held components follow evidenced work and approved funding. They are separate from seller sale proceeds and the buyer's refund; no rider/hub percentage is inferred here.</p>
        @if($actor->isCommerceAdmin() || $actor->isLogisticsAdmin())
            @if($summary['return_cost'])<p>Return-cost request: {{ $summary['return_cost']->status }} · Funding difference PHP {{ number_format($summary['return_cost']->difference_cents / 100, 2) }}.</p>@endif
            <a class="dash-btn-sm" href="{{ route('finance.shipments') }}">Review shipping funding and evidenced cost actions</a>
        @endif
    </section>
</main>
@endsection
