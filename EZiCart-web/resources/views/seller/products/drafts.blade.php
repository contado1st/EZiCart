@extends(isset($applicant) ? 'layouts.applicant' : 'layouts.app')
@php($draftRoutePrefix = $draftRoutePrefix ?? 'seller.product-drafts')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="dash-main">
    <div class="dash-header"><div><h1 class="dash-title">Private product drafts</h1><p class="dash-subtitle">Saved work is visible only to you. {{ isset($applicant) ? 'Submission becomes available after account approval.' : 'Submit complete products for compliance review.' }}</p></div><div class="web-actions">@unless(isset($applicant))<a class="dash-btn-outline" href="{{ route('seller.products.index') }}">Inventory</a>@endunless<a class="dash-btn-primary" href="{{ isset($applicant) ? route('applicant-drafts.create') : route('seller.products.create') }}">Add product</a></div></div>
    @if(session('success'))<div class="dash-alert-success" role="status">{{ session('success') }}</div>@endif
    <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Draft</th><th>Last saved</th><th>Actions</th></tr></thead><tbody>
    @forelse($drafts as $draft)
        @php($draftImageField = isset($draft->uploads['image']) ? 'image' : collect(array_keys($draft->uploads ?? []))->first(fn ($field) => str_starts_with($field, 'gallery_images.')))
        <tr><td><x-product-table-item :name="($draft->payload['name'] ?? '') ?: 'Untitled product'" :src="$draftImageField ? route($draftRoutePrefix.'.media', [$draft, $draftImageField]) : null" /></td><td>{{ $draft->updated_at->format('d M Y, H:i') }}</td><td><div class="web-actions"><a class="dash-btn-sm dash-btn-outline" href="{{ route($draftRoutePrefix.'.edit', $draft) }}">Continue editing</a><form method="POST" action="{{ route($draftRoutePrefix.'.destroy', $draft) }}" data-decision="delete-draft" data-decision-title="Delete private draft?" data-decision-message="This removes this saved draft and its private media." onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf @method('DELETE')<button class="dash-btn-sm dash-btn-danger" type="submit">Delete draft</button></form></div></td></tr>
    @empty<tr><td colspan="3">No private drafts yet. Use Save private draft on the Add Product form.</td></tr>@endforelse
    </tbody></table></div>{{ $drafts->links() }}
</main>
@endsection
