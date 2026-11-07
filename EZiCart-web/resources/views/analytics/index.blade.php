@extends('layouts.app')
@section('title', $report['title'].' | EZiCart')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@endpush
@section('content')
@php
    $formatMetric = static fn ($value, $unit) => $value === null ? 'No data' : ($unit === 'money' ? '₱'.number_format($value, 2) : number_format($value, $unit === 'hours' ? 2 : 0).($unit === 'hours' ? ' hrs' : ''));
    $chartData = ['trend' => $report['trend'], 'statuses' => $report['statuses'], 'commercial' => $report['commercial'], 'groups' => collect($report['sections'])->whereIn('key', ['products', 'categories', 'sellers', 'locations'])->map(fn ($section) => ['key' => $section['key'], 'title' => $section['title'], 'rows' => array_slice($section['rows'], 0, 10)])->values()->all()];
@endphp
<div class="analytics-page">
    <div class="analytics-print-brand">EZiCart · Analytics report</div>
    <header class="analytics-heading"><div><h1>{{ $report['title'] }}</h1><p>{{ $report['filters']['from'] }} — {{ $report['filters']['to'] }} · {{ $report['timezone'] }} · PHP</p></div>
        <div class="analytics-actions"><button type="button" data-analytics-print><x-shop-icon name="print" /> Print charts</button>
        @foreach(['xlsx' => 'Excel workbook', 'csv' => 'CSV', 'pdf' => 'Full PDF report'] as $extension => $label)<a href="{{ route('analytics.export', [...$report['filters'], 'format' => $extension]) }}"><x-shop-icon name="note" /> {{ $label }}</a>@endforeach</div>
    </header>
    <p class="analytics-print-scope">Chart summary · {{ \Illuminate\Support\Str::headline($report['role']) }} workspace. @foreach(collect($report['filters'])->except(['from', 'to', 'comparison', 'interval'])->filter(fn ($value) => $value !== null && $value !== '') as $key => $value){{ \Illuminate\Support\Str::headline($key) }}: {{ $value }}; @endforeach Full source records are included in the Excel, CSV and PDF downloads.</p>
    @include('components.feature-feedback')
    <form class="analytics-filters" method="GET" action="{{ route('analytics.index') }}" data-analytics-filter>
        <div class="analytics-filter-main">
            <label>From<input type="date" name="from" value="{{ $report['filters']['from'] }}" required></label>
            <label>To<input type="date" name="to" value="{{ $report['filters']['to'] }}" required></label>
            <label>Group trend by<select name="interval">@foreach(['day', 'week', 'month', 'quarter', 'year'] as $interval)<option value="{{ $interval }}" @selected($report['filters']['interval'] === $interval)>{{ ucfirst($interval) }}</option>@endforeach</select></label>
            <label>Compare with<select name="comparison">@foreach(['previous' => 'Previous equal period', 'year' => 'Same dates last year', 'none' => 'No comparison'] as $value => $label)<option value="{{ $value }}" @selected($report['filters']['comparison'] === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="analytics-primary" type="submit">Apply filters <x-shop-icon name="search" /></button><a href="{{ route('analytics.index') }}">Reset</a>
        </div>
        <details class="analytics-advanced" @if(collect($report['filters'])->except(['from','to','interval','comparison'])->filter()->isNotEmpty()) open @endif><summary>More filters</summary><div class="analytics-filter-main">
            @if(auth()->user()->isCommerceAdmin())<label>Seller ID<input type="number" name="seller_id" min="1" value="{{ $report['filters']['seller_id'] ?? '' }}"></label>@endif
            @if(auth()->user()->isCommerceAdmin() || auth()->user()->isLogisticsAdmin() || auth()->user()->role === 'sorting_center')<label>Rider ID<input type="number" name="rider_id" min="1" value="{{ $report['filters']['rider_id'] ?? '' }}"></label>@endif
            <label>Product ID<input type="number" name="product_id" min="1" value="{{ $report['filters']['product_id'] ?? '' }}"></label>
            <label>Category<input name="category" maxlength="120" value="{{ $report['filters']['category'] ?? '' }}"></label>
            <label>Order status<select name="status"><option value="">All statuses</option>@foreach(\App\Enums\OrderStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($report['filters']['status'] ?? '') === $status->value)>{{ \Illuminate\Support\Str::headline(strtolower($status->value)) }}</option>@endforeach</select></label>
            <label>Municipality<input name="municipality" maxlength="120" value="{{ $report['filters']['municipality'] ?? '' }}"></label>
            <label>Barangay<input name="barangay" maxlength="120" value="{{ $report['filters']['barangay'] ?? '' }}"></label>
            <label>Payment record<select name="payment_status"><option value="">All payment records</option>@foreach(['verified' => 'Remittance verified', 'collected' => 'COD collected', 'uncollected' => 'No recorded collection', 'refunded' => 'Executed refund'] as $value => $label)<option value="{{ $value }}" @selected(($report['filters']['payment_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Payment method<input name="payment_method" maxlength="30" placeholder="e.g. COD" value="{{ $report['filters']['payment_method'] ?? '' }}"></label>
            <label>Voucher code<input name="voucher" maxlength="100" value="{{ $report['filters']['voucher'] ?? '' }}"></label>
        </div></details>
    </form>
    <p class="analytics-freshness">Updated {{ $report['generated_at'] }}. Figures use orders placed in the selected period and their current recorded state. @if($report['comparison'])Comparison: {{ $report['comparison']['from'] }} — {{ $report['comparison']['to'] }}.@endif</p>
    <section class="analytics-metrics" aria-label="Reporting period metrics">
        @foreach($report['metrics'] as $metric)<article><h2>{{ $metric['label'] }}</h2><strong>{{ $formatMetric($metric['value'], $metric['unit']) }}</strong>
            @if($report['comparison'])<p>@if($metric['change'] !== null)<span>{{ $metric['change'] > 0 ? '+' : '' }}{{ $metric['change'] }}%</span> vs comparison @else No percentage baseline @endif</p><small>Previous: {{ $formatMetric($metric['previous'], $metric['unit']) }}</small>@endif
        </article>@endforeach
    </section>
    <div data-analytics-charts><div data-skeleton-chart-loading hidden><p class="analytics-chart-loading" role="status">Loading interactive charts. All figures are available in the tables below.</p><x-loading-skeleton variant="charts" /></div><noscript><p class="analytics-chart-loading">Interactive charts require JavaScript. All figures are available in the tables below.</p></noscript></div>
    <script type="application/json" id="analytics-chart-data">@json($chartData)</script>
    @if(count($report['insights']))<section class="analytics-observations"><h2>Observations to review</h2>@foreach($report['insights'] as $insight)<p><strong>{{ $insight['Metric'] }}:</strong> {{ $insight['Observation'] }}</p>@endforeach</section>@endif
    <section class="analytics-section analytics-detail-section"><header><h2>Trend data</h2><p>Weekly and quarterly labels identify the group containing each selected date.</p></header><div class="analytics-table-scroll"><table><thead><tr><th scope="col">Period</th><th scope="col">Orders</th><th scope="col">Completed</th><th scope="col">Recorded delivery</th>@if($report['commercial'])<th scope="col">Net seller sales PHP</th>@endif</tr></thead><tbody>@foreach($report['trend'] as $point)<tr><td>{{ $point['period'] }}</td><td>{{ $point['orders'] }}</td><td>{{ $point['completed'] }}</td><td>{{ $point['delivered'] }}</td>@if($report['commercial'])<td>{{ number_format($point['net'], 2) }}</td>@endif</tr>@endforeach</tbody></table></div></section>
    @foreach($report['sections'] as $section)
        <section class="analytics-section analytics-detail-section" id="analytics-{{ $section['key'] }}"><header><h2>{{ $section['title'] }}</h2><p>{{ number_format(count($section['rows'])) }} {{ $section['key'] === 'orders' ? 'orders in this report' : 'rows' }}. @if($section['key'] !== 'orders' && count($section['rows']) > 20)Showing the first 20; exports include every row.@endif</p></header>
            <div class="analytics-table-scroll">@if(count($section['rows']))
            <table><thead><tr>@foreach(array_keys($section['rows'][0]) as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @foreach($section['key'] === 'orders' ? $orders : array_slice($section['rows'], 0, 20) as $row)<tr>@foreach($row as $heading => $value)<td>{{ is_float($value) ? number_format($value, 2) : $value }}</td>@endforeach</tr>@endforeach
            </tbody></table>@else<p class="analytics-empty">No matching records for this section.</p>@endif</div>
            @if($section['key'] === 'orders'){{ $orders->links() }}@endif
        </section>
    @endforeach
    <section class="analytics-definitions analytics-detail-section"><h2>Definitions and data coverage</h2><ul>@foreach($report['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul><p>Exports contain all permitted source rows, comparisons, filters and definitions. “Print charts” prints the chart summary; use the full PDF or Excel report for all rows.</p></section>
    <p class="analytics-print-scope">Reporting basis: orders placed during the selected period, using their latest recorded state. Net seller sales is not profit. Unavailable metrics are not estimated.</p>
</div>
@endsection
