@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="container workflow-admin-page">
    <header class="dash-header"><div><h1 class="dash-title">Hub transfers</h1><p class="dash-subtitle">A planned movement becomes physical custody only after the source and destination scans.</p></div></header>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    @forelse($transfers as $transfer)<section class="dash-panel">
        <h2>{{ $transfer->order->order_number }}</h2><p>{{ $transfer->sourceHub->business_name ?? $transfer->source_hub_id }} → {{ $transfer->destinationHub->business_name ?? $transfer->destination_hub_id }} — {{ $transfer->status }}</p>
        <p>{{ $transfer->reason }}</p>@if($transfer->transport_reference)<p>Transport manifest: {{ $transfer->transport_reference }}</p>@endif
        @if($actor->role === 'sorting_center' && $actor->id === $transfer->source_hub_id && $transfer->status === 'PLANNED')
            <form method="POST" action="{{ route('parcel-transfers.release', $transfer) }}" data-decision="parcel-transfers.release" data-decision-title="Confirm transfer release?" data-decision-message="Continue only after physically releasing the parcel for the planned hub transfer." data-decision-label="Confirm release" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Parcel label (EZP code)<input name="parcel_reference" required maxlength="100"></label><label>Transport manifest or carrier handover reference<input name="transport_reference" required maxlength="120"></label><button class="dash-btn-sm" type="submit">Scan and release transfer</button></form>
        @endif
        @if($actor->role === 'sorting_center' && $actor->id === $transfer->destination_hub_id && $transfer->status === 'IN_TRANSIT')
            <form method="POST" action="{{ route('parcel-transfers.receive', $transfer) }}" data-decision="parcel-transfers.receive" data-decision-title="Confirm transfer receipt?" data-decision-message="Continue only after the destination hub physically received the transferred parcel." data-decision-label="Confirm receipt" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Parcel label (EZP code)<input name="parcel_reference" required maxlength="100"></label><button class="dash-btn-sm" type="submit">Scan physical receipt</button></form>
        @endif
        @if($actor->isLogisticsAdmin() && $transfer->status === 'PLANNED')
            <form method="POST" action="{{ route('parcel-transfers.cancel', $transfer) }}" data-decision="parcel-transfers.cancel" data-decision-title="Cancel planned transfer?" data-decision-message="Check the parcel and cancellation reason before cancelling this transfer." data-decision-label="Cancel transfer" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Cancellation reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Cancel unreleased transfer</button></form>
        @endif
    </section>@empty<p>No transfers in your workspace.</p>@endforelse{{ $transfers->links() }}
</main>
@endsection
