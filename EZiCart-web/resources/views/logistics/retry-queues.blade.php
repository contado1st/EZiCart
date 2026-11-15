<section class="ops-panel" id="return-required">
    <h2>Return required <span class="ops-muted">{{ $returnRequired->total() }} parcels</span></h2>
    <p>At least {{ max(1, (int) config('logistics.maximum_delivery_attempts', 3)) }} failed attempts are recorded. This is an attention queue, not proof of physical return. No further delivery attempt is allowed; use scanned hub intake, return-rider assignment and verified handoff.</p>
    <div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Order</th><th>Current parcel stage</th><th>Attempt history</th><th>Next physical step</th></tr></thead><tbody>
    @forelse($returnRequired as $parcel)
        <tr><td>{{ $parcel->order_number }}</td><td>{{ str_replace('_', ' ', $parcel->status) }}</td><td><details><summary>{{ $parcel->deliveryAttempts->where('outcome', 'failed')->count() }} failed attempts</summary><ol>@foreach($parcel->deliveryAttempts->where('outcome', 'failed')->sortBy('attempt_no') as $attempt)<li>Attempt {{ $attempt->attempt_no }} · {{ str_replace('_', ' ', $attempt->reason ?? 'Reason not recorded') }} · {{ $attempt->attempted_at?->format('d M Y H:i') ?? 'Time not recorded' }}@if($attempt->notes)<p>{{ $attempt->notes }}</p>@endif</li>@endforeach</ol></details></td><td>
            @if(in_array($parcel->status, ['DELIVERY_FAILED', 'OUT_FOR_DELIVERY'], true)) Scan the physically returned parcel into this hub's Returns or Exception location.
            @elseif($parcel->status === 'SORTED') Verify scanned hub custody, then use dispatch to assign a return rider and verify physical handoff.
            @else Verify the assigned return rider's handoff and use Start seller return; do not start another buyer-delivery attempt.
            @endif
        </td></tr>
    @empty<tr><td colspan="4">No parcels currently require seller return because of the attempt limit.</td></tr>@endforelse
    </tbody></table></div>{{ $returnRequired->fragment('return-required')->links() }}
</section>

<section class="ops-panel" id="retry-requests">
    <h2>Rider retry proposals <span class="ops-muted">{{ $retryRequests->total() }} pending</span></h2>
    <p>A proposal does not authorize a new attempt. Scheduling rechecks current hub custody, attempt limit, coverage, rider eligibility and cargo capacity through normal dispatch. Reject with a reason if a retry is unsuitable.</p>
    <div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Order / rider</th><th>Proposal</th><th>Review and dispatch</th></tr></thead><tbody>
    @forelse($retryRequests as $retry)
        <tr><td>{{ $retry->order->order_number }}<div class="ops-muted">{{ $retry->rider->first_name }} {{ $retry->rider->last_name }} · Failed attempt {{ $retry->failedAttempt->attempt_no }}</div></td><td>{{ $retry->proposed_for->format('d M Y H:i') }}<p>{{ $retry->reason }}</p><span class="ops-muted">Current parcel stage: {{ str_replace('_', ' ', $retry->order->status) }}</span></td><td>
            <details><summary>Review proposal</summary>
                @if($retry->order->status === 'SORTED')
                    <form class="ops-form" method="POST" action="{{ route('logistics.orders.assignRider', $retry->order) }}" data-decision="schedule-retry" data-decision-title="Schedule this retry?" data-decision-message="This uses normal dispatch checks. The rider must still accept and receive a verified physical hub handoff." data-decision-label="Schedule and assign">
                        @csrf<input type="hidden" name="retry_request_id" value="{{ $retry->id }}">
                        <div class="ops-field"><label for="retry-rider-{{ $retry->id }}">Assign eligible rider</label><select id="retry-rider-{{ $retry->id }}" name="delivery_courier_id" required><option value="">Choose rider</option>@foreach($riders->filter(fn ($rider) => $rider->serviceAreas->contains('id', $retry->order->destination_area_id)) as $rider)<option value="{{ $rider->id }}">{{ $rider->first_name }} {{ $rider->last_name }} · {{ $rider->capacity_load_count }} active jobs</option>@endforeach</select></div>
                        <div class="ops-field"><label for="retry-schedule-{{ $retry->id }}">Confirmed start ({{ config('app.timezone') }})</label><input id="retry-schedule-{{ $retry->id }}" name="scheduled_at" type="datetime-local" value="{{ $retry->proposed_for->isFuture() ? $retry->proposed_for->format('Y-m-d\TH:i') : '' }}" required></div>
                        <div class="ops-field"><label for="retry-coverage-{{ $retry->id }}">Coverage override reason, if required</label><input id="retry-coverage-{{ $retry->id }}" name="coverage_override_reason" maxlength="500"></div>
                        <button class="ops-btn ops-btn--primary" type="submit">Schedule and assign</button>
                    </form>
                @else<p class="ops-muted">Parcel stage changed. Review its current custody before deciding; the proposal cannot be scheduled from this stage.</p>@endif
                <form class="ops-form" method="POST" action="{{ route('logistics.retry-requests.reject', $retry) }}" data-decision="reject-retry" data-decision-title="Reject this retry proposal?" data-decision-message="The rider will receive your reason. Parcel custody and attempt records remain unchanged." data-decision-label="Reject proposal">
                    @csrf<div class="ops-field"><label for="retry-reject-{{ $retry->id }}">Reason for rejection</label><input id="retry-reject-{{ $retry->id }}" name="reason" minlength="5" maxlength="1000" required></div><button class="ops-btn ops-btn--danger" type="submit">Reject proposal</button>
                </form>
            </details>
        </td></tr>
    @empty<tr><td colspan="3">No pending rider retry proposals for this hub.</td></tr>@endforelse
    </tbody></table></div>{{ $retryRequests->fragment('retry-requests')->links() }}
</section>
