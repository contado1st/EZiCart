@extends('layouts.app')
@section('title', 'Request parcel correction review | EZiCart')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="web-operations">
    @include('components.feature-feedback')
    <header class="dash-header"><div><h1 class="dash-title">Request correction review</h1><p class="dash-subtitle">{{ $order->order_number }} · Current stage: {{ str_replace('_', ' ', $snapshot['status']) }}</p></div><a class="dash-btn-sm" href="{{ route('logistics-corrections.index') }}">Review history</a></header>
    <section class="dash-panel"><h2>Describe the discrepancy</h2><p>Explain the record that needs investigation and attach supporting evidence. This request records the current parcel state for comparison. It cannot mark delivery complete, collect cash, confirm custody or restore stock.</p>
        <form class="web-form" method="POST" enctype="multipart/form-data" action="{{ route('logistics-corrections.store', $order) }}" data-decision="parcel-correction-request" data-decision-title="Request correction review?" data-decision-message="This sends your reason and private evidence to logistics administration. It does not change the parcel." data-decision-label="Request review" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
            @csrf<label class="web-wide" for="correction-reason">Discrepancy and requested investigation<textarea id="correction-reason" name="reason" minlength="5" maxlength="500" required>{{ old('reason') }}</textarea></label>
            <label class="web-wide" for="correction-evidence">Supporting evidence — JPG, PNG or PDF, up to 5 MB<input id="correction-evidence" type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf" required></label>
            <button class="dash-btn-sm dash-btn-primary" type="submit">Submit correction review</button>
        </form>
    </section>
</main>
@endsection
