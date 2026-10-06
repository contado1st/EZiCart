@extends('layouts.courier')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Delivery details</h1>
            <p>Order {{ $order->order_number }} · {{ $order->recipient_name }} · {{ $order->recipient_contact }} · {{ $order->delivery_area ?? $order->municipality }}</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : ($order->status === 'OUT_FOR_DELIVERY' ? 'ops-status--pink' : 'ops-status--amber') }}">
                {{ str_replace('_', ' ', $order->status) }}
            </span>
            <a class="ops-btn" href="{{ route('courier.orders.messages.show', $order) }}">Order messages</a>
            <a class="ops-btn" href="{{ route('courier.dashboard') }}">Back to board</a>
        </div>
    </header>

    @if ($order->payment_method !== 'COD' && !in_array($order->status, ['CANCELLED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true))
        <section class="ops-panel ops-alert--error" role="status">
            Fulfillment is on hold because payment verification for {{ $order->payment_method }} is not configured yet.
        </section>
    @endif

    {{-- Prominent Failed Delivery State Banner --}}
    @if ($order->status === 'DELIVERY_FAILED')
        <section class="ops-panel courier-failed" role="status" aria-live="polite">
            <div class="courier-failed-header">
                <h2>Delivery attempt recorded</h2>
                <span class="courier-failed-reason-tag">
                    ⚠️ {{ str_replace('_', ' ', ucfirst($order->delivery_failure_reason)) }}
                </span>
            </div>
            <div class="courier-failed-guidance">
                <strong>Logistics Hub Review Pending</strong>
                <p class="courier-status-copy">
                    Logistics will review the recorded attempt and decide whether to assign another delivery or arrange a return. No retry or return action is available on the courier screen yet. The parcel must be physically checked into the hub before retry or return.
                </p>
            </div>
            <div class="courier-actions">
                <a class="ops-btn" href="{{ route('courier.dashboard') }}">Return to work board</a>
                <a class="ops-btn" href="{{ route('courier.orders.messages.show', $order) }}">Message Logistics</a>
            </div>
        </section>
    @endif

    <section class="ops-grid courier-order-summary" aria-label="Delivery and parcel information">
        <div class="ops-panel">
            <h2>Drop-off destination</h2>
            <p><strong>{{ $order->recipient_name }}</strong></p>
            <p>{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}</p>
            <div>
                <a href="tel:{{ $order->recipient_contact }}" class="courier-tel-link">
                    📞 Call {{ $order->recipient_contact }}
                </a>
            </div>
            <div class="ops-field">
                @if ($order->payment_method === 'COD')
                    <div class="courier-cod-badge">
                        💵 Cash on Delivery: ₱{{ number_format($order->total_amount, 2) }}
                    </div>
                @else
                    <span class="ops-status ops-status--green">{{ $order->payment_method }}</span>
                    <span class="ops-muted">₱{{ number_format($order->total_amount, 2) }}</span>
                @endif
            </div>
            <p class="ops-muted">Order notes: {{ $order->notes ?? 'None provided' }}</p>
        </div>

        <div class="ops-panel">
            <h2>Parcel contents</h2>
            @forelse($order->items as $item)
                <p><strong>{{ $item->product_name }}</strong> × {{ $item->quantity }}</p>
            @empty
                <p class="ops-muted">No item summary recorded.</p>
            @endforelse
            <div class="ops-muted">
                <p>Rider assignment: {{ $order->assigned_at?->format('d M Y H:i') ?? '—' }}</p>
                <p>Out for delivery: {{ $order->out_for_delivery_at?->format('d M Y H:i') ?? 'Not started' }}</p>
            </div>
        </div>
    </section>

    @if ($order->delivery_courier_id === auth()->id() && $order->status === 'ASSIGNED_TO_RIDER')
        <section class="ops-panel courier-delivery">
            <h2>Start delivery</h2>
            @if ($order->payment_method !== 'COD')
                <p class="ops-muted">Delivery is on hold until payment verification for this method is configured.</p>
            @elseif ($order->hub_released_at)
                <div class="ops-banner ops-banner--success">
                    <span>Logistics confirmed the hub release at {{ $order->hub_released_at->format('d M Y H:i') }}.</span>
                </div>
                <form class="ops-form" method="POST" action="{{ route('courier.orders.startDelivery', $order) }}">
                    @csrf
                    <div class="ops-field">
                        <label for="start-parcel-reference">Parcel QR/code (optional manual handoff fallback)</label>
                        <input id="start-parcel-reference" name="parcel_reference" maxlength="100" placeholder="EZP:…" autocomplete="off">
                    </div>
                    <input type="hidden" name="method" value="manual">
                    <button class="ops-btn ops-btn--primary ops-btn--lg">Start delivery</button>
                </form>
            @else
                <div class="ops-banner ops-banner--urgent">
                    <span>Waiting for Logistics to verify and release this parcel from the hub.</span>
                </div>
                <form class="ops-form" method="POST" action="{{ route('courier.orders.declineDeliveryAssignment', $order) }}" onsubmit="return confirm('Decline this delivery assignment and return the parcel to dispatch?')">
                    @csrf
                    <div class="ops-field">
                        <label for="assignment-decline-reason">Reason (optional)</label>
                        <input id="assignment-decline-reason" name="reason" maxlength="500">
                    </div>
                    <button class="ops-btn ops-btn--danger" type="submit">Decline assignment</button>
                </form>
            @endif
        </section>
    @elseif($order->delivery_courier_id === auth()->id() && $order->status === 'OUT_FOR_DELIVERY')
        <section class="ops-panel courier-delivery">
            <h2>Delivery result</h2>
            <div class="ops-grid">
                @if ($order->payment_method === 'COD')
                    <div class="ops-panel">
                        <h3>Complete delivery</h3>
                        <form class="ops-form" method="POST" action="{{ route('courier.orders.completeDelivery', $order) }}" enctype="multipart/form-data" onsubmit="return confirm('Confirm this parcel was delivered?')">
                            @csrf
                            @method('PATCH')
                            <div class="ops-field">
                                <label for="recipient-confirmation">Recipient confirmation</label>
                                <input id="recipient-confirmation" name="recipient_confirmation" maxlength="120" required placeholder="Name of person receiving parcel">
                            </div>
                            <div class="ops-field">
                                <label for="buyer-delivery-code">Buyer delivery code</label>
                                <input id="buyer-delivery-code" class="courier-code-input" name="delivery_code" inputmode="numeric" autocomplete="off" maxlength="32" required placeholder="000000">
                            </div>
                            <div class="ops-field">
                                <label for="cod-collected-amount">Cash collected (₱{{ number_format($order->total_amount, 2) }})</label>
                                <input id="cod-collected-amount" name="cod_collected_amount" type="number" min="0" step="0.01" required value="{{ $order->total_amount }}">
                            </div>
                            <div class="ops-field">
                                <label for="delivery-notes">Delivery notes</label>
                                <input id="delivery-notes" name="delivery_notes" maxlength="1000" placeholder="Optional notes (e.g. left with front desk)">
                            </div>
                            <div class="ops-field courier-file-card">
                                <label for="proof-file">Proof of delivery (photo or PDF)</label>
                                <input id="proof-file" type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required>
                            </div>
                            <button class="ops-btn ops-btn--primary ops-btn--lg" type="submit">Confirm delivered</button>
                        </form>
                    </div>
                @else
                    <div class="ops-panel">
                        <p class="ops-muted">Delivery confirmation is unavailable until payment verification is configured.</p>
                    </div>
                @endif

                <div class="ops-panel ops-panel--urgent">
                    <h3>Record failed attempt</h3>
                    <form class="ops-form" method="POST" action="{{ route('courier.orders.failDelivery', $order) }}" onsubmit="return confirm('Record a failed delivery attempt?')">
                        @csrf
                        @method('PATCH')
                        <div class="ops-field">
                            <label for="failure-reason">Failure reason</label>
                            <select id="failure-reason" name="failure_reason" required>
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
                            <label for="failure-notes">Notes</label>
                            <input id="failure-notes" name="delivery_notes" maxlength="1000" placeholder="Describe reason for delivery failure">
                        </div>
                        <button class="ops-btn ops-btn--danger ops-btn--lg" type="submit">Record failure</button>
                    </form>
                </div>
            </div>
        </section>
    @endif

    <section class="ops-panel">
        <h2>Delivery attempts <span class="ops-muted">{{ $order->deliveryAttempts->count() }} recorded</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Attempt</th>
                        <th>Outcome</th>
                        <th>Reason</th>
                        <th>Time</th>
                        <th>Proof</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($order->deliveryAttempts as $attempt)
                        <tr>
                            <td data-label="Attempt">Attempt {{ $attempt->attempt_no }}</td>
                            <td data-label="Outcome">
                                <span class="ops-status {{ $attempt->outcome === 'failed' ? 'ops-status--danger' : 'ops-status--green' }}">
                                    {{ ucfirst($attempt->outcome) }}
                                </span>
                            </td>
                            <td data-label="Reason">{{ $attempt->reason ? str_replace('_', ' ', $attempt->reason) : '—' }}</td>
                            <td data-label="Time" class="ops-mono">
                                {{ $attempt->attempted_at?->format('d M Y H:i') ?? 'Not recorded' }}
                                @if ($attempt->scheduled_at)
                                    <div class="ops-muted">Scheduled: {{ $attempt->scheduled_at->format('d M Y H:i') }}</div>
                                @endif
                            </td>
                            <td data-label="Proof">
                                @if ($attempt->proof_path)
                                    <a class="ops-btn" href="{{ route('delivery-attempts.proof', $attempt) }}">Download proof</a>
                                @else
                                    <span class="ops-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">No delivery attempts recorded.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="ops-panel">
        <h2>Parcel tracking timeline</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->trackingEvents as $event)
                        <tr>
                            <td data-label="Event">{{ str_replace('_', ' ', $event->event_type) }}</td>
                            <td data-label="Time" class="ops-mono">{{ $event->created_at->format('d M Y H:i') }}</td>
                            <td data-label="Location">{{ $event->location ?? '—' }}</td>
                            <td data-label="Notes">{{ $event->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No tracking updates recorded.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
