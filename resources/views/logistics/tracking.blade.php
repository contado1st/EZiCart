@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Delivery tracking</h1>
            <p>Current stage, rider ownership, milestone progression, and persisted parcel events.</p>
        </div>
    </header>

    <section class="ops-panel">
        <form class="ops-form" method="GET" action="{{ route('logistics.tracking') }}" aria-label="Filter parcel tracking">
            <div class="ops-field">
                <label for="tracking-order-search">Order number</label>
                <input id="tracking-order-search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="e.g. ORD-10293">
            </div>
            <div class="ops-field">
                <label for="tracking-status">Status</label>
                <select id="tracking-status" name="status">
                    <option value="">All active</option>
                    @foreach (['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ str_replace('_', ' ', $status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="ops-field">
                <label for="tracking-rider">Rider</label>
                <select id="tracking-rider" name="rider">
                    <option value="">All riders</option>
                    @foreach ($riders as $rider)
                        <option value="{{ $rider->id }}" @selected((string) request('rider') === (string) $rider->id)>
                            {{ $rider->first_name }} {{ $rider->last_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="ops-field">
                <label for="tracking-rider-search">Search rider list</label>
                <input id="tracking-rider-search" name="rider_search" value="{{ request('rider_search') }}" maxlength="100" placeholder="First or last name">
                <span class="ops-muted">Up to 50 hub riders. Search to find others.</span>
            </div>
            <div class="ops-field">
                <label for="tracking-area">Area</label>
                <select id="tracking-area" name="area">
                    <option value="">All areas</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}" @selected((string) request('area') === (string) $area->id)>
                            {{ $area->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="ops-field">
                <label for="tracking-date">Updated date</label>
                <input id="tracking-date" type="date" name="date" value="{{ request('date') }}">
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Apply filters</button>
            @if (request()->filled('search') || request()->filled('status') || request()->filled('rider') || request()->filled('rider_search') || request()->filled('area') || request()->filled('date'))
                <a class="ops-btn" href="{{ route('logistics.tracking') }}">Clear filters</a>
            @endif
        </form>

        @if (request()->filled('search') || request()->filled('status') || request()->filled('rider') || request()->filled('rider_search') || request()->filled('area') || request()->filled('date'))
            <ul class="ops-filter-chips" aria-label="Active filters">
                @if (request()->filled('search'))
                    <li>Order: <strong>{{ request('search') }}</strong></li>
                @endif
                @if (request()->filled('status'))
                    <li>Status: <strong>{{ str_replace('_', ' ', request('status')) }}</strong></li>
                @endif
                @if (request()->filled('rider') && ($selectedRider = $riders->firstWhere('id', request('rider'))))
                    <li>Rider: <strong>{{ $selectedRider->first_name }} {{ $selectedRider->last_name }}</strong></li>
                @endif
                @if (request()->filled('rider_search'))
                    <li>Rider search: <strong>{{ request('rider_search') }}</strong></li>
                @endif
                @if (request()->filled('area') && ($selectedArea = $areas->firstWhere('id', request('area'))))
                    <li>Area: <strong>{{ $selectedArea->name }}</strong></li>
                @endif
                @if (request()->filled('date'))
                    <li>Date: <strong>{{ request('date') }}</strong></li>
                @endif
            </ul>
        @endif
    </section>

    @foreach ($orders as $order)
        <section class="ops-panel {{ $order->status === 'DELIVERY_FAILED' ? 'ops-panel--urgent' : '' }}">
            <div class="ops-heading">
                <div>
                    <p class="ops-mono">Order {{ $order->order_number }}</p>
                    <h2>{{ $order->recipient_name }} · {{ $order->municipality }}</h2>
                    <p>
                        Rider: <strong>{{ $order->deliveryCourier ? $order->deliveryCourier->first_name . ' ' . $order->deliveryCourier->last_name : 'No delivery rider assigned' }}</strong>
                        · Updated {{ $order->updated_at->diffForHumans() }}
                    </p>
                </div>
                <div class="courier-actions">
                    <span class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : ($order->status === 'RETURN_IN_TRANSIT' ? 'ops-status--amber' : ($order->status === 'DELIVERED' || $order->status === 'COMPLETED' ? 'ops-status--green' : 'ops-status--pink')) }}">
                        {{ str_replace('_', ' ', $order->status) }}
                    </span>
                    @if ($order->status === 'DELIVERY_FAILED')
                        <form method="POST" action="{{ route('logistics.orders.return', $order) }}" onsubmit="return confirm('Move this parcel to return handling?')">
                            @csrf
                            <button class="ops-btn ops-btn--danger" type="submit">Return to sender</button>
                        </form>
                    @endif
                    @if ($order->messageParticipants()->contains('id', auth()->id()))
                        <a class="ops-btn" href="{{ route('logistics.orders.messages.show', $order) }}">Order messages</a>
                    @endif
                </div>
            </div>

            @if ($order->status === 'RETURN_IN_TRANSIT')
                <div class="ops-banner ops-banner--urgent">
                    <span>{{ $order->return_handed_to_seller_at ? 'Seller receipt confirmation pending' : 'Awaiting courier to record seller handoff' }}</span>
                </div>
            @endif

            {{-- Milestone Progress Stepper --}}
            @php
                $stages = [
                    'PICKED_UP' => 'Picked up',
                    'AT_SORTING_CENTER' => 'At hub',
                    'SORTED' => 'Sorted',
                    'OUT_FOR_DELIVERY' => 'Out for delivery',
                    'DELIVERED' => 'Delivered',
                ];
                $orderStatus = $order->status;
                $isFailed = $orderStatus === 'DELIVERY_FAILED';
                $isReturn = in_array($orderStatus, ['RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true);
                $statusOrder = ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'COMPLETED'];
                $currentIndex = array_search($orderStatus, $statusOrder, true);
                if ($currentIndex === false) {
                    $currentIndex = $isFailed ? 4 : ($isReturn ? 4 : 0);
                }
            @endphp
            <div class="ops-stepper" aria-label="Parcel milestone progression">
                <div class="ops-step {{ $currentIndex >= 0 ? 'is-done' : '' }}">
                    <span class="ops-step__indicator">1</span>
                    <span class="ops-step__label">Picked up</span>
                </div>
                <div class="ops-step {{ $currentIndex >= 1 ? 'is-done' : ($currentIndex === 0 ? 'is-active' : '') }}">
                    <span class="ops-step__indicator">2</span>
                    <span class="ops-step__label">At Hub</span>
                </div>
                <div class="ops-step {{ $currentIndex >= 2 ? 'is-done' : ($currentIndex === 1 ? 'is-active' : '') }}">
                    <span class="ops-step__indicator">3</span>
                    <span class="ops-step__label">Sorted</span>
                </div>
                <div class="ops-step {{ $isFailed ? 'is-failed' : ($currentIndex >= 4 ? 'is-done' : ($currentIndex >= 3 ? 'is-active' : '')) }}">
                    <span class="ops-step__indicator">{{ $isFailed ? '!' : '4' }}</span>
                    <span class="ops-step__label">{{ $isFailed ? 'Failed' : ($isReturn ? 'Returning' : 'Delivery') }}</span>
                </div>
                <div class="ops-step {{ in_array($orderStatus, ['DELIVERED', 'COMPLETED', 'RETURNED_TO_SELLER'], true) ? 'is-done' : '' }}">
                    <span class="ops-step__indicator">✓</span>
                    <span class="ops-step__label">{{ $isReturn ? 'Returned' : 'Complete' }}</span>
                </div>
            </div>

            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Location</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->trackingEvents as $event)
                            <tr>
                                <td data-label="Event">{{ str_replace('_', ' ', $event->event_type) }}</td>
                                <td data-label="Time" class="ops-mono">{{ $event->created_at->format('d M Y H:i') }}</td>
                                <td data-label="Actor">{{ $event->actor ? $event->actor->first_name . ' ' . $event->actor->last_name : 'System' }}</td>
                                <td data-label="Location">{{ $event->location ?? '—' }}</td>
                                <td data-label="Notes">{{ $event->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="ops-empty">No recorded events yet.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @if ($orders->isEmpty())
        <div class="ops-empty">No parcels match the selected filters.</div>
    @endif

    <div class="ops-pagination">{{ $orders->links() }}</div>
@endsection
