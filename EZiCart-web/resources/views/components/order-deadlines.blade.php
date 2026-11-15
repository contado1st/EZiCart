@include('components.order-progress', ['order' => $order])
<p class="web-section"><a class="feature-button" href="{{ route('parcel-evidence.show', $order) }}">View parcel journey and event photos</a></p>
@php($pendingCancellation = $order->pendingCancellation)
@if($pendingCancellation)<p class="web-section">Product stock and any voucher use remain reserved until cancellation is approved. Approval restores eligible usage once; manually ended or expired vouchers remain unavailable. Any recorded sandbox wallet payment is returned after approval.</p>
@elseif($order->status === 'CANCELLED' && $order->voucher_code)<p class="web-section">This order's original voucher discount remains in its history. Check <a href="{{ route('buyer.vouchers.index', ['view' => 'saved']) }}">saved vouchers</a> for current eligibility; a cancellation does not renew an expired or manually ended offer.</p>@endif
@if ($pendingCancellation)
<section class="order-deadline-panel"><h2>Cancellation pending</h2><p>{{ $pendingCancellation->reason }}</p><p>Seller response due: {{ $pendingCancellation->respond_by->format('M d, Y h:i A T') }} · <time data-order-deadline="{{ $pendingCancellation->respond_by->toIso8601String() }}">{{ $pendingCancellation->respond_by->diffForHumans() }}</time></p><p>Fulfillment is paused. Unanswered requests are approved automatically after this deadline.</p>
@if (auth()->id() === $order->seller_id)<form class="feature-form" method="POST" action="{{ route('seller.orders.cancellation', $order) }}" data-decision="seller.orders.cancellation" data-decision-title="Submit cancellation decision?" data-decision-message="Check your selected decision and response. Approval cancels the order and releases its reserved stock." data-decision-label="Submit decision" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<label>Response<textarea name="response" required minlength="5" maxlength="500"></textarea></label><button class="feature-button" name="decision" value="approve">Approve cancellation</button><button class="feature-button" name="decision" value="reject">Reject cancellation</button></form>@endif</section>
@elseif ($order->status === 'PLACED' && $order->seller_response_due_at)
<p class="order-deadline-panel">Seller confirmation: <time data-order-deadline="{{ $order->seller_response_due_at->toIso8601String() }}">{{ $order->seller_response_due_at->format('M d, Y h:i A T') }}</time>. Unconfirmed orders are cancelled automatically after the deadline.</p>
@endif
@if (in_array($order->status, ['CONFIRMED', 'PREPARING'], true) && !$pendingCancellation && $order->preparation_due_at)
<p class="order-deadline-panel">Seller preparation: <time data-order-deadline="{{ $order->preparation_due_at->toIso8601String() }}">{{ $order->preparation_due_at->format('M d, Y h:i A T') }}</time>. Orders not prepared before the deadline are cancelled automatically before physical custody.</p>
@endif
@if ($order->status === 'ASSIGNED_TO_RIDER')
    @php($assignment = $order->deliveryAssignments->where('status', 'active')->first())
    @if ($assignment && !$assignment->accepted_at && !$order->hub_released_at)
        @php($acceptBy = $assignment->assigned_at->copy()->addMinutes((int) config('commerce.rider_acceptance_minutes', 15)))
        <section class="order-deadline-panel"><h2>Rider acceptance pending</h2><p><time data-order-deadline="{{ $acceptBy->toIso8601String() }}">{{ $acceptBy->format('M d, Y h:i A T') }}</time>. Unaccepted assignments return to logistics for reassignment.</p>@if (auth()->id() === $order->delivery_courier_id)<form method="POST" action="{{ route('courier.orders.accept-assignment', $order) }}" data-decision="courier.orders.accept-assignment" data-decision-title="Accept assignment?" data-decision-message="Confirm that you can carry out this parcel assignment." data-decision-label="Accept assignment" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button class="feature-button" type="submit">Accept assignment</button></form>@endif</section>
    @endif
@endif
