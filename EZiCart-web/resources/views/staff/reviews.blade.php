@extends('layouts.app')
@section('title', 'Profile change approvals | EZiCart')
@section('content')
<main class="web-operations profile-review-page">
    <header class="dash-header"><div><h1 class="dash-title">Profile change approvals</h1><p class="dash-subtitle">{{ auth()->user()->isCommerceAdmin() ? 'Review seller profile and contact updates.' : 'Review rider and sorting center profile and contact updates.' }} Changes take effect only after approval.</p></div></header>
    @include('components.feature-feedback')
    <form method="GET" class="workspace-filter-form"><label for="profile-review-status">Request status<select id="profile-review-status" name="status">@foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All requests'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label><button type="submit" class="dash-btn-sm dash-btn-outline">Filter requests</button></form>
    <section class="dash-panel"><h2>{{ $revisions->total() }} {{ Str::plural('request', $revisions->total()) }}</h2>
        <div class="dash-table-wrapper"><table class="dash-table profile-review-table" role="table" aria-label="Profile change requests">
            <thead role="rowgroup"><tr role="row"><th scope="col">Account / Submitted</th><th scope="col">Current and requested changes</th><th scope="col">Status</th><th scope="col">Review decision</th></tr></thead>
            <tbody role="rowgroup">
            @forelse($revisions as $revision)
                <tr role="row">
                    <td role="cell" data-label="Account / Submitted"><a href="{{ route('staff.show', $revision->user) }}"><strong>{{ $revision->user->first_name }} {{ $revision->user->last_name }}</strong></a><p>{{ str_replace('_', ' ', $revision->user->role) }}</p><p>{{ $revision->user->email }}</p><time class="web-muted" datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->format('d M Y, h:i A') }}</time></td>
                    <td role="cell" data-label="Current and requested changes">@include('account.profile-change-values', ['revision' => $revision])</td>
                    <td role="cell" data-label="Status"><strong>{{ ucfirst($revision->status) }}</strong>@if($revision->reviewed_at)<p class="web-muted">{{ $revision->reviewed_at->format('d M Y, h:i A') }}<br>{{ $revision->reviewer?->first_name ?: 'Administrator' }}</p>@endif</td>
                    <td role="cell" data-label="Review decision">
                        @if($revision->status === 'pending')
                            <form method="POST" action="{{ route($reviewRoute, $revision) }}" class="profile-review-decision" data-decision="profile-review" data-decision-title="Record this profile decision?" data-decision-message="Approval replaces the verified details with the requested values. Rejection keeps the current details." data-decision-label="Record decision" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                @csrf @method('PATCH')<input type="hidden" name="profile_revision_id" value="{{ $revision->id }}">
                                <label for="profile-review-reason-{{ $revision->id }}">Reason (required to reject)</label><textarea id="profile-review-reason-{{ $revision->id }}" name="reason" rows="2" minlength="5" maxlength="1000">{{ in_array(old('profile_revision_id'), [$revision->id, (string) $revision->id], true) && is_string(old('reason')) ? old('reason') : '' }}</textarea>
                                <div class="web-actions"><button class="dash-btn-sm dash-btn-primary" type="submit" name="decision" value="approve">Approve</button><button class="dash-btn-sm dash-btn-outline" type="submit" name="decision" value="reject">Reject</button></div>
                            </form>
                        @else<p>{{ $revision->reason ?: 'Review completed.' }}</p>@endif
                    </td>
                </tr>
            @empty<tr role="row"><td role="cell" colspan="4">@if($revisions->total() > 0)<p>No profile change requests on this page.</p><a class="dash-btn-sm dash-btn-outline" href="{{ $revisions->url(1) }}">Return to first page</a>@else<p>No {{ $status === 'all' ? '' : $status }} profile change requests.</p>@endif</td></tr>@endforelse
            </tbody>
        </table></div>
        {{ $revisions->links() }}
    </section>
</main>
@endsection
