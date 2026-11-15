@extends('layouts.app')
@section('workspace-primary-route', 'parcel-transfers.index')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="container workflow-admin-page">
    <header class="dash-header">
        <div><h1 class="dash-title">Logistics control tower</h1><p class="dash-subtitle">Cross-hub parcel oversight, service areas, account reviews, and recovery coordination.</p></div>
        <div class="workspace-page-actions">
            <a href="{{ route('parcel-transfers.index') }}" class="dash-btn-primary">Hub transfers</a>
            <a href="{{ route('finance.index') }}" class="dash-btn-sm dash-btn-outline">COD and remittances</a>
        </div>
    </header>
    <section class="workspace-metrics" aria-label="Logistics network summary">
        <x-workspace-metric label="Parcels in view" :value="$orders->total()" icon="package1" detail="Matching the current parcel search" />
        <x-workspace-metric label="Aging parcels" :value="$aging" icon="warning" detail="Awaiting an operational update" tone="warning" />
        <x-workspace-metric label="Recovery exceptions" :value="$exceptions->total()" icon="shield" detail="Open cases awaiting review" tone="danger" />
        <x-workspace-metric label="Approved sorting hubs" :value="$centers->count()" icon="location" detail="Available centers in the network" tone="success" />
    </section>
    <x-feature-feedback />
    <x-workspace-tabs :tabs="[
        ['id' => 'parcels', 'label' => 'Parcels', 'count' => $orders->total()],
        ['id' => 'applications', 'label' => 'Applications', 'count' => $pendingUsers->total()],
        ['id' => 'service-areas', 'label' => 'Service areas', 'count' => $areas->total()],
        ['id' => 'managed-accounts', 'label' => 'Hubs and riders', 'count' => $managedUsers->total()],
        ['id' => 'network-exceptions', 'label' => 'Exceptions', 'count' => $exceptions->total()],
    ]" />
    <section class="dash-panel" id="network-exceptions"><h2>Aging and exceptions</h2><p>{{ $aging }} parcels have had no operational update for {{ config('commerce.parcel_alert_hours') }} hours.</p>
        @forelse($exceptions as $exception)<p>Order #{{ $exception->order_id }}: {{ $exception->type ?? 'Recovery exception' }} — {{ $exception->reason ?? 'Review parcel custody before reassignment.' }}</p>@empty
            @if($exceptions->total() > 0)<p>No recovery exceptions on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $exceptions->url(1) }}">Return to first page</a>
            @else<p>No open recovery exceptions.</p>@endif
        @endforelse
        {{ $exceptions->links() }}
    </section>
    <div class="workspace-admin-grid">
    <section class="dash-panel" id="applications"><h2>Logistics applications <span class="workspace-count">{{ $pendingUsers->total() }} pending matching applications</span></h2>
        <form method="GET" class="workspace-filter-form">
            <label for="application-search">Applicant name, business or email<input id="application-search" name="application_search" maxlength="100" value="{{ request('application_search') }}"></label>
            <button class="dash-btn-sm dash-btn-outline" type="submit">Search applications</button>
        </form>
        @forelse($pendingUsers as $applicant)
            <article class="workspace-review-item"><h3>{{ $applicant->business_name ?? trim($applicant->first_name.' '.$applicant->last_name) }} ({{ \Illuminate\Support\Str::headline($applicant->role) }})</h3>
                <p class="workspace-review-meta">{{ $applicant->email }} · {{ $applicant->contact_no }}</p>
                <p class="workspace-review-meta">Waiting since {{ $applicant->created_at->format('d M Y') }} ({{ $applicant->created_at->diffForHumans() }})</p>
                <div class="workspace-page-actions">
                <a class="dash-btn-sm dash-btn-outline" href="{{ route('logistics-admin.applications.show', $applicant) }}">Review details & documents</a>
                </div>
                <div class="workspace-review-actions">
                <form method="POST" action="{{ route('logistics-admin.users.approve', $applicant) }}" data-decision="logistics-admin.users.approve" data-decision-title="Approve this account?" data-decision-message="Confirm that you reviewed the submitted credentials and that this account meets the approval requirements." data-decision-label="Approve account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button class="dash-btn-sm" type="submit">Approve reviewed application</button></form>
                <form method="POST" action="{{ route('logistics-admin.users.reject', $applicant) }}" data-decision="logistics-admin.users.reject" data-decision-title="Reject this application?" data-decision-message="Check the rejection reason before recording your decision." data-decision-label="Reject application" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Rejection reason<input name="reason" required minlength="5" maxlength="1000"></label><button class="dash-btn-sm" type="submit">Reject</button></form>
                </div>
            </article>
        @empty
            @if($pendingUsers->total() > 0)<p>No applications on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $pendingUsers->url(1) }}">Return to first page</a>
            @else<p>No pending logistics applications match this view.</p>@endif
        @endforelse
        {{ $pendingUsers->links() }}
    </section>
    <section class="dash-panel" id="service-areas"><h2>Service-area ownership</h2>
        @forelse($areas as $area)
            <form class="workspace-row-form" method="POST" action="{{ route('logistics-admin.areas.assign', $area) }}" data-decision="logistics-admin.areas.assign" data-decision-title="Change service-area ownership?" data-decision-message="Check the selected hub and reason. This affects service-area routing." data-decision-label="Assign hub" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf @method('PATCH')
                <label>{{ $area->name }}<select name="sorting_center_id" required>@foreach($centers as $center)<option value="{{ $center->id }}" @selected($area->sorting_center_id === $center->id)>{{ $center->business_name ?? $center->first_name }}</option>@endforeach</select></label>
                <label>Reason<input name="reason" required minlength="5" maxlength="500"></label><button type="submit" class="dash-btn-sm">Assign hub</button>
            </form>
        @empty
            @if($areas->total() > 0)<p>No service areas on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $areas->url(1) }}">Return to first page</a>
            @else<p>No service areas are configured. Add and map service areas before assigning hub ownership.</p>@endif
        @endforelse
        {{ $areas->links() }}
    </section>
    </div>
    <section class="dash-panel" id="managed-accounts"><h2>Sorting centers and riders</h2><div class="workspace-account-grid">
        @forelse($managedUsers as $managed)<article><h3>{{ $managed->business_name ?? trim($managed->first_name.' '.$managed->last_name) }} — {{ $managed->role }}</h3><p>{{ $managed->status }}</p>
            @if($managed->role === 'courier' && $managed->active_parcels_count > 0)<p>Suspending this rider will open {{ $managed->active_parcels_count }} logistics recovery {{ \Illuminate\Support\Str::plural('case', $managed->active_parcels_count) }}.</p>@endif
            @if($managed->status === 'approved')<form method="POST" action="{{ route('logistics-admin.users.suspend', $managed) }}" data-decision="logistics-admin.users.suspend" data-decision-title="Suspend this account?" data-decision-message="This restricts the account access and may affect its active work. Review the reason before continuing." data-decision-label="Suspend account" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Suspension reason<textarea name="suspension_reason" maxlength="500" required></textarea></label><button type="submit" class="dash-btn-sm">Suspend account</button></form>
            @else<form method="POST" action="{{ route('logistics-admin.users.reactivate', $managed) }}" data-decision="logistics-admin.users.reactivate" data-decision-title="Restore this account?" data-decision-message="Check the account review and intended status before restoring access." data-decision-label="Restore account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button type="submit" class="dash-btn-sm">Restore prior account status</button></form>@endif
        </article>@empty
            @if($managedUsers->total() > 0)<p>No managed accounts on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $managedUsers->url(1) }}">Return to first page</a>
            @else<p>No sorting centers or riders are available in this view. Review new applications in the Applications tab.</p>@endif
        @endforelse</div><div class="workspace-pagination">{{ $managedUsers->links() }}</div>
    </section>
    <section class="dash-panel" id="parcels"><h2>Parcels across hubs</h2>
        <form method="GET" class="workspace-filter-form">
            <label for="parcel-search">Order number<input id="parcel-search" name="search" maxlength="100" value="{{ request('search') }}"></label>
            <button type="submit" class="dash-btn-sm dash-btn-outline">Search parcels</button>
        </form>
        <div class="dash-table-wrapper"><table class="dash-table workspace-parcel-table" role="table" aria-label="Parcels across hubs"><thead role="rowgroup"><tr role="row"><th scope="col">Parcel</th><th scope="col">Status</th><th scope="col">Hub</th><th scope="col">Coordination</th></tr></thead><tbody role="rowgroup">
        @forelse($orders as $order)<tr role="row"><td role="cell" data-label="Parcel">{{ $order->order_number }}<x-parcel-weight :order="$order" compact /></td><td role="cell" data-label="Status">{{ str_replace('_', ' ', $order->operational_status) }}</td><td role="cell" data-label="Hub">{{ $order->sortingCenter->business_name ?? 'Awaiting intake' }}</td><td role="cell" data-label="Coordination">
            <a href="{{ route('logistics-admin.orders.messages.show', $order) }}">Messages</a>
            @if(in_array($order->status, ['AT_SORTING_CENTER', 'SORTED'], true) && ! $order->activeTransfer)
                <details class="workspace-table-disclosure" @if($errors->any()) open @endif>
                <summary>Plan hub transfer<span class="sr-only"> for {{ $order->order_number }}</span></summary>
                <form method="POST" action="{{ route('logistics-admin.orders.transfer', $order) }}" data-decision="logistics-admin.orders.transfer" data-decision-title="Plan hub transfer?" data-decision-message="Verify the destination hub and reason before creating a physical parcel transfer." data-decision-label="Plan transfer" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf
                    <label>Destination hub<select name="destination_hub_id" required>@foreach($centers as $center)<option value="{{ $center->id }}">{{ $center->business_name ?? $center->first_name }}</option>@endforeach</select></label>
                    <label>Transfer reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm dash-btn-outline" type="submit">Plan physical transfer</button>
                </form>
                </details>
            @elseif($order->activeTransfer)<p>Transfer: {{ $order->activeTransfer->status }}</p>@endif
            @if($order->status === 'READY_FOR_PICKUP' && $order->pickup_arrived_at === null && $order->seller_handover_at === null)
                <details class="workspace-table-disclosure" @if($errors->any()) open @endif>
                <summary>Reassign pickup<span class="sr-only"> for {{ $order->order_number }}</span></summary>
                <form method="POST" action="{{ route('logistics-admin.orders.pickup', $order) }}" data-decision="logistics-admin.orders.pickup" data-decision-title="Reassign pickup?" data-decision-message="Check the selected rider and reason before changing the pickup assignment." data-decision-label="Reassign pickup" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf
                    <label>Pickup rider<select name="pickup_courier_id" required>@foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->first_name }} {{ $rider->last_name }}</option>@endforeach</select></label>
                    <label>Reason<input name="reason" required minlength="5" maxlength="500"></label><button class="dash-btn-sm dash-btn-outline" type="submit">Reassign before handover</button>
                </form>
                </details>
            @endif
        </td></tr>@empty<tr role="row"><td role="cell" colspan="4">
            @if($orders->total() > 0)<p>No parcels on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $orders->url(1) }}">Return to first page</a>
            @else<p>No parcels match this search. Check the order number or clear the search.</p>@endif
        </td></tr>@endforelse
        </tbody></table></div>{{ $orders->links() }}
    </section>
</main>
@endsection
