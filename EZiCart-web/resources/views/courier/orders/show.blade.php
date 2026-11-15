@extends('layouts.courier')

@section('workspace')
    @include('components.order-deadlines', ['order' => $order])
    <header class="ops-heading">
        <div>
            <h1>Delivery details</h1>
            <p>Order {{ $order->order_number }} · {{ $order->recipient_name }} · {{ $order->recipient_contact }} · {{ $order->delivery_area ?? $order->municipality }}</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : ($order->status === 'OUT_FOR_DELIVERY' ? 'ops-status--pink' : 'ops-status--amber') }}">
                {{ str_replace('_', ' ', $order->operational_status) }}
            </span>
            <a class="ops-btn" href="{{ route('courier.orders.messages.show', $order) }}">Order messages</a>
            <a class="ops-btn" href="{{ route('courier.dashboard') }}">Back to board</a>
        </div>
    </header>

    @if($order->pickup_courier_id === auth()->id() && in_array($order->status, ['READY_FOR_PICKUP', 'PICKED_UP'], true))
        <section class="ops-panel" aria-labelledby="pickup-next-step">
            <h2 id="pickup-next-step">Pickup progress and next step</h2>
            <p>{{ $order->status === 'PICKED_UP' ? 'Possession confirmed. Bring this parcel to the hub for its intake scan.' : ($order->pickup_claimed_at ? ($order->pickup_arrived_at ? ($order->seller_handover_at ? 'Seller handover confirmed. Scan the parcel label to confirm possession.' : 'Wait for the seller to confirm physical handover; do not confirm possession yet.') : 'Travel to the seller and record arrival.') : 'Accept the assigned pickup before recording arrival.') }}</p>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(['pickup_claimed_at' => 'Accepted', 'pickup_arrived_at' => 'Arrival recorded', 'seller_handover_at' => 'Seller handover', 'picked_up_at' => 'Possession confirmed'] as $field => $label)
                    <div><dt class="ops-muted">{{ $label }}</dt><dd>{{ $order->{$field}?->format('d M Y H:i') ?? 'Not recorded' }}</dd></div>
                @endforeach
            </dl>
            <p class="ops-muted">Camera, handheld and manual entry use the same parcel identifier: EZP: followed by the label code, or the label code alone. The order number is not the parcel label code. Both seller handover and a matching label are required.</p>
            <a class="ops-btn" href="{{ route('courier.dashboard') }}#{{ $order->status === 'PICKED_UP' ? 'hub-custody' : ($order->pickup_claimed_at ? 'accepted-pickups' : 'pickup-requests') }}">Continue on work board</a>
        </section>
    @endif

    <section class="ops-panel"><h2>My work compensation</h2><x-courier-compensation :summary="$workSummary" /><a class="ops-btn" href="{{ route('courier.history', ['search' => $order->order_number]) }}">View work history</a></section>

    @if (! $deliveryProgress['payment_ready'] && !in_array($order->status, ['CANCELLED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true))
        <section class="ops-panel ops-alert--error" role="status">
            Fulfillment is on hold. Payment for {{ $order->payment_method }} cannot currently be verified, is unsupported or requires account review. Do not dispatch or collect cash.
        </section>
    @endif

    {{-- Prominent Failed Delivery State Banner --}}
    @if ($order->status === 'DELIVERY_FAILED')
        <section class="ops-panel courier-failed" role="status" aria-live="polite">
            <div class="courier-failed-header">
                <h2>Delivery attempt recorded</h2>
                <span class="courier-failed-reason-tag">
                    <x-shop-icon name="warning" /> {{ str_replace('_', ' ', ucfirst($order->delivery_failure_reason)) }}
                </span>
            </div>
            <div class="courier-failed-guidance">
                <strong>Logistics Hub Review Pending</strong>
                <p class="courier-status-copy">
                    {{ $deliveryProgress['next_step'] }} A retry proposal is a request for logistics review; it cannot bypass physical hub intake or the configured attempt limit.
                </p>
            </div>
            <div class="courier-actions">
                <a class="ops-btn" href="{{ route('courier.dashboard') }}">Return to work board</a>
                <a class="ops-btn" href="{{ route('courier.orders.messages.show', $order) }}">Message Logistics</a>
            </div>
        </section>
    @endif

    @if($order->delivery_courier_id === auth()->id())
        <section class="ops-panel" aria-labelledby="delivery-next-step"><h2 id="delivery-next-step">Delivery progress and next step</h2>
            <p><strong>Attempt {{ $deliveryProgress['attempt_no'] }}</strong> · {{ $deliveryProgress['failures'] }} failed / {{ $deliveryProgress['attempt_limit'] }} permitted.</p>
            <p>{{ $deliveryProgress['next_step'] }}</p>
            @if($deliveryProgress['scheduled_at'])<p>Scheduled start: {{ $deliveryProgress['scheduled_at']->format('d M Y H:i') }} ({{ config('app.timezone') }}).</p>@endif
            @if($deliveryProgress['acceptance_due_at'] && !$deliveryProgress['accepted_at'])<p>Acceptance deadline: {{ $deliveryProgress['acceptance_due_at']->format('d M Y H:i') }}.</p>@endif
            @if($order->status === 'OUT_FOR_DELIVERY' && !$deliveryProgress['code_usable'])<p class="ops-muted">The buyer delivery code needs refresh or review. Ask the buyer to use their order page; never bypass the code or proof.</p>@endif
            <div class="courier-actions">
            @if($deliveryProgress['can_accept'])<form method="POST" action="{{ route('courier.orders.accept-assignment', $order) }}" data-decision="accept-delivery" data-decision-title="Accept delivery assignment?" data-decision-message="Accepted parcels remain your responsibility until a verified handover or recovery." data-decision-label="Accept assignment">@csrf<button class="ops-btn ops-btn--primary" type="submit">Accept assignment</button></form>@endif
            <a class="ops-btn" href="{{ route('courier.history', ['search' => $order->order_number]) }}">Review failed attempts and retry proposals</a>
            </div>
        </section>
    @endif

    <section class="ops-grid courier-order-summary" aria-label="Delivery and parcel information">
        <div class="ops-panel">
            <h2>Drop-off destination</h2>
            <p><strong>{{ $order->recipient_name }}</strong></p>
            <p>{{ trim(($order->house_number ? $order->house_number.' ' : '').$order->street_address) }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}</p>
            <div>
                <a href="tel:{{ $order->recipient_contact }}" class="courier-tel-link">
                    <x-shop-icon name="phone" /> Call {{ $order->recipient_contact }}
                </a>
            </div>
            <div class="ops-field">
                <x-courier-payment-status :order="$order" :ready="$deliveryProgress['payment_ready']" />
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
            @if (! $deliveryProgress['payment_ready'])
                <p class="ops-muted">Delivery is on hold until payment verification for this method is configured.</p>
            @elseif ($order->hub_released_at)
                <div class="ops-banner ops-banner--success">
                    <span>Logistics confirmed the hub release at {{ $order->hub_released_at->format('d M Y H:i') }}.</span>
                </div>
                <form class="ops-form" method="POST" action="{{ route('courier.orders.startDelivery', $order) }}" data-decision="courier.orders.startDelivery" data-decision-title="Start delivery?" data-decision-message="This records that the parcel is going out for delivery." data-decision-label="Start delivery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf
                    <div class="ops-field">
                        <label for="start-parcel-reference">Parcel QR/code (optional manual handoff fallback)</label>
                        <input id="start-parcel-reference" name="parcel_reference" maxlength="100" placeholder="EZP:…" autocomplete="off">
                    </div>
                    <input type="hidden" name="method" value="manual">
                    <button class="ops-btn ops-btn--primary ops-btn--lg" @disabled($deliveryProgress['scheduled_at']?->isFuture() || $deliveryProgress['return_required'])>Start delivery</button>
                </form>
            @else
                <div class="ops-banner ops-banner--urgent">
                    <span>Waiting for Logistics to verify and release this parcel from the hub.</span>
                </div>
                <form class="ops-form" method="POST" action="{{ route('courier.orders.declineDeliveryAssignment', $order) }}" data-decision="courier.orders.declineDeliveryAssignment" data-decision-title="Decline delivery assignment?" data-decision-message="This returns the assignment to dispatch for review and reassignment." data-decision-label="Decline assignment" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
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
                @if ($deliveryProgress['payment_ready'])
                    <div class="ops-panel">
                        <h3>Complete delivery</h3>
                        <form class="ops-form" method="POST" action="{{ route('courier.orders.completeDelivery', $order) }}" enctype="multipart/form-data" data-decision="courier.orders.completeDelivery" data-decision-title="Confirm successful delivery?" data-decision-message="Continue only after handing the parcel to the recipient and recording the required delivery evidence and COD details." data-decision-label="Confirm delivery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
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
                            @if ($order->payment_method === 'COD')
                            <div class="ops-field">
                                <label for="cod-collected-amount">Cash collected (₱{{ number_format($order->total_amount, 2) }})</label>
                                <input id="cod-collected-amount" name="cod_collected_amount" type="number" min="0" step="0.01" required value="{{ $order->total_amount }}">
                            </div>
                            @else<p>Wallet payment is verified. Do not collect cash.</p>@endif
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
                    <form class="ops-form" method="POST" action="{{ route('courier.orders.failDelivery', $order) }}" data-decision="courier.orders.failDelivery" data-decision-title="Record failed delivery?" data-decision-message="Check the failure reason and evidence. This records a delivery attempt against the parcel." data-decision-label="Record failed attempt" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
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
