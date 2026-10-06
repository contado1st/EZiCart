@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Operations overview</h1>
            <p>Sorting hub · {{ now()->format('D, d M Y') }} · Real-time operational queues and hub throughput.</p>
        </div>
        <div class="courier-actions">
            <a class="ops-btn ops-btn--primary" href="{{ route('logistics.intake') }}">Receive parcel</a>
        </div>
    </header>

    @if ($exceptions->isNotEmpty())
        <div class="logistics-triage-banner" role="alert">
            <div>
                <strong>Action Required: {{ $exceptions->count() }} Rider Suspension {{ Str::plural('Exception', $exceptions->count()) }}</strong>
                <p>Parcels previously assigned to suspended couriers require physical hub recovery or route reassignment.</p>
            </div>
            <a class="ops-btn ops-btn--danger" href="#logistics-exceptions-title">Review exceptions</a>
        </div>
    @endif

    <section class="ops-stats ops-stats--priority" aria-label="Priority parcel queues">
        <div class="ops-stat ops-stat--action ops-stat--warning">
            <span>Pickup arrivals</span>
            <strong>{{ $stats['inbound'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Awaiting sort</span>
            <strong>{{ $stats['at_center'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Ready for dispatch</span>
            <strong>{{ $stats['sorted'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--action ops-stat--danger">
            <span>Failed deliveries</span>
            <strong>{{ $stats['failed'] }}</strong>
        </div>
    </section>

    <section class="ops-panel {{ $exceptions->isNotEmpty() ? 'ops-panel--urgent' : '' }}"
        aria-labelledby="logistics-exceptions-title">
        <h2 id="logistics-exceptions-title">
            <span>Rider suspension exceptions</span>
            <span class="ops-status {{ $exceptions->isNotEmpty() ? 'ops-status--danger' : 'ops-status--green' }}">
                {{ $exceptions->count() }} open
            </span>
        </h2>
        @if ($exceptions->isEmpty())
            <div class="ops-empty">
                <strong>Everything is clear</strong>
                <p>No active parcels are waiting on suspended rider recovery.</p>
            </div>
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
                                <td class="ops-mono">{{ $exception->order_number }}</td>
                                <td>{{ $exception->rider_first_name }} {{ $exception->rider_last_name }}</td>
                                <td>
                                    <span class="ops-status ops-status--danger">
                                        {{ str_replace('_', ' ', $exception->order_status) }}
                                    </span>
                                </td>
                                <td>{{ $exception->reason }}</td>
                                <td>
                                    @if ($exception->type === 'SUSPENDED_RIDER_PICKUP_ACCEPTED')
                                        <a class="ops-btn ops-btn--primary" href="{{ route('logistics.pickupRequests') }}">Reassign pickup</a>
                                    @elseif ($exception->order_status === 'OUT_FOR_DELIVERY')
                                        <a class="ops-btn" href="{{ route('logistics.dispatch') }}">Scan recovered parcel</a>
                                    @elseif ($exception->order_status === 'ASSIGNED_TO_RIDER' && $exception->hub_released_at)
                                        <form method="POST"
                                            action="{{ route('logistics.orders.recoverReleasedParcel', $exception->order_id) }}">
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
    </section>

    <section class="ops-grid" aria-label="Operational queues">
        @foreach ([
            'inbound' => ['Inbound pickup arrivals', 'logistics.intake', 'Verify arrival'],
            'sorting' => ['Waiting to be sorted', 'logistics.sorting', 'Sort parcels'],
            'dispatch' => ['Ready for rider assignment', 'logistics.dispatch', 'Assign rider'],
            'failed' => ['Failed deliveries to review', 'logistics.tracking', 'Review tracking'],
        ] as $key => [$title, $route, $actionLabel])
            <section class="ops-panel">
                <h2>
                    <span>{{ $title }}</span>
                    <a class="ops-btn" href="{{ route($route) }}">{{ $actionLabel }}</a>
                </h2>
                @if ($queues[$key]->isEmpty())
                    <div class="ops-empty">
                        <p>No parcels currently waiting in this queue.</p>
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
                                        <td class="ops-mono">{{ $order->order_number }}</td>
                                        <td>{{ $order->municipality }}, {{ $order->province }}</td>
                                        <td>
                                            <span
                                                class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : 'ops-status--pink' }}">
                                                {{ str_replace('_', ' ', $order->status) }}
                                            </span>
                                        </td>
                                        <td class="ops-muted">{{ $order->updated_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </section>

    <section class="ops-panel" aria-labelledby="parcel-flow-title">
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
                <span>{{ $stats['assigned'] }} assigned</span>
            </div>
            <div class="ops-flow-step">
                <strong>5. In transit</strong>
                <span>{{ $stats['out'] }} active</span>
            </div>
            <div class="ops-flow-step">
                <strong>6. Delivered today</strong>
                <span>{{ $stats['delivered_today'] }} completed</span>
            </div>
        </div>
        <ul class="ops-summary" aria-label="Additional hub totals">
            <li>Approved riders <strong>{{ $stats['active_riders'] }}</strong></li>
            <li>Available riders <strong>{{ $stats['available_riders'] }}</strong></li>
            <li>Returns in transit <strong>{{ $stats['returned'] }}</strong></li>
        </ul>
    </section>
@endsection
