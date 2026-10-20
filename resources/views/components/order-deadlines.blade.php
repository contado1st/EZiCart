@php($pendingCancellation = $order->pendingCancellation)
@if ($pendingCancellation)
<section class="order-deadline-panel"><h2>Cancellation pending</h2><p>{{ $pendingCancellation->reason }}</p><p>Seller response due: {{ $pendingCancellation->respond_by->format('M d, Y h:i A T') }} · <time data-order-deadline="{{ $pendingCancellation->respond_by->toIso8601String() }}">{{ $pendingCancellation->respond_by->diffForHumans() }}</time></p><p>Fulfillment is paused. Unanswered requests are approved automatically after this deadline.</p>
@if (auth()->id() === $order->seller_id)<form class="feature-form" method="POST" action="{{ route('seller.orders.cancellation', $order) }}">@csrf<label>Response<textarea name="response" required minlength="5" maxlength="500"></textarea></label><button class="feature-button" name="decision" value="approve">Approve cancellation</button><button class="feature-button" name="decision" value="reject">Reject cancellation</button></form>@endif</section>
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
        <section class="order-deadline-panel"><h2>Rider acceptance pending</h2><p><time data-order-deadline="{{ $acceptBy->toIso8601String() }}">{{ $acceptBy->format('M d, Y h:i A T') }}</time>. Unaccepted assignments return to logistics for reassignment.</p>@if (auth()->id() === $order->delivery_courier_id)<form method="POST" action="{{ route('courier.orders.accept-assignment', $order) }}">@csrf<button class="feature-button" type="submit">Accept assignment</button></form>@endif</section>
    @endif
@endif
