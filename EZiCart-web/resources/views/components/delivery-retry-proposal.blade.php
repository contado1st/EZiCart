@props(['order', 'context'])
@if($context['failures'])
    <div class="py-2">
        <p><strong>{{ $context['failures'] }} failed attempt(s)</strong> / {{ max(1, (int) config('logistics.maximum_delivery_attempts', 3)) }} permitted.</p>
        @if($context['request'])
            <p>My latest retry proposal: <strong>{{ ucfirst(strtolower($context['request']->status)) }}</strong> · Proposed {{ $context['request']->proposed_for->format('d M Y H:i') }}.</p>
            @if($context['request']->scheduled_for)<p>Scheduled by logistics: {{ $context['request']->scheduled_for->format('d M Y H:i') }}. Follow the actual assignment; logistics may choose a different rider.</p>@endif
            @if($context['request']->decision_reason)<p class="ops-muted">{{ $context['request']->decision_reason }}</p>@endif
        @endif
        @if($context['can_request'])
            <form class="ops-form" method="POST" action="{{ route('courier.retry-requests.store', $order) }}" data-decision="retry-proposal" data-decision-title="Propose a delivery retry?" data-decision-message="Logistics will review this proposal. It does not assign a rider, change custody or authorize a new attempt." data-decision-label="Send proposal">
                @csrf<input type="hidden" name="failed_attempt_id" value="{{ $context['latest_failure']->id }}">
                <div class="ops-field"><label for="retry-date-{{ $order->id }}">Preferred retry time ({{ config('app.timezone') }})</label><input id="retry-date-{{ $order->id }}" name="proposed_for" type="datetime-local" required></div>
                <div class="ops-field"><label for="retry-reason-{{ $order->id }}">Reason / recipient availability</label><input id="retry-reason-{{ $order->id }}" name="reason" minlength="5" maxlength="500" required></div>
                <button class="ops-btn ops-btn--primary" type="submit">Propose retry to logistics</button>
            </form>
        @else<p class="ops-muted">{{ $context['reason'] }}</p>@endif
    </div>
@endif
