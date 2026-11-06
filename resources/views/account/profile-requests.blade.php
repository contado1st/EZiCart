@if($profileRevisions !== null)
<section class="dash-panel web-section" id="profile-requests" aria-labelledby="profile-requests-title">
    <h2 id="profile-requests-title">Profile change requests</h2>
    <p>Changes to your profile, appearance and contact details require {{ $user->role === 'seller' ? 'marketplace' : 'logistics' }} admin approval. Your verified details stay active until approval. Only one request may be pending at a time.</p>
    @forelse($profileRevisions as $revision)
        <article class="profile-request-history">
            <div class="web-actions"><strong>{{ ucfirst($revision->status) }}</strong><time class="web-muted" datetime="{{ $revision->created_at->toIso8601String() }}">Submitted {{ $revision->created_at->format('d M Y, h:i A') }}</time></div>
            <details><summary>View requested changes</summary>@include('account.profile-change-values', ['revision' => $revision])</details>
            @if($revision->reviewed_at)<p class="web-muted">Reviewed {{ $revision->reviewed_at->format('d M Y, h:i A') }} by {{ $revision->reviewer?->first_name ?: 'Administrator' }}</p>@endif
            @if($revision->reason)<p>{{ $revision->reason }}</p>@endif
        </article>
    @empty<p class="web-muted">No profile changes submitted yet.</p>@endforelse
    {{ $profileRevisions->fragment('profile-requests')->links() }}
</section>
@endif
