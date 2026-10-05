@extends('layouts.courier')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Courier · {{ now()->format('D, d M Y') }}</div>
            <h1>My dispatch board</h1>
            <p>Pickups, hub handoffs and doorstep deliveries assigned to your account.</p>
        </div>
        <div class="courier-actions">
            @if (count($myDeliveryAssignments) > 0)
                <a class="ops-btn" href="{{ route('courier.orders.show', $myDeliveryAssignments->first()) }}">Open next
                    delivery</a>
            @endif
            <span class="ops-status {{ $courier->status === 'approved' ? 'ops-status--green' : 'ops-status--amber' }}">
                {{ ucfirst($courier->status) }}</span>
        </div>
    </header>
    <section class="ops-stats">
        <div class="ops-stat"><span>Pickup claims</span><strong>{{ $stats['claimed_pickups'] }}</strong></div>
        <div class="ops-stat"><span>Parcels to hub</span><strong>{{ $stats['in_transit_hub'] }}</strong></div>
        <div class="ops-stat"><span>Delivery workload</span><strong>{{ $stats['assigned_delivery'] }}</strong></div>
        <div class="ops-stat"><span>Returns to seller</span><strong>{{ $stats['returning_to_seller'] }}</strong></div>
        <div class="ops-stat"><span>Delivered today</span><strong>{{ $stats['completed_today'] }}</strong></div>
        <div class="ops-stat"><span>Failed today</span><strong>{{ $stats['failed_today'] }}</strong></div>
    </section>
    <section class="ops-panel courier-pickup">
        <h2>Pickup work · available seller requests</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller pickup point</th>
                        <th>Destination</th>
                        <th>Contents</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($availablePickups as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>{{ $order->seller?->business_name ?? $order->seller?->first_name }}<div class="ops-muted">
                                    {{ $order->seller?->street_address }}, {{ $order->seller?->municipality }}</div>
                                <div class="ops-muted">{{ $order->seller?->contact_no }}</div>
                            </td>
                            <td>{{ $order->municipality }}, {{ $order->province }}</td>
                            <td>{{ $order->items->count() }} item(s)</td>
                            <td>
                                <form method="POST" action="{{ route('courier.orders.claim', $order) }}">@csrf<button
                                        class="ops-btn ops-btn--primary" type="submit" @disabled($courier->status !== 'approved')>Accept
                                        assigned pickup</button></form>
                                <form method="POST" action="{{ route('courier.orders.declinePickup', $order) }}" style="margin-top:.5rem">@csrf
                                    <input type="text" name="reason" maxlength="500" placeholder="Reason (optional)" aria-label="Reason for declining pickup">
                                    <button class="ops-btn" type="submit" @disabled($courier->status !== 'approved')>Decline assignment</button>
                                </form>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="5">
                                <div class="ops-empty">No unclaimed seller pickups are available.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $availablePickups->links() }}</div>
    </section>
    <section class="ops-panel courier-pickup">
        <h2>Pickup work · accepted requests</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Claimed</th>
                        <th>Next step</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($claimedPickups as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>{{ $order->seller?->business_name ?? $order->seller?->first_name }}<div class="ops-muted">
                                    {{ $order->seller?->municipality }}</div>
                            </td>
                            <td>{{ $order->pickup_claimed_at?->format('d M H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('courier.orders.confirmPickup', $order) }}">
                                    @csrf<button class="ops-btn ops-btn--primary" @disabled($courier->status !== 'approved')>{{ $order->pickup_arrived_at && $order->seller_handover_at ? 'Confirm parcel possession' : ($order->pickup_arrived_at ? 'Waiting for seller handover' : 'Record arrival at seller') }}</button></form>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="4">
                                <div class="ops-empty">No accepted pickups waiting for collection confirmation.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="ops-panel courier-pickup">
        <h2>Pickup work · in your custody, heading to the hub</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Collected</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myActivePickups as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</td>
                            <td>{{ $order->picked_up_at?->format('d M H:i') }}</td>
                            <td><span class="ops-status ops-status--amber">Awaiting hub receipt</span></td>
                    </tr>@empty<tr>
                            <td colspan="4">
                                <div class="ops-empty">No parcels in transit to the sorting center.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="ops-panel courier-delivery">
        <h2>Return parcels to sellers</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead><tr><th>Order</th><th>Seller return address</th><th>Next step</th></tr></thead>
                <tbody>
                    @forelse ($myReturns as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>{{ $order->seller?->business_name ?? $order->seller?->first_name }}<div class="ops-muted">{{ $order->seller?->street_address }}, {{ $order->seller?->barangay }}, {{ $order->seller?->municipality }}</div></td>
                            <td><form method="POST" action="{{ route('courier.orders.confirmReturnDelivery', $order) }}" onsubmit="return confirm('Confirm that you physically handed this return parcel to the seller?')">@csrf<button class="ops-btn ops-btn--primary" @disabled($courier->status !== 'approved')>Record seller handoff</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="ops-empty">No return parcels are waiting for seller handoff.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="ops-panel courier-delivery">
        <h2>Failed deliveries requiring logistics follow-up</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead><tr><th>Order</th><th>Latest attempt</th><th>Recorded</th><th>Next step</th></tr></thead>
                <tbody>
                    @forelse ($myFailedDeliveries as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>{{ str_replace('_', ' ', $order->delivery_failure_reason) }}</td>
                            <td>{{ $order->failed_at?->format('d M H:i') }}</td>
                            <td>Logistics will reassign the delivery or initiate a return.</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="ops-empty">No failed deliveries need follow-up.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="ops-panel courier-delivery">
        <h2>Doorstep delivery work</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order / stage</th>
                        <th>Recipient & destination</th>
                        <th>Payment</th>
                        <th>Delivery action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myDeliveryAssignments as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}<div><span
                                        class="ops-status">{{ str_replace('_', ' ', $order->status) }}</span></div>
                                <div class="ops-muted">Assigned {{ $order->assigned_at?->diffForHumans() }}</div>
                            </td>
                            <td>{{ $order->recipient_name }}<div class="ops-muted">{{ $order->street_address }},
                                    {{ $order->barangay }}, {{ $order->municipality }}</div>
                                <div class="ops-muted">{{ $order->recipient_contact }} · {{ $order->delivery_area }}</div>
                            </td>
                            <td>{{ $order->payment_method }}<div class="ops-muted">
                                    ₱{{ number_format($order->total_amount, 2) }}</div>
                            </td>
                            <td>
                                @if ($order->status === 'ASSIGNED_TO_RIDER')
                                    @if ($order->hub_released_at)
                                        <form method="POST" action="{{ route('courier.orders.startDelivery', $order) }}">
                                            @csrf<button class="ops-btn ops-btn--primary" @disabled($courier->status !== 'approved')>Start delivery</button></form>
                                    @else
                                        <span class="ops-muted">Waiting for Logistics hub release</span>
                                        <form class="ops-form" method="POST" action="{{ route('courier.orders.declineDeliveryAssignment', $order) }}"
                                            onsubmit="return confirm('Decline this delivery assignment and return the parcel to dispatch?')">
                                            @csrf
                                            <div class="ops-field"><label for="decline-reason-{{ $order->id }}">Reason (optional)</label>
                                                <input id="decline-reason-{{ $order->id }}" name="reason" maxlength="500"></div>
                                            <button class="ops-btn ops-btn--danger" type="submit" @disabled($courier->status !== 'approved')>Decline assignment</button>
                                        </form>
                                    @endif
                                @else
                                    <details>
                                        <summary class="ops-btn ops-btn--primary">Complete delivery</summary>
                                        <form class="ops-form" method="POST"
                                            action="{{ route('courier.orders.completeDelivery', $order) }}"
                                            enctype="multipart/form-data"
                                            onsubmit="return confirm('Confirm this parcel was delivered to the named recipient?')">
                                            @csrf @method('PATCH')<div class="ops-field"><label>Recipient
                                                    confirmation</label><input name="recipient_confirmation" maxlength="120"
                                                    required></div>
                                            @if ($order->payment_method === 'COD')
                                                <div class="ops-field"><label>Cash collected
                                                        (₱{{ number_format($order->total_amount, 2) }})
                                                    </label><input type="number" step="0.01" min="0"
                                                        name="cod_collected_amount" required></div>
                                            @endif
                                            <div class="ops-field">
                                                <label>Delivery notes</label><input name="delivery_notes" maxlength="1000">
                                            </div>
                                            <div class="ops-field"><label>Proof of delivery (photo or PDF)</label><input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required></div>
                                            <button class="ops-btn ops-btn--primary">Confirm delivered</button>
                                        </form>
                                    </details>
                                    <details>
                                        <summary class="ops-btn ops-btn--danger">Record failed attempt</summary>
                                        <form class="ops-form" method="POST"
                                            action="{{ route('courier.orders.failDelivery', $order) }}"
                                            onsubmit="return confirm('Record this delivery attempt as failed?')">@csrf
                                            @method('PATCH')<div class="ops-field"><label>Reason</label><select
                                                    name="failure_reason" required>
                                                    <option value="">Select reason</option>
                                                    <option value="recipient_unavailable">Recipient unavailable</option>
                                                    <option value="incorrect_address">Incorrect address</option>
                                                    <option value="recipient_refused">Recipient refused</option>
                                                    <option value="unreachable_contact">Unreachable contact</option>
                                                    <option value="access_issue">Access issue</option>
                                                    <option value="damaged_parcel">Damaged parcel</option>
                                                    <option value="other">Other</option>
                                                </select></div>
                                            <div class="ops-field"><label>Notes</label><input name="delivery_notes"
                                                    maxlength="1000"></div><button class="ops-btn ops-btn--danger">Record
                                                failure</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                    </tr>@empty<tr>
                            <td colspan="4">
                                <div class="ops-empty">No delivery parcels are assigned to you.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $myDeliveryAssignments->links() }}</div>
    </section>
@endsection
