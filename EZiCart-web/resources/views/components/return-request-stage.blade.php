@props(['case', 'viewer'])
@if($case)
@php($response = $case->sellerResponse)
<section class="dash-panel" aria-label="Return request">
    <h2>Return request #{{ $case->id }}</h2>
    <p><strong>{{ $response?->label() ?? 'Administrator-led case' }}</strong> · Admin case: {{ str_replace('_', ' ', $case->status) }}</p>
    <p>{{ $case->reason }} — {{ $case->description }}</p>
    @if($case->evidence_path && ($viewer->isCommerceAdmin() || ($viewer->role === 'buyer' && $viewer->id === $case->buyer_id) || ($viewer->role === 'seller' && $viewer->id === $case->seller_id)))<p><a class="dash-btn-sm" href="{{ route('returns.request-evidence', $case) }}">Download private request evidence</a></p>@endif
    @if($response)
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Seller response deadline</dt><dd>{{ $response->response_due_at->timezone('Asia/Manila')->format('d M Y H:i:s') }} Philippine time</dd></div>
            @if($response->seller_responded_at)<div><dt>Seller responded</dt><dd>{{ $response->seller_responded_at->timezone('Asia/Manila')->format('d M Y H:i:s') }}</dd></div>@endif
        </dl>
        @if($response->seller_reason)<div class="dispute-evidence-box"><strong>Seller's exact reason</strong><p>{{ $response->seller_reason }}</p></div>@endif
        @if($response->stage === 'seller_rejected')
            <p>Escalate before {{ $response->escalation_due_at->timezone('Asia/Manila')->format('d M Y H:i:s') }} Philippine time (72 elapsed hours after rejection). {{ $response->escalation_due_at->isFuture() ? '' : 'This window has expired; closure is awaiting processing.' }}</p>
        @endif
        @if($response->stage === 'awaiting_seller' && $response->response_due_at->lte(now()))<p role="status">The seller deadline has passed. Automatic request approval will be recorded by deadline processing or the next response attempt.</p>@endif
        @if($viewer->role === 'seller' && $viewer->id === $case->seller_id && $case->status === 'PENDING' && $response->stage === 'awaiting_seller' && $response->response_due_at->isFuture())
            <div class="grid gap-4 sm:grid-cols-2">
                <form method="POST" action="{{ route('seller.returns.respond', $case) }}" data-decision="return-approve" data-decision-title="Approve this return request?" data-decision-message="This approves the request for administrator authorization. It does not move the parcel, restore stock or send a refund." onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf<input type="hidden" name="version" value="{{ $response->version }}"><input type="hidden" name="decision" value="approve"><button class="dash-btn-sm dash-btn-primary" type="submit">Approve request <span aria-label="Action required">1</span></button>
                </form>
                <form method="POST" action="{{ route('seller.returns.respond', $case) }}" class="grid gap-3" data-decision="return-reject" data-decision-title="Reject this request?" data-decision-message="The buyer will see your exact reason and have 72 hours to escalate to an administrator." onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf<input type="hidden" name="version" value="{{ $response->version }}"><input type="hidden" name="decision" value="reject"><label for="return-reason-{{ $case->id }}">Rejection reason (10–300 characters)</label><textarea id="return-reason-{{ $case->id }}" name="reason" class="search-input" rows="3" minlength="10" maxlength="300" required>{{ old('reason') }}</textarea><button class="dash-btn-sm" type="submit">Reject request</button>
                </form>
            </div>
        @endif
        @if($viewer->role === 'buyer' && $viewer->id === $case->buyer_id && $case->status === 'PENDING' && !$response->escalated_at)
            @if(($response->stage === 'seller_rejected' && $response->escalation_due_at->isFuture()) || in_array($response->stage, ['awaiting_seller', 'seller_approved', 'deadline_approved'], true))
                <form method="POST" action="{{ route('buyer.returns.escalate', $case) }}" data-decision="return-escalate" data-decision-title="Request administrator review?" data-decision-message="An administrator will take over this request. The seller cannot approve or reject after escalation." onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf<input type="hidden" name="version" value="{{ $response->version }}"><input type="hidden" name="urgent" value="{{ $response->stage === 'seller_rejected' ? 0 : 1 }}"><button class="dash-btn-sm dash-btn-primary" type="submit">{{ $response->stage === 'seller_rejected' ? 'Escalate' : 'Request urgent admin assistance' }}</button>
                </form>
            @endif
        @endif
        <p>Request approval, physical pickup, seller receipt, inspection and funded refund execution are separate steps. Do not send the parcel until authorized pickup instructions are recorded.</p>
    @endif
    @if($case->admin_notes)<p><strong>Administrator / closure note:</strong> {{ $case->admin_notes }}</p>@endif
    <a class="dash-btn-sm" href="{{ route('returns.workflow', $case->order_id) }}">Return progress and next steps</a>
</section>
@endif
