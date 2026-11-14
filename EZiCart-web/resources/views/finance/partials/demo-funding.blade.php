<section class="dash-panel" id="wallet-funding">
    <h2>Add demo funds</h2>
    <p>Choose demo credits or record a demo cash deposit for school testing. Requests do not change your balance. An independent administrator reviews them; no real cash or provider transfer is involved.</p>
    @unless($demoEnabled)<p role="status">Demo funding is disabled. Ask the project administrator to enable the local/testing financial sandbox before submitting requests.</p>@endunless
    <form class="grid gap-4 md:grid-cols-2 my-4" method="POST" enctype="multipart/form-data" action="{{ route('wallet.demo-funding.store') }}" data-decision="demo_funding_request" data-decision-title="Submit this demo funding request?" data-decision-message="No funds are added until an independent administrator approves. These are school demo credits only." data-decision-label="Submit request" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        @csrf<input type="hidden" name="request_key" value="{{ old('request_key', Str::uuid()) }}">
        <label class="grid gap-1" for="demo-funding-kind">Request type<select id="demo-funding-kind" name="kind" required><option value="demo_credit" @selected(old('kind') === 'demo_credit')>Demo credits</option><option value="demo_cash_deposit" @selected(old('kind') === 'demo_cash_deposit')>Record demo cash deposit</option></select></label>
        <label class="grid gap-1" for="demo-funding-amount">Demo amount PHP<input id="demo-funding-amount" name="amount" type="number" min="0.01" max="1000000" step="0.01" required value="{{ old('amount') }}"></label>
        <label class="grid gap-1 md:col-span-2" for="demo-funding-reason">Request reason<textarea id="demo-funding-reason" name="reason" required minlength="5" maxlength="500" rows="2">{{ old('reason') }}</textarea></label>
        <label class="grid gap-1" for="demo-funding-receipt">Demo receipt reference <span>Required for a demo cash deposit</span><input id="demo-funding-receipt" name="receipt_reference" minlength="3" maxlength="120" value="{{ old('receipt_reference') }}"></label>
        <label class="grid gap-1" for="demo-funding-evidence">Private demo receipt <span>Required for a demo cash deposit. JPG, PNG or PDF; up to 5 MB. Use synthetic details.</span><input id="demo-funding-evidence" name="evidence" type="file" accept="image/jpeg,image/png,application/pdf"></label>
        <div class="md:col-span-2"><button type="submit" class="dash-btn-sm" @disabled(! $demoEnabled || $available->frozen)>Request demo funds</button></div>
    </form>
    <h3>Requests and administrator decisions</h3>
    <p>Requests expire after 24 hours. Expired requests cannot be approved; submit a new request. Each balance credit is posted once.</p>
    <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Request / source</th><th>Account</th><th>Demo amount PHP</th><th>Status / review</th><th>Review action</th></tr></thead><tbody>
    @forelse($intents as $intent)
        <tr><td>{{ $intent->reference }}<br>{{ $intent->provider === 'internal_demo' ? str_replace('_', ' ', $intent->request_kind) : 'Legacy signed mock funding' }}
            @if($intent->provider === 'internal_demo')
                <p>{{ $intent->request_details['reason'] ?? '' }}</p>
                @if($intent->request_details['receipt_reference'] ?? null)
                    <p>Demo receipt: {{ $intent->request_details['receipt_reference'] }}</p>
                @endif
                @if($intent->request_details['evidence_path'] ?? null)
                    <a class="dash-btn-sm" href="{{ route('wallet.demo-funding.evidence', $intent) }}">Download private demo receipt</a>
                @endif
            @endif
        </td><td>{{ $intent->user?->first_name }} {{ $intent->user?->last_name }} #{{ $intent->user_id }}</td><td>{{ number_format($intent->amount_cents / 100, 2) }}</td><td>{{ $intent->status === 'PENDING' && $intent->expires_at->isPast() ? 'Expired' : ($intent->provider === 'internal_demo' && $intent->status === 'PAID' ? 'Approved — credited' : $intent->status) }}
            @if($intent->reviewed_at)<p>Reviewed {{ $intent->reviewed_at->format('M d, Y h:i A') }} by account #{{ $intent->reviewed_by }}</p><p>{{ $intent->review_reason }}</p>@endif
        </td><td>
            @if($intent->provider === 'internal_demo' && $intent->status === 'PENDING' && $intent->expires_at->isFuture() && $demoFunding->canReview($actor, $intent))
                <form class="grid gap-2" method="POST" action="{{ route('wallet.demo-funding.review', $intent) }}" data-decision="demo_funding_review" data-decision-title="Record this demo funding decision?" data-decision-message="Approval credits the exact requested demo amount once. Check the source and any private demo receipt first." data-decision-label="Record decision" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf<label for="funding-decision-{{ $intent->id }}">Decision</label><select id="funding-decision-{{ $intent->id }}" name="decision" required><option value="approve">Approve demo funds</option><option value="reject">Reject request</option></select>
                    <label for="funding-review-{{ $intent->id }}">Review reason</label><textarea id="funding-review-{{ $intent->id }}" name="reason" minlength="5" maxlength="500" rows="2" required></textarea><button class="dash-btn-sm" type="submit">Record decision</button>
                </form>
            @elseif($intent->provider === 'sandbox' && $actor->isCommerceAdmin() && in_array($intent->status, ['PENDING', 'PAID'], true))
                <details><summary>Legacy mock event controls</summary><form class="grid gap-2" method="POST" action="{{ route('finance.wallet.simulate', $intent) }}">@csrf<label for="legacy-result-{{ $intent->id }}">Mock result</label><select id="legacy-result-{{ $intent->id }}" name="result">@if($intent->status === 'PENDING')<option value="paid">Mock payment paid</option><option value="failed">Mock payment failed</option>@else<option value="reversed">Mock provider reversal</option>@endif</select><button type="submit" class="dash-btn-sm">Simulate event</button></form></details>
            @else
                {{ $intent->status === 'PENDING' && $intent->expires_at->isFuture() ? 'Awaiting independent administrator review' : 'No further action' }}
            @endif
        </td></tr>
    @empty<tr><td colspan="5">No funding requests. Submit a request above to add demo funds after approval.</td></tr>@endforelse
    </tbody></table></div>{{ $intents->links() }}
</section>
