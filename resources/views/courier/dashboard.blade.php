@extends('layouts.courier')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>My dispatch board</h1>
            <p>{{ now()->format('D, d M Y') }} · Pickups, hub handoffs and doorstep deliveries assigned to you.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status {{ $courier->status === 'approved' ? 'ops-status--green' : 'ops-status--amber' }}">
                {{ ucfirst($courier->status) }}
            </span>
        </div>
    </header>

    {{-- Mobile-First Hero Next Action Card --}}
    <section class="courier-hero-card" aria-labelledby="courier-next-action-title">
        <div>
            <span class="courier-hero-card__badge">Priority Action</span>
            <h2 id="courier-next-action-title">
                @if (count($myDeliveryAssignments) > 0)
                    Doorstep delivery: {{ $myDeliveryAssignments->first()->order_number }}
                @elseif ($claimedPickups->isNotEmpty())
                    Pickup in progress: {{ $claimedPickups->first()->order_number }}
                @elseif ($myActivePickups->isNotEmpty())
                    Hub handoff: {{ $myActivePickups->first()->order_number }}
                @elseif ($myReturns->isNotEmpty())
                    Seller return: {{ $myReturns->first()->order_number }}
                @elseif (count($availablePickups) > 0)
                    Available pickup request
                @elseif ($myFailedDeliveries->isNotEmpty())
                    Failed delivery under review: {{ $myFailedDeliveries->first()->order_number }}
                @else
                    All caught up!
                @endif
            </h2>
            <p>
                @if (count($myDeliveryAssignments) > 0)
                    Deliver to {{ $myDeliveryAssignments->first()->recipient_name }} in {{ $myDeliveryAssignments->first()->delivery_area ?? $myDeliveryAssignments->first()->municipality }}.
                @elseif ($claimedPickups->isNotEmpty())
                    @if ($claimedPickups->first()->pickup_arrived_at && $claimedPickups->first()->seller_handover_at)
                        Scan the parcel label to confirm possession of pickup {{ $claimedPickups->first()->order_number }}.
                    @elseif ($claimedPickups->first()->pickup_arrived_at)
                        Record the seller handoff for pickup {{ $claimedPickups->first()->order_number }}.
                    @else
                        Travel to the seller for pickup {{ $claimedPickups->first()->order_number }} and record your arrival.
                    @endif
                @elseif ($myActivePickups->isNotEmpty())
                    Take collected parcel {{ $myActivePickups->first()->order_number }} to the sorting hub.
                @elseif ($myReturns->isNotEmpty())
                    Complete the seller return for parcel {{ $myReturns->first()->order_number }}.
                @elseif (count($availablePickups) > 0)
                    Review the seller pickup requests assigned to you.
                @elseif ($myFailedDeliveries->isNotEmpty())
                    A failed delivery is with Logistics for review. Check its recorded attempt and messages.
                @else
                    You have no open parcel tasks right now. Great job!
                @endif
            </p>
        </div>
        <div>
            @if (count($myDeliveryAssignments) > 0)
                <a class="ops-btn ops-btn--primary" href="{{ route('courier.orders.show', $myDeliveryAssignments->first()) }}">Open delivery</a>
            @elseif ($claimedPickups->isNotEmpty())
                <a class="ops-btn ops-btn--primary" href="#accepted-pickups">Continue pickup</a>
            @elseif ($myActivePickups->isNotEmpty())
                <a class="ops-btn ops-btn--primary" href="#hub-custody">View parcels for hub handoff</a>
            @elseif ($myReturns->isNotEmpty())
                <a class="ops-btn ops-btn--primary" href="#seller-returns">Continue return</a>
            @elseif (count($availablePickups) > 0)
                <a class="ops-btn ops-btn--primary" href="#pickup-requests">Review pickups</a>
            @elseif ($myFailedDeliveries->isNotEmpty())
                <a class="ops-btn ops-btn--danger" href="#failed-deliveries">Review failed delivery</a>
            @endif
        </div>
    </section>

    {{-- Quick Jump Navigation --}}
    <nav class="courier-jump-nav" aria-label="Workflow quick jump">
        <a class="courier-jump-pill" href="#pickup-requests">
            Available pickups <span>{{ $availablePickups->total() }}</span>
        </a>
        <a class="courier-jump-pill" href="#accepted-pickups">
            Claimed pickups <span>{{ $claimedPickups->count() }}</span>
        </a>
        <a class="courier-jump-pill" href="#hub-custody">
            Parcels to hub <span>{{ $myActivePickups->count() }}</span>
        </a>
        <a class="courier-jump-pill" href="#delivery-assignments">
            Deliveries <span>{{ $myDeliveryAssignments->total() }}</span>
        </a>
        <a class="courier-jump-pill" href="#failed-deliveries">
            Failed review <span>{{ $myFailedDeliveries->count() }}</span>
        </a>
        <a class="courier-jump-pill" href="#seller-returns">
            Returns <span>{{ $myReturns->count() }}</span>
        </a>
    </nav>

    {{-- Workload Summary Metrics --}}
    <section class="ops-stats ops-stats--priority" aria-label="Today's work summary">
        <div class="ops-stat ops-stat--action">
            <span>Pickup claims</span>
            <strong>{{ $stats['claimed_pickups'] }}</strong>
        </div>
        <div class="ops-stat">
            <span>Parcels to hub</span>
            <strong>{{ $stats['in_transit_hub'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Delivery workload</span>
            <strong>{{ $stats['assigned_delivery'] }}</strong>
        </div>
        <div class="ops-stat">
            <span>Returns to seller</span>
            <strong>{{ $stats['returning_to_seller'] }}</strong>
        </div>
        <div class="ops-stat">
            <span>Delivered today</span>
            <strong>{{ $stats['completed_today'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--danger">
            <span>Failed attempts today</span>
            <strong>{{ $stats['failed_today'] }}</strong>
        </div>
    </section>

    {{-- Available Seller Pickups --}}
    <section class="ops-panel courier-pickup" id="pickup-requests">
        <h2>Available seller pickups <span class="ops-muted">{{ $availablePickups->total() }} available</span></h2>
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
                            <td class="ops-mono" data-label="Order">{{ $order->order_number }}</td>
                            <td data-label="Seller">
                                <strong>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</strong>
                                <div class="ops-muted">{{ $order->seller?->street_address }}, {{ $order->seller?->municipality }}</div>
                                @if ($order->seller?->contact_no)
                                    <div>
                                        <a href="tel:{{ $order->seller->contact_no }}" class="courier-tel-link">
                                            📞 {{ $order->seller->contact_no }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td data-label="Destination">{{ $order->municipality }}, {{ $order->province }}</td>
                            <td data-label="Contents">{{ $order->items->count() }} item(s)</td>
                            <td data-label="Action">
                                <div class="courier-actions">
                                    <form method="POST" action="{{ route('courier.orders.claim', $order) }}">
                                        @csrf
                                        <button class="ops-btn ops-btn--primary" type="submit" @disabled($courier->status !== 'approved')>
                                            Accept assigned pickup
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('courier.orders.declinePickup', $order) }}">
                                        @csrf
                                        <input type="text" name="reason" maxlength="500" placeholder="Reason (optional)" aria-label="Reason for declining pickup">
                                        <button class="ops-btn" type="submit" @disabled($courier->status !== 'approved')>
                                            Decline assignment
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
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

    {{-- Claimed / Accepted Pickups --}}
    <section class="ops-panel courier-pickup" id="accepted-pickups">
        <h2>Accepted pickups <span class="ops-muted">{{ $claimedPickups->count() }} active</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller & Location</th>
                        <th>Claimed</th>
                        <th>Pickup custody confirmation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($claimedPickups as $order)
                        <tr>
                            <td class="ops-mono" data-label="Order">{{ $order->order_number }}</td>
                            <td data-label="Seller">
                                <strong>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</strong>
                                <div class="ops-muted">{{ $order->seller?->street_address }}, {{ $order->seller?->municipality }}</div>
                                @if ($order->seller?->contact_no)
                                    <div>
                                        <a href="tel:{{ $order->seller->contact_no }}" class="courier-tel-link">
                                            📞 {{ $order->seller->contact_no }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td data-label="Claimed">{{ $order->pickup_claimed_at?->format('d M H:i') }}</td>
                            <td data-label="Next step">
                                <form method="POST" action="{{ route('courier.orders.confirmPickup', $order) }}">
                                    @csrf
                                    @if ($order->pickup_arrived_at && $order->seller_handover_at)
                                        <div class="ops-field">
                                            <label for="pickup-parcel-{{ $order->id }}">Scan parcel label QR/code</label>
                                            <input id="pickup-parcel-{{ $order->id }}" name="parcel_reference" data-qr-input required maxlength="100" placeholder="EZP:…" autocomplete="off">
                                        </div>
                                        <input type="hidden" name="method" value="manual">
                                        <div class="courier-actions">
                                            <button type="button" class="ops-btn" data-qr-start>Use camera</button>
                                            <button type="button" class="ops-btn" data-qr-stop hidden>Stop camera</button>
                                        </div>
                                        <video data-qr-video playsinline hidden></video>
                                        <p data-qr-status role="status" class="ops-muted">Scan the parcel label, or enter its code manually.</p>
                                    @endif
                                    <button class="ops-btn ops-btn--primary" @disabled($courier->status !== 'approved')>
                                        {{ $order->pickup_arrived_at && $order->seller_handover_at ? 'Confirm scanned parcel possession' : ($order->pickup_arrived_at ? 'Waiting for seller handover' : 'Record arrival at seller') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No accepted pickups waiting for collection confirmation.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Collected Parcels Heading to Hub --}}
    <section class="ops-panel courier-pickup" id="hub-custody">
        <h2>Collected parcels heading to the hub <span class="ops-muted">{{ $myActivePickups->count() }} parcels</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Collected</th>
                        <th>Hub status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myActivePickups as $order)
                        <tr>
                            <td class="ops-mono" data-label="Order">{{ $order->order_number }}</td>
                            <td data-label="Seller">{{ $order->seller?->business_name ?? $order->seller?->first_name }}</td>
                            <td data-label="Collected">{{ $order->picked_up_at?->format('d M H:i') }}</td>
                            <td data-label="Status">
                                <span class="ops-status ops-status--amber">Awaiting hub receipt</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No parcels in transit to the sorting center.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Return Parcels to Sellers --}}
    <section class="ops-panel courier-delivery" id="seller-returns">
        <h2>Return parcels to sellers <span class="ops-muted">{{ $myReturns->count() }} pending</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller return address</th>
                        <th>Next step</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($myReturns as $order)
                        <tr>
                            <td class="ops-mono" data-label="Order">{{ $order->order_number }}</td>
                            <td data-label="Return address">
                                <strong>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</strong>
                                <div class="ops-muted">{{ $order->seller?->street_address }}, {{ $order->seller?->barangay }}, {{ $order->seller?->municipality }}</div>
                                @if ($order->seller?->contact_no)
                                    <div>
                                        <a href="tel:{{ $order->seller->contact_no }}" class="courier-tel-link">
                                            📞 {{ $order->seller->contact_no }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td data-label="Action">
                                <form method="POST" action="{{ route('courier.orders.confirmReturnDelivery', $order) }}" onsubmit="return confirm('Confirm that you physically handed this return parcel to the seller?')">
                                    @csrf
                                    <div class="ops-field">
                                        <label for="return-parcel-{{ $order->id }}">Scan parcel label QR/code</label>
                                        <input id="return-parcel-{{ $order->id }}" name="parcel_reference" data-qr-input required maxlength="100" placeholder="EZP:…" autocomplete="off">
                                    </div>
                                    <input type="hidden" name="method" value="manual">
                                    <div class="courier-actions">
                                        <button type="button" class="ops-btn" data-qr-start>Use camera</button>
                                        <button type="button" class="ops-btn" data-qr-stop hidden>Stop camera</button>
                                    </div>
                                    <video data-qr-video playsinline hidden></video>
                                    <p data-qr-status role="status" class="ops-muted">Camera, handheld scanner, or manual entry.</p>
                                    <button class="ops-btn ops-btn--primary" @disabled($courier->status !== 'approved')>
                                        Record scanned seller handoff
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="ops-empty">No return parcels are waiting for seller handoff.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Failed Deliveries Requiring Logistics Follow-up (CRITICAL BUG/UX FIX) --}}
    <section class="ops-panel courier-delivery courier-failed" id="failed-deliveries">
        <h2>
            Failed deliveries requiring logistics follow-up
            <span class="ops-muted">{{ $myFailedDeliveries->count() }} active</span>
        </h2>
        @forelse ($myFailedDeliveries as $order)
            <article class="courier-failed-alert-card">
                <div class="courier-failed-header">
                    <div>
                        <div class="ops-mono">
                            <a href="{{ route('courier.orders.show', $order) }}"><strong>Order {{ $order->order_number }}</strong></a>
                        </div>
                        <div class="ops-muted">Recipient: {{ $order->recipient_name }} · {{ $order->delivery_area ?? $order->municipality }}</div>
                    </div>
                    <span class="courier-failed-reason-tag">
                        ⚠️ {{ str_replace('_', ' ', ucfirst($order->delivery_failure_reason)) }}
                    </span>
                </div>
                <div class="courier-failed-guidance">
                    <strong>What happens next?</strong>
                    Logistics will review the recorded attempt and decide the next step. You cannot retry or return this parcel from the courier screen. The parcel must be physically checked into the hub before retry or return.
                </div>
                <div class="courier-actions">
                    <span class="ops-muted">Recorded: {{ $order->failed_at?->format('d M Y H:i') }}</span>
                    <a class="ops-btn" href="{{ route('courier.orders.show', $order) }}">View delivery record</a>
                    <a class="ops-btn" href="{{ route('courier.orders.messages.show', $order) }}">Order messages</a>
                </div>
            </article>
        @empty
            <div class="ops-empty">No failed deliveries need follow-up. All parcels are clear.</div>
        @endforelse
    </section>

    {{-- Doorstep Delivery Work --}}
    <section class="ops-panel courier-delivery" id="delivery-assignments">
        <h2>Doorstep delivery work <span class="ops-muted">{{ $myDeliveryAssignments->total() }} assignments</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order / Stage</th>
                        <th>Recipient & Destination</th>
                        <th>Payment</th>
                        <th>Delivery action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myDeliveryAssignments as $order)
                        <tr>
                            <td class="ops-mono" data-label="Order">
                                <a href="{{ route('courier.orders.show', $order) }}"><strong>{{ $order->order_number }}</strong></a>
                                <div><span class="ops-status {{ $order->status === 'OUT_FOR_DELIVERY' ? 'ops-status--pink' : 'ops-status--amber' }}">{{ str_replace('_', ' ', $order->status) }}</span></div>
                                <div class="ops-muted">Assigned {{ $order->assigned_at?->diffForHumans() }}</div>
                            </td>
                            <td data-label="Recipient">
                                <strong>{{ $order->recipient_name }}</strong>
                                <div class="ops-muted">{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}</div>
                                <div class="ops-muted">{{ $order->delivery_area }}</div>
                                <div>
                                    <a href="tel:{{ $order->recipient_contact }}" class="courier-tel-link">
                                        📞 {{ $order->recipient_contact }}
                                    </a>
                                </div>
                            </td>
                            <td data-label="Payment">
                                @if ($order->payment_method === 'COD')
                                    <div class="courier-cod-badge">
                                        💵 COD: ₱{{ number_format($order->total_amount, 2) }}
                                    </div>
                                @else
                                    <span class="ops-status ops-status--green">{{ $order->payment_method }}</span>
                                    <div class="ops-muted">₱{{ number_format($order->total_amount, 2) }}</div>
                                @endif
                            </td>
                            <td data-label="Delivery action">
                                @if ($order->status === 'ASSIGNED_TO_RIDER')
                                    @if ($order->hub_released_at)
                                        <form method="POST" action="{{ route('courier.orders.startDelivery', $order) }}">
                                            @csrf
                                            <div class="ops-field">
                                                <label for="start-parcel-reference-{{ $order->id }}">Parcel QR/code (optional manual handoff fallback)</label>
                                                <input id="start-parcel-reference-{{ $order->id }}" name="parcel_reference" maxlength="100" placeholder="EZP:…" autocomplete="off">
                                            </div>
                                            <input type="hidden" name="method" value="manual">
                                            <button class="ops-btn ops-btn--primary" @disabled($courier->status !== 'approved')>
                                                Start delivery
                                            </button>
                                        </form>
                                    @else
                                        <div class="ops-banner ops-banner--urgent">
                                            <span>Waiting for Logistics hub release</span>
                                        </div>
                                        <form class="ops-form" method="POST" action="{{ route('courier.orders.declineDeliveryAssignment', $order) }}" onsubmit="return confirm('Decline this delivery assignment and return the parcel to dispatch?')">
                                            @csrf
                                            <div class="ops-field">
                                                <label for="decline-reason-{{ $order->id }}">Reason (optional)</label>
                                                <input id="decline-reason-{{ $order->id }}" name="reason" maxlength="500">
                                            </div>
                                            <button class="ops-btn ops-btn--danger" type="submit" @disabled($courier->status !== 'approved')>
                                                Decline assignment
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <details>
                                        <summary class="ops-btn ops-btn--primary">Complete delivery</summary>
                                        <form class="ops-form" method="POST" action="{{ route('courier.orders.completeDelivery', $order) }}" enctype="multipart/form-data" onsubmit="return confirm('Confirm this parcel was delivered to the named recipient?')">
                                            @csrf
                                            @method('PATCH')
                                            <div class="ops-field">
                                                <label for="recipient-confirmation-{{ $order->id }}">Recipient confirmation</label>
                                                <input id="recipient-confirmation-{{ $order->id }}" name="recipient_confirmation" maxlength="120" required>
                                            </div>
                                            <div class="ops-field">
                                                <label for="buyer-delivery-code-{{ $order->id }}">Buyer delivery code</label>
                                                <input id="buyer-delivery-code-{{ $order->id }}" class="courier-code-input" name="delivery_code" inputmode="numeric" autocomplete="off" maxlength="32" required placeholder="000000">
                                            </div>
                                            @if ($order->payment_method === 'COD')
                                                <div class="ops-field">
                                                    <label for="cod-collected-amount-{{ $order->id }}">Cash collected (₱{{ number_format($order->total_amount, 2) }})</label>
                                                    <input id="cod-collected-amount-{{ $order->id }}" type="number" step="0.01" min="0" name="cod_collected_amount" required value="{{ $order->total_amount }}">
                                                </div>
                                            @endif
                                            <div class="ops-field">
                                                <label for="delivery-notes-{{ $order->id }}">Delivery notes</label>
                                                <input id="delivery-notes-{{ $order->id }}" name="delivery_notes" maxlength="1000">
                                            </div>
                                            <div class="ops-field courier-file-card">
                                                <label for="proof-file-{{ $order->id }}">Proof of delivery photo or document</label>
                                                <input id="proof-file-{{ $order->id }}" type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                            </div>
                                            <button class="ops-btn ops-btn--primary" type="submit">Confirm delivered</button>
                                        </form>
                                    </details>
                                    <details>
                                        <summary class="ops-btn ops-btn--danger">Record failed attempt</summary>
                                        <form class="ops-form" method="POST" action="{{ route('courier.orders.failDelivery', $order) }}" onsubmit="return confirm('Record this delivery attempt as failed?')">
                                            @csrf
                                            @method('PATCH')
                                            <div class="ops-field">
                                                <label for="failure-reason-{{ $order->id }}">Reason</label>
                                                <select id="failure-reason-{{ $order->id }}" name="failure_reason" required>
                                                    <option value="">Select reason</option>
                                                    <option value="recipient_unavailable">Recipient unavailable</option>
                                                    <option value="incorrect_address">Incorrect address</option>
                                                    <option value="recipient_refused">Recipient refused</option>
                                                    <option value="unreachable_contact">Unreachable contact</option>
                                                    <option value="access_issue">Access issue</option>
                                                    <option value="damaged_parcel">Damaged parcel</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="ops-field">
                                                <label for="failure-notes-{{ $order->id }}">Notes</label>
                                                <input id="failure-notes-{{ $order->id }}" name="delivery_notes" maxlength="1000">
                                            </div>
                                            <button class="ops-btn ops-btn--danger" type="submit">Record failure</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
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

    {{-- Rider Identification Badge --}}
    <details class="ops-panel courier-badge-details">
        <summary>Rider identification badge</summary>
        <p class="ops-muted">Show this QR at the hub or seller handoff. Staff actions still require their own authenticated account and assignment checks.</p>
        <p class="ops-mono">EZR:{{ $badgeCode }}</p>
        <div class="courier-badge-qr" role="img" aria-label="QR code for rider identification">
            {!! $badgeQr !!}
        </div>
        <form method="POST" action="{{ route('courier.badge.rotate') }}" onsubmit="return confirm('Rotate this badge? Any printed copy of the old QR will stop identifying your badge.')">
            @csrf
            <button class="ops-btn" type="submit">Rotate rider badge</button>
        </form>
    </details>
@endsection
