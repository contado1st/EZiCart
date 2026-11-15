@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="web-operations">
    <header class="dash-header"><div><h1 class="dash-title">Return requests</h1><p class="dash-subtitle">Respond within 72 elapsed hours. Escalated cases are handled by administrators.</p></div></header>
    @include('components.feature-feedback')
    <section class="dash-panel">
        <form class="web-actions" method="GET"><label for="return-stage">Request stage</label><select id="return-stage" name="stage"><option value="">All requests</option>@foreach(['awaiting_seller' => 'Awaiting seller', 'seller_approved' => 'Seller approved', 'seller_rejected' => 'Seller rejected', 'escalated' => 'In Dispute', 'deadline_approved' => 'Auto-approved request', 'resolved' => 'Response closed', 'legacy' => 'Legacy admin-led'] as $key => $label)<option value="{{ $key }}" @selected($stage === $key)>{{ $label }}</option>@endforeach</select><button class="dash-btn-sm" type="submit">Filter</button></form>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Case / order</th><th>Buyer / reason</th><th>Stage</th><th>Deadline / action</th></tr></thead><tbody>
        @forelse($cases as $case)<tr><td>#{{ $case->id }} · {{ $case->order->order_number }}</td><td>{{ $case->buyer->first_name }} {{ $case->buyer->last_name }}<br>{{ $case->reason }}</td><td>{{ $case->sellerResponse?->label() ?? 'Administrator-led case' }}<br>Admin: {{ $case->status }}</td><td>{{ $case->sellerResponse?->response_due_at->timezone('Asia/Manila')->format('d M H:i') ?? 'No seller deadline' }}<div class="web-actions"><a class="dash-btn-sm" href="{{ route('returns.workflow', $case->order_id) }}">{{ $case->sellerResponse?->stage === 'awaiting_seller' && $case->status === 'PENDING' ? 'Respond to request' : 'View request' }}</a></div></td></tr>
        @empty<tr><td colspan="4">No return requests in this stage.</td></tr>@endforelse
        </tbody></table></div>{{ $cases->links() }}
    </section>
</main>
@endsection
