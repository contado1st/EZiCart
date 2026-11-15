@extends('layouts.app')
@section('title', 'Correction reviews | EZiCart')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="web-operations">
    @include('components.feature-feedback')
    <header class="dash-header"><div><h1 class="dash-title">Correction reviews</h1><p class="dash-subtitle">Evidence, administrator decisions and the actual parcel workflow remain separate.</p></div>@if($actor->role === 'sorting_center')<a class="dash-btn-sm" href="{{ route('logistics.tracking') }}">Find a parcel to request review</a>@endif</header>
    <section class="dash-panel">
        <form class="web-form" method="GET"><label>Status<select name="status">@foreach(['OPEN' => 'Open reviews', 'RESOLVED' => 'Resolved through recorded workflow', 'REJECTED' => 'Rejected', 'all' => 'All history'] as $value => $label)<option value="{{ $value }}" @selected(request('status', 'OPEN') === $value)>{{ $label }}</option>@endforeach</select></label><label>Order reference<input name="search" maxlength="100" value="{{ request('search') }}"></label><button class="dash-btn-sm" type="submit">Filter reviews</button></form>
        <p>{{ $corrections->total() }} matching reviews. Requests and decisions cannot edit a parcel status, replace scan/proof records or post money.</p>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th scope="col">Request / order</th><th scope="col">Hub / requester</th><th scope="col">Requested</th><th scope="col">Review state</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($corrections as $correction)<tr><td>#{{ $correction->id }} · {{ $correction->order->order_number }}</td><td>{{ $correction->hub->business_name ?: trim($correction->hub->first_name.' '.$correction->hub->last_name) }}<br>{{ $correction->requester->first_name }} {{ $correction->requester->last_name }}</td><td>{{ $correction->created_at->format('d M Y H:i') }}</td><td>{{ $correction->status }}@if($correction->reviewed_at)<br>{{ $correction->reviewer->first_name }} {{ $correction->reviewer->last_name }} · {{ $correction->reviewed_at->format('d M Y H:i') }}@endif</td><td><a class="dash-btn-sm" href="{{ route('logistics-corrections.show', $correction) }}">View review</a></td></tr>
        @empty<tr><td colspan="5">No matching reviews. @if($actor->role === 'sorting_center')Open a parcel in Delivery tracking to request a review.@else Choose All history to inspect completed decisions.@endif</td></tr>@endforelse
        </tbody></table></div>{{ $corrections->links() }}
    </section>
</main>
@endsection
