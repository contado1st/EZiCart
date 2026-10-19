@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="container workflow-admin-page">
    <header class="dash-header"><div><h1 class="dash-title">Logistics control tower</h1><p class="dash-subtitle">Cross-hub parcel oversight, service areas, account reviews, and recovery coordination.</p></div><a href="{{ route('finance.index') }}" class="dash-btn-sm">COD and remittances</a></header>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <p><a href="{{ route('parcel-transfers.index') }}">Review planned and active hub transfers</a></p>
    <section class="dash-panel"><h2>Aging and exceptions</h2><p>{{ $aging }} parcels have had no operational update for {{ config('commerce.parcel_alert_hours') }} hours.</p>
        @forelse($exceptions as $exception)<p>Order #{{ $exception->order_id }}: {{ $exception->type ?? 'Recovery exception' }} — {{ $exception->reason ?? 'Review parcel custody before reassignment.' }}</p>@empty<p>No open recovery exceptions.</p>@endforelse
    </section>
    <section class="dash-panel"><h2>Logistics applications</h2>
        @forelse($pendingUsers as $applicant)
            <article><h3>{{ $applicant->business_name ?? trim($applicant->first_name.' '.$applicant->last_name) }} ({{ $applicant->role }})</h3>
                <a href="{{ route('logistics-admin.users.documents', [$applicant, 'identity']) }}">Identity document</a>
                @if($applicant->role === 'sorting_center')<a href="{{ route('logistics-admin.users.documents', [$applicant, 'permit']) }}">Business permit</a>@endif
                <form method="POST" action="{{ route('logistics-admin.users.approve', $applicant) }}">@csrf<button class="dash-btn-sm" type="submit">Approve reviewed application</button></form>
                <form method="POST" action="{{ route('logistics-admin.users.reject', $applicant) }}">@csrf<label>Rejection reason<input name="reason" required minlength="5" maxlength="1000"></label><button class="dash-btn-sm" type="submit">Reject</button></form>
            </article>
        @empty<p>No pending logistics applications.</p>@endforelse
    </section>
    <section class="dash-panel"><h2>Service-area ownership</h2>
        @foreach($areas as $area)
            <form method="POST" action="{{ route('logistics-admin.areas.assign', $area) }}">@csrf @method('PATCH')
                <label>{{ $area->name }}<select name="sorting_center_id" required>@foreach($centers as $center)<option value="{{ $center->id }}" @selected($area->sorting_center_id === $center->id)>{{ $center->business_name ?? $center->first_name }}</option>@endforeach</select></label>
                <label>Reason<input name="reason" required minlength="5" maxlength="500"></label><button type="submit" class="dash-btn-sm">Assign hub</button>
            </form>
        @endforeach
        {{ $areas->links() }}
    </section>
    <section class="dash-panel"><h2>Sorting centers and riders</h2>
        @foreach($managedUsers as $managed)<article><h3>{{ $managed->business_name ?? trim($managed->first_name.' '.$managed->last_name) }} — {{ $managed->role }}</h3><p>{{ $managed->status }}</p>
            @if($managed->role === 'courier' && $managed->active_parcels_count > 0)<p>Suspending this rider will open {{ $managed->active_parcels_count }} logistics recovery {{ \Illuminate\Support\Str::plural('case', $managed->active_parcels_count) }}.</p>@endif
            @if($managed->status === 'approved')<form method="POST" action="{{ route('logistics-admin.users.suspend', $managed) }}">@csrf<label>Suspension reason<textarea name="suspension_reason" maxlength="500" required></textarea></label><button type="submit" class="dash-btn-sm">Suspend account</button></form>
            @else<form method="POST" action="{{ route('logistics-admin.users.reactivate', $managed) }}">@csrf<button type="submit" class="dash-btn-sm">Restore prior account status</button></form>@endif
        </article>@endforeach{{ $managedUsers->links() }}
    </section>
    <section class="dash-panel"><h2>Parcels across hubs</h2>
        <form method="GET"><label for="parcel-search">Order number</label><input id="parcel-search" name="search" maxlength="100" value="{{ request('search') }}"><button type="submit" class="dash-btn-sm">Search</button></form>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Parcel</th><th>Status</th><th>Hub</th><th>Coordination</th></tr></thead><tbody>
        @forelse($orders as $order)<tr><td>{{ $order->order_number }}</td><td>{{ str_replace('_', ' ', $order->status) }}</td><td>{{ $order->sortingCenter->business_name ?? 'Awaiting intake' }}</td><td>
            <a href="{{ route('logistics-admin.orders.messages.show', $order) }}">Messages</a>
            @if(in_array($order->status, ['AT_SORTING_CENTER', 'SORTED'], true) && ! $order->activeTransfer)
                <form method="POST" action="{{ route('logistics-admin.orders.transfer', $order) }}">@csrf
                    <label>Destination hub<select name="destination_hub_id" required>@foreach($centers as $center)<option value="{{ $center->id }}">{{ $center->business_name ?? $center->first_name }}</option>@endforeach</select></label>
                    <label>Transfer reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Plan physical transfer</button>
                </form>
            @elseif($order->activeTransfer)<p>Transfer: {{ $order->activeTransfer->status }}</p>@endif
            @if($order->status === 'READY_FOR_PICKUP' && $order->pickup_arrived_at === null && $order->seller_handover_at === null)
                <form method="POST" action="{{ route('logistics-admin.orders.pickup', $order) }}">@csrf
                    <label>Pickup rider<select name="pickup_courier_id" required>@foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->first_name }} {{ $rider->last_name }}</option>@endforeach</select></label>
                    <label>Reason<input name="reason" required minlength="5" maxlength="500"></label><button class="dash-btn-sm" type="submit">Reassign before handover</button>
                </form>
            @endif
        </td></tr>@empty<tr><td colspan="4">No parcels match.</td></tr>@endforelse
        </tbody></table></div>{{ $orders->links() }}
    </section>
</main>
@endsection
