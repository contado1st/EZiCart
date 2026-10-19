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
            <form method="POST" action="{{ route('parcel-transfers.release', $transfer) }}">@csrf<label>Parcel label (EZP code)<input name="parcel_reference" required maxlength="100"></label><label>Transport manifest or carrier handover reference<input name="transport_reference" required maxlength="120"></label><button class="dash-btn-sm" type="submit">Scan and release transfer</button></form>
        @endif
        @if($actor->role === 'sorting_center' && $actor->id === $transfer->destination_hub_id && $transfer->status === 'IN_TRANSIT')
            <form method="POST" action="{{ route('parcel-transfers.receive', $transfer) }}">@csrf<label>Parcel label (EZP code)<input name="parcel_reference" required maxlength="100"></label><button class="dash-btn-sm" type="submit">Scan physical receipt</button></form>
        @endif
        @if($actor->isLogisticsAdmin() && $transfer->status === 'PLANNED')
            <form method="POST" action="{{ route('parcel-transfers.cancel', $transfer) }}">@csrf<label>Cancellation reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Cancel unreleased transfer</button></form>
        @endif
    </section>@empty<p>No transfers in your workspace.</p>@endforelse{{ $transfers->links() }}
</main>
@endsection
