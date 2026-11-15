@extends('layouts.logistics')

@section('workspace-primary-route', 'logistics.intake')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Operations overview</h1>
            <p>Sorting hub · Updated {{ $updatedAt->format('d M Y H:i:s T') }} · Refresh to retrieve current hub records.</p>
        </div>
        <div class="courier-actions workspace-page-actions">
            <a class="ops-btn ops-btn--primary" href="{{ route('logistics.intake') }}">Receive parcel</a>
        </div>
    </header>

    @if ($exceptions->total() > 0)
        <div class="logistics-triage-banner" role="alert">
            <div>
                <strong>Action Required: {{ $exceptions->total() }} Rider Suspension {{ Str::plural('Exception', $exceptions->count()) }}</strong>
                <p>Parcels previously assigned to suspended couriers require physical hub recovery or route reassignment.</p>
            </div>
            <a class="ops-btn ops-btn--danger" href="#logistics-exceptions-title">Review exceptions</a>
        </div>
    @endif

    <section class="ops-stats ops-stats--priority" aria-label="Priority parcel queues">
        <a href="{{ route('logistics.tracking', ['queue' => 'inbound']) }}" class="ops-stat ops-stat--action ops-stat--warning">
            <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="delivery1" /></span>
            <span>Pickup arrivals</span>
            <strong>{{ $stats['inbound'] }}</strong>
            <small class="workspace-stat-detail">Inbound to your sorting center</small>
        </a>
        <a href="{{ route('logistics.tracking', ['queue' => 'at_center']) }}" class="ops-stat ops-stat--action">
            <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="package1" /></span>
            <span>Awaiting sort</span>
            <strong>{{ $stats['at_center'] }}</strong>
            <small class="workspace-stat-detail">Received and waiting to sort</small>
        </a>
        <a href="{{ route('logistics.tracking', ['queue' => 'sorted']) }}" class="ops-stat ops-stat--action">
            <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="checklist" /></span>
            <span>Ready for dispatch</span>
            <strong>{{ $stats['sorted'] }}</strong>
            <small class="workspace-stat-detail">Parcels ready for a rider</small>
        </a>
        <a href="{{ route('logistics.tracking', ['queue' => 'failed']) }}" class="ops-stat ops-stat--action ops-stat--danger">
            <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="warning" /></span>
            <span>Failed deliveries</span>
            <strong>{{ $stats['failed'] }}</strong>
            <small class="workspace-stat-detail">Attempts requiring review</small>
        </a>
    </section>

    <x-workspace-tabs :default-tab="$exceptions->total() ? 'hub-exceptions' : ($queues['inbound']->total() ? 'hub-inbound' : ($queues['sorting']->total() ? 'hub-sorting' : ($queues['dispatch']->total() ? 'hub-dispatch' : ($queues['failed']->total() ? 'hub-failed' : 'hub-lifecycle'))))" :tabs="[
        ['id' => 'hub-inbound', 'label' => 'Arrivals', 'count' => $queues['inbound']->total()],
        ['id' => 'hub-sorting', 'label' => 'Sorting', 'count' => $queues['sorting']->total()],
        ['id' => 'hub-dispatch', 'label' => 'Dispatch', 'count' => $queues['dispatch']->total()],
        ['id' => 'hub-failed', 'label' => 'Failed deliveries', 'count' => $queues['failed']->total()],
        ['id' => 'hub-exceptions', 'label' => 'Exceptions', 'count' => $exceptions->total()],
        ['id' => 'hub-lifecycle', 'label' => 'Lifecycle'],
    ]" />
    <section id="hub-exceptions" class="ops-panel {{ $exceptions->total() ? 'ops-panel--urgent' : '' }}"
        aria-labelledby="logistics-exceptions-title">
        <h2 id="logistics-exceptions-title">
            <span>Rider suspension exceptions</span>
            <span class="ops-status {{ $exceptions->total() ? 'ops-status--danger' : 'ops-status--green' }}">
                {{ $exceptions->total() }} open
            </span>
        </h2>
        @if ($exceptions->total() === 0)
            <div class="ops-empty">
                <strong>Everything is clear</strong>
                <p>No active parcels are waiting on suspended rider recovery.</p>
            </div>
        @elseif ($exceptions->isEmpty())
            <div class="ops-empty"><p>No exceptions on this page. Use the page numbers below to return to an open case.</p></div>
        @else
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Parcel</th>
                            <th>Former rider</th>
                            <th>Current stage</th>
                            <th>Reason</th>
                            <th>Next action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exceptions as $exception)
                            <tr>
                                <td class="ops-mono" data-label="Parcel">{{ $exception->order_number }}</td>
                                <td data-label="Former rider">{{ $exception->rider_first_name }} {{ $exception->rider_last_name }}</td>
                                <td data-label="Current stage">
                                    <span class="ops-status ops-status--danger">
                                        {{ str_replace('_', ' ', $exception->order_status) }}
                                    </span>
                                </td>
                                <td data-label="Reason">{{ $exception->reason }}</td>
                                <td data-label="Action">
                                    @if ($exception->type === 'SUSPENDED_RIDER_PICKUP_ACCEPTED')
                                        <a class="ops-btn ops-btn--primary" href="{{ route('logistics.pickupRequests') }}">Reassign pickup</a>
                                    @elseif ($exception->order_status === 'OUT_FOR_DELIVERY')
                                        <a class="ops-btn" href="{{ route('logistics.dispatch') }}">Scan recovered parcel</a>
                                    @elseif ($exception->order_status === 'ASSIGNED_TO_RIDER' && $exception->hub_released_at)
                                        <form method="POST"
                                            action="{{ route('logistics.orders.recoverReleasedParcel', $exception->order_id) }}" data-decision="logistics.orders.recoverReleasedParcel" data-decision-title="Confirm parcel recovery?" data-decision-message="Continue only after logistics physically received the parcel back from the assigned rider." data-decision-label="Confirm recovery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            @csrf
                                            <button class="ops-btn ops-btn--primary" type="submit">Confirm physical hub recovery</button>
                                        </form>
                                    @else
                                        <span class="ops-muted">Physical recovery or dispatch review required</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        {{ $exceptions->links() }}
    </section>

    <section class="ops-grid" aria-label="Operational queues">
        @foreach ([
            'inbound' => ['Inbound pickup arrivals', 'logistics.intake', 'Verify arrival'],
            'sorting' => ['Waiting to be sorted', 'logistics.sorting', 'Sort parcels'],
            'dispatch' => ['Ready for rider assignment', 'logistics.dispatch', 'Assign rider'],
            'failed' => ['Failed deliveries to review', 'logistics.tracking', 'Review tracking'],
        ] as $key => [$title, $route, $actionLabel])
            <section class="ops-panel" id="hub-{{ $key }}">
                <h2>
                    <span>{{ $title }}</span>
                    <a class="ops-btn" href="{{ route($route, $key === 'failed' ? ['queue' => 'failed'] : []) }}">{{ $actionLabel }}</a>
                </h2>
                @if ($queues[$key]->isEmpty())
                    <div class="ops-empty">
                        @if ($queues[$key]->total() > 0)
                            <p>No parcels on this page. This queue has {{ number_format($queues[$key]->total()) }} {{ \Illuminate\Support\Str::plural('parcel', $queues[$key]->total()) }}.</p>
                            <a class="ops-btn" href="{{ $queues[$key]->url(1) }}">Return to first page</a>
                        @else
                            <p>No parcels currently waiting in this queue.</p>
                        @endif
                    </div>
                @else
                    <div class="ops-table-wrap">
                        <table class="ops-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Destination</th>
                                    <th>Current stage</th>
                                    <th>Last updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($queues[$key] as $order)
                                    <tr>
                                        <td class="ops-mono" data-label="Order">{{ $order->order_number }}</td>
                                        <td data-label="Destination">{{ $order->municipality }}, {{ $order->province }}</td>
                                        <td data-label="Current stage">
                                            <span
                                                class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : 'ops-status--pink' }}">
                                                {{ str_replace('_', ' ', $order->operational_status) }}
                                            </span>
                                        </td>
                                        <td class="ops-muted" data-label="Last updated">{{ $order->updated_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                {{ $queues[$key]->links() }}
            </section>
        @endforeach
    </section>

    <section class="ops-panel" id="hub-lifecycle" aria-labelledby="parcel-flow-title">
        <h2 id="parcel-flow-title">Parcel lifecycle pipeline</h2>
        <div class="ops-flow">
            <div class="ops-flow-step">
                <strong>1. Seller pickup</strong>
                <span>{{ $stats['inbound'] }} inbound</span>
            </div>
            <div class="ops-flow-step">
                <strong>2. Hub arrival</strong>
                <span>{{ $stats['at_center'] }} to sort</span>
            </div>
            <div class="ops-flow-step">
                <strong>3. Area sorting</strong>
                <span>{{ $stats['sorted'] }} staged</span>
            </div>
            <div class="ops-flow-step">
                <strong>4. Dispatch</strong>
                <a href="{{ route('logistics.tracking', ['queue' => 'assigned']) }}">{{ $stats['assigned'] }} assigned</a>
            </div>
            <div class="ops-flow-step">
                <strong>5. In transit</strong>
                <a href="{{ route('logistics.tracking', ['queue' => 'out']) }}">{{ $stats['out'] }} active</a>
            </div>
            <div class="ops-flow-step">
                <strong>6. Delivered today</strong>
                <a href="{{ route('logistics.tracking', ['queue' => 'delivered_today']) }}">{{ $stats['delivered_today'] }} delivered/completed today</a>
            </div>
        </div>
        <ul class="ops-summary" aria-label="Additional hub totals">
            <li>Approved riders <strong>{{ $stats['active_riders'] }}</strong></li>
            <li>Available candidates without active final deliveries <strong>{{ $stats['available_riders'] }}</strong></li>
            <li><a href="{{ route('logistics.tracking', ['queue' => 'returned']) }}">Seller returns (in transit or received) <strong>{{ $stats['returned'] }}</strong></a></li>
        </ul>
        <p class="ops-muted">Rider counts describe coverage/account candidates. Dispatch still checks capacity, licence, work limits, availability mode and physical custody.</p>
    </section>
@endsection
