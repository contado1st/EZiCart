@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Sorting center · {{ now()->format('D, d M Y') }}</div>
            <h1>Operations overview</h1>
            <p>Parcel flow and work that needs attention at this hub.</p>
        </div><a class="ops-btn ops-btn--primary" href="{{ route('logistics.intake') }}">Receive parcel</a>
    </header>
    <section class="ops-stats" aria-label="Current parcel totals">
        <div class="ops-stat"><span>Inbound to hub</span><strong>{{ $stats['inbound'] }}</strong></div>
        <div class="ops-stat"><span>Awaiting sort</span><strong>{{ $stats['at_center'] }}</strong></div>
        <div class="ops-stat"><span>Ready to assign</span><strong>{{ $stats['sorted'] }}</strong></div>
        <div class="ops-stat"><span>Assigned to riders</span><strong>{{ $stats['assigned'] }}</strong></div>
        <div class="ops-stat"><span>Out for delivery</span><strong>{{ $stats['out'] }}</strong></div>
        <div class="ops-stat"><span>Delivered today</span><strong>{{ $stats['delivered_today'] }}</strong></div>
        <div class="ops-stat"><span>Failed delivery</span><strong>{{ $stats['failed'] }}</strong></div>
        <div class="ops-stat"><span>Returned</span><strong>{{ $stats['returned'] }}</strong></div>
        <div class="ops-stat"><span>Approved riders</span><strong>{{ $stats['active_riders'] }}</strong></div>
        <div class="ops-stat"><span>Available riders</span><strong>{{ $stats['available_riders'] }}</strong></div>
    </section>
    <section class="ops-panel">
        <h2>Parcel flow</h2>
        <div class="ops-flow">
            <div class="ops-flow-step"><strong>Seller pickup</strong><span>{{ $stats['inbound'] }} inbound</span></div>
            <div class="ops-flow-step"><strong>Hub arrival</strong><span>{{ $stats['at_center'] }} to receive/sort</span>
            </div>
            <div class="ops-flow-step"><strong>Area sorting</strong><span>{{ $stats['sorted'] }} ready</span></div>
            <div class="ops-flow-step"><strong>Rider assignment</strong><span>{{ $stats['assigned'] }} assigned</span>
            </div>
            <div class="ops-flow-step"><strong>Out for delivery</strong><span>{{ $stats['out'] }} active</span></div>
            <div class="ops-flow-step"><strong>Delivery result</strong><span>{{ $stats['delivered_today'] }} today</span>
            </div>
        </div>
    </section>
    <section class="ops-grid">
        @foreach (['inbound' => ['Inbound pickup arrivals', 'logistics.intake'], 'sorting' => ['Waiting to be sorted', 'logistics.sorting'], 'dispatch' => ['Ready for rider assignment', 'logistics.dispatch'], 'failed' => ['Failed deliveries to review', 'logistics.tracking']] as $key => [$title, $route])
            <div class="ops-panel">
                <h2>{{ $title }} <a class="ops-btn" href="{{ route($route) }}">Open queue</a></h2>
                @if ($queues[$key]->isEmpty())
                    <div class="ops-empty">No parcels in this queue.</div>
                @else
                    <div class="ops-table-wrap">
                        <table class="ops-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Destination</th>
                                    <th>Current stage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($queues[$key] as $order)
                                    <tr>
                                        <td class="ops-mono">{{ $order->order_number }}</td>
                                        <td>{{ $order->municipality }}, {{ $order->province }}</td>
                                        <td><span class="ops-status">{{ str_replace('_', ' ', $order->status) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </section>
    <section class="ops-panel">
        <h2>Rider suspension exceptions <span class="ops-muted">{{ $exceptions->count() }} open</span></h2>
        @if ($exceptions->isEmpty())
            <div class="ops-empty">No active parcels are waiting on suspended rider recovery.</div>
        @else
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead><tr><th>Parcel</th><th>Former rider</th><th>Current stage</th><th>Reason</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach ($exceptions as $exception)
                            <tr>
                                <td class="ops-mono">{{ $exception->order_number }}</td>
                                <td>{{ $exception->rider_first_name }} {{ $exception->rider_last_name }}</td>
                                <td><span class="ops-status">{{ str_replace('_', ' ', $exception->order_status) }}</span></td>
                                <td>{{ $exception->reason }}</td>
                                <td>
                                    @if ($exception->type === 'SUSPENDED_RIDER_PICKUP_ACCEPTED')
                                        <a class="ops-btn" href="{{ route('logistics.pickupRequests') }}">Reassign pickup</a>
                                    @elseif ($exception->order_status === 'OUT_FOR_DELIVERY')
                                        <a class="ops-btn" href="{{ route('logistics.dispatch') }}">Scan recovered parcel</a>
                                    @elseif ($exception->order_status === 'ASSIGNED_TO_RIDER' && $exception->hub_released_at)
                                        <form method="POST" action="{{ route('logistics.orders.recoverReleasedParcel', $exception->order_id) }}">
                                            @csrf<button class="ops-btn ops-btn--primary">Confirm physical hub recovery</button>
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
@endsection
