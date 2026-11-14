@extends(isset($applicant) ? 'layouts.applicant' : 'layouts.app')
@php($draftRoutePrefix = $draftRoutePrefix ?? 'seller.product-drafts')

@push('styles')
    @vite('resources/css/shared/app.css')
@endpush

@section('content')
    <div class="admin-container u-extracted-6c0f8d518b">
        <div class="admin-header-row">
            <div>
                <h1 class="admin-page-title">{{ isset($draft) ? 'Continue product draft' : 'Add New Product' }}</h1>
                <p class="admin-page-desc">New listings remain hidden from buyers until Product Compliance approves them.</p>
            </div>
            <a href="{{ isset($applicant) ? route('applicant-drafts.index') : route('seller.products.index') }}" class="u-extracted-4839300e95">← {{ isset($applicant) ? 'Back to private drafts' : 'Back to Inventory' }}</a>
        </div>

        <div class="admin-table-wrapper u-extracted-a4812596aa">
            <form action="{{ isset($applicant) ? (isset($draft) ? route($draftRoutePrefix.'.update', $draft) : route($draftRoutePrefix.'.store')) : route('seller.products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if(isset($draft))<input type="hidden" name="draft_id" value="{{ $draft->id }}">@endif
                @if(session('success'))<p class="dash-alert-success" role="status">{{ session('success') }}</p>@endif
                @if($errors->any())<div class="product-media-validation" role="alert"><strong>Check your product details and media.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Please select new upload files again after correcting errors.</p></div>@endif

                <div class="form-group">
                    <label class="form-label" for="product-name">Product Name *</label>
                    <input id="product-name" type="text" name="name" class="form-control" value="{{ old('name', $draftValues['name'] ?? '') }}" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="product-category">Category *</label>
                        <select id="product-category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            @foreach(config('web-commerce.categories') as $category)<option value="{{ $category }}" @selected(old('category', $draftValues['category'] ?? '') === $category)>{{ $category }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cover-image">Cover image (optional)</label>
                        <input id="cover-image" type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>

                @include('seller.products.media-fields')
                @if(isset($draft) && $draft->uploads)
                    <section class="web-section"><h2>Saved private media</h2><p>Saved media is retained for submission. New gallery uploads replace the saved gallery. Changing a variation's attributes requires selecting its image again.</p><ul>
                    @foreach($draft->uploads as $field => $upload)<li><a href="{{ route($draftRoutePrefix.'.media', [$draft, $field]) }}">{{ $upload['name'] }}</a> <label><input type="checkbox" name="clear_draft_media[]" value="{{ $field }}"> Remove saved file</label></li>@endforeach
                    </ul></section>
                @endif
                @include('seller.products.weight-field')

                @include('seller.products.pricing-fields')

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" aria-label="Product description">{{ old('description', $draftValues['description'] ?? '') }}</textarea>
                </div>

                @include('seller.products.variation-fields')
                <div class="web-actions">@if(isset($applicant))<button type="button" class="dash-btn-sm" disabled>Submission available after approval</button>@else<button type="submit" class="btn-primary u-extracted-1c5da340eb">Submit for compliance review</button>@endif<button type="submit" class="dash-btn-sm dash-btn-outline" formaction="{{ isset($draft) ? route($draftRoutePrefix.'.update', $draft) : route($draftRoutePrefix.'.store') }}" formnovalidate>Save private draft</button></div>
            </form>
        </div>
    </div>
@endsection
