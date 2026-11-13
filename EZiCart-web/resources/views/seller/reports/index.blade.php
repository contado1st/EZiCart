@extends('layouts.app')
@section('title', 'Seller financial report | EZiCart')
@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/shared/reports.css')
@endpush
@section('content')
<main class="dash-main">
    <header class="dash-header"><div><h1 class="dash-title">Sales & Financial Report</h1>
        <p class="dash-subtitle">Orders placed from {{ $filters['from_date'] }} to {{ $filters['to_date'] }} · {{ config('app.timezone') }}. Current lifecycle and recorded adjustments.</p>
    </div></header>
    @include('components.feature-feedback')
    <div class="report-filter-bar">
        <form method="GET" action="{{ route('seller.reports.index') }}" class="report-filter-form">
            <label class="report-filter-group">From<input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="report-date-input" required></label>
            <label class="report-filter-group">To<input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="report-date-input" required></label>
            <button class="dash-btn-sm dash-btn-primary" type="submit">Filter records</button>
            <a class="dash-btn-sm" href="{{ route('seller.reports.index') }}">Reset</a>
        </form>
        <div class="report-actions-group">
            <a class="dash-btn-sm" href="{{ route('sales-reports.index', array_merge($filters, ['format' => 'csv'])) }}">Export CSV</a>
            <a class="dash-btn-sm" href="{{ route('sales-reports.index', array_merge($filters, ['format' => 'pdf'])) }}">Download PDF</a>
            <button class="report-print-btn" type="button" onclick="window.print()"><x-shop-icon name="print" /> Print this page</button>
        </div>
    </div>
    <section class="report-summary-banner" aria-label="Report totals for all matching orders">
        @foreach (['gross' => 'Gross merchandise activity', 'seller_discount' => 'Seller-funded discounts', 'refund' => 'Executed refunds (including shipping)', 'commission' => 'Recognized commission', 'net' => 'Net seller sales', 'pending_payout' => 'Pending recorded payouts', 'settled_payout' => 'Settled recorded payouts'] as $key => $label)
            <div class="report-summary-card"><div class="report-summary-title">{{ $label }}</div><div class="report-summary-value">₱{{ number_format($stats[$key] / 100, 2) }}</div></div>
        @endforeach
        <div class="report-summary-card"><div class="report-summary-title">Completed excluding full refunds / all orders</div><div class="report-summary-value">{{ $stats['completed_count'] }} / {{ $stats['total_orders'] }}</div></div>
    </section>
    <section class="dash-panel">
        <h2>How these figures reconcile</h2>
        <p>Net seller sales = gross merchandise activity − seller-funded discounts − merchandise refunds − platform subsidy reversals − recognized commission. Gross retains original completed/delivered-refunded activity; refunds remain separate. Cancelled or void orders contribute zero sales. Commission follows verified finance records, rather than a fixed percentage label. Authorized refunds awaiting execution do not count as money returned.</p>
        <p>These are sales figures, not profit or withdrawable cash. Product costs are not recorded. Payout requests retain completion, seven-day delivery, funding, remittance, dispute and administrator checks. Pending/settled payout figures describe actual recorded requests, separately from sales. Adjustments attached to the selected order cohort may have happened after its placement dates.</p>
        <p><a class="dash-btn-sm" href="{{ route('finance.index') }}">Review order payout eligibility</a> <a class="dash-btn-sm" href="{{ route('finance.wallet') }}">Wallet and withdrawal requests</a> <a class="dash-btn-sm" href="{{ route('sales-reports.index', $filters) }}">Itemized report and filters</a></p>
    </section>
    <section class="dash-panel report-table-section">
        <h2 class="report-table-title">Matching orders ({{ $orders->total() }})</h2>
        <p>Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }}. Summary totals and downloads cover every matching order; printing covers this page.</p>
        <div class="dash-table-wrapper"><table class="dash-table">
            <thead><tr><th>Order / placed</th><th>Status</th><th>Gross PHP</th><th>Seller discount PHP</th><th>Platform discount PHP</th><th>Refund PHP</th><th>Merchandise refund PHP</th><th>Subsidy reversal PHP</th><th>Commission PHP</th><th>Net seller sales PHP</th></tr></thead>
            <tbody>@forelse ($rows as $row)<tr><td><strong>{{ $row['order'] }}</strong><br>{{ $row['placed'] }}</td><td>{{ str_replace('_', ' ', $row['status']) }}</td>
                @foreach (['gross', 'seller_discount', 'platform_discount', 'refund', 'merchandise_refund', 'platform_reversal', 'commission', 'net'] as $key)<td>{{ number_format($row[$key] / 100, 2) }}</td>@endforeach
            </tr>@empty<tr><td colspan="10" class="dash-table-empty">No matching orders for these dates.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $orders->links() }}
    </section>
</main>
@endsection
