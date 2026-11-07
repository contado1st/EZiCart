@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="container workflow-admin-page">
    <header class="dash-header"><div><h1 class="dash-title">Approved return pickups</h1><p class="dash-subtitle">Approval, rider acceptance, buyer handover and physical collection are separate steps.</p></div></header>
    @include('components.feature-feedback')
    @forelse($orders as $order)
        @php($assignment = $order->returnPickupAssignment)
        <section class="dash-panel">
            <h2>{{ $order->order_number }}</h2>
            <p>{{ $order->seller?->business_name }} · {{ $order->dispute->status }} · {{ str_replace('_', ' ', $order->status) }}</p>
            <x-parcel-weight :order="$order" compact />
            <p><a href="{{ route('parcel-evidence.show', $order) }}">Journey & evidence</a></p>
            @if(in_array($order->status, ['DELIVERED', 'COMPLETED'], true))
                <p>Awaiting collection from buyer {{ $order->recipient_name }} at {{ $order->street_address }}, {{ $order->municipality }}, {{ $order->province }}. Approval has not moved this parcel.</p>
                <p>{{ $assignment ? 'Assigned rider: '.$assignment->rider->first_name.' '.$assignment->rider->last_name : 'Logistics: assign a verified local rider.' }} {{ $assignment?->accepted_at ? 'Pickup accepted.' : 'Awaiting assignment/acceptance.' }}</p>
                @if(($actor->isLogisticsAdmin() || $actor->role === 'sorting_center') && ! $assignment?->accepted_at)
                    <form method="POST" action="{{ route('return-pickups.assign', $order) }}">@csrf<label>Return pickup rider<select name="rider_id" required><option value="">Select an eligible destination-hub rider</option>@foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->first_name }} {{ $rider->last_name }} · {{ $rider->plate_number }}</option>@endforeach</select></label><button class="dash-btn-sm" type="submit">Assign return pickup</button></form>
                @endif
                @if($actor->role === 'courier' && $assignment?->rider_id === $actor->id)
                    @if(! $assignment->accepted_at)
                        <form method="POST" action="{{ route('return-pickups.accept', $order) }}">@csrf<button class="dash-btn-sm" type="submit">Accept return pickup</button></form>
                    @else
                        <form method="POST" action="{{ route('return-pickups.collect', $order) }}">@csrf<label>Scan parcel after buyer handover<input name="parcel_reference" required maxlength="100" placeholder="EZP:"></label><button class="dash-btn-sm" type="submit">Confirm scanned possession</button></form>
                    @endif
                @endif
                @if($actor->role === 'buyer' && $assignment?->accepted_at)
                    <form method="POST" action="{{ route('return-pickups.handover', $order) }}" data-decision="return-handover" data-decision-message="Confirm only when you physically give this parcel to the assigned return rider." onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button class="dash-btn-sm" type="submit">Confirm physical handover to rider</button></form>
                @endif
            @else
                <p>{{ $order->status === 'RETURN_IN_TRANSIT' ? 'Rider collected this return. The seller must confirm the scanned return handoff.' : 'Return physically completed.' }}</p>
            @endif
        </section>
    @empty<p>No approved return pickups in your account scope.</p>@endforelse
    {{ $orders->links() }}
</main>
@endsection
