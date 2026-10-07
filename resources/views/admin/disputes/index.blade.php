@extends('layouts.admin')

@section('title', 'Dispute Arbitration - Admin Portal')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}">
@endpush

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Disputes & Complaints Center</h1>
        <p class="dash-subtitle">Review contested orders, inspect buyer evidence, and execute resolutions.</p>
    </div>
</div>

<!-- Filter Status Toolbar -->
<div class="dispute-filter-nav" style="margin-bottom: 1.5rem;">
    <a href="{{ route('admin.disputes.index') }}" class="dispute-filter-link {{ empty($status) ? 'active' : '' }}">All Cases</a>
    <a href="{{ route('admin.disputes.index', ['status' => 'PENDING']) }}" class="dispute-filter-link {{ $status === 'PENDING' ? 'active' : '' }}">Pending</a>
    <a href="{{ route('admin.disputes.index', ['status' => 'UNDER_REVIEW']) }}" class="dispute-filter-link {{ $status === 'UNDER_REVIEW' ? 'active' : '' }}">Under Review</a>
    <a href="{{ route('admin.disputes.index', ['status' => 'REFUND_APPROVED']) }}" class="dispute-filter-link {{ $status === 'REFUND_APPROVED' ? 'active' : '' }}">Refunded</a>
    <a href="{{ route('admin.disputes.index', ['status' => 'REJECTED']) }}" class="dispute-filter-link {{ $status === 'REJECTED' ? 'active' : '' }}">Rejected</a>
</div>

<div class="dash-panel">
    <h2 class="courier-section-title">Open Claims ({{ $disputes->total() }})</h2>

    <div class="dash-table-wrapper" style="margin-top: 1rem;">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Case ID</th>
                    <th>Order No.</th>
                    <th>Buyer</th>
                    <th>Merchant</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($disputes as $dispute)
                    <tr>
                        <td><strong>#DIS-{{ str_pad($dispute->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                        <td>{{ $dispute->order->order_number }}</td>
                        <td>{{ $dispute->buyer->first_name }} {{ $dispute->buyer->last_name }}</td>
                        <td>{{ $dispute->seller->business_name ?? 'Store Merchant' }}</td>
                        <td>{{ $dispute->reason }}</td>
                        <td>
                            <span class="status-pill status-badge-{{ strtolower(str_replace('_', '-', $dispute->status)) }}">
                                {{ str_replace('_', ' ', $dispute->status) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.disputes.show', $dispute->id) }}" class="dash-action-link">
                                Review Case →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="dash-table-empty">No disputes found matching this status.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem;">
        {{ $disputes->links() }}
    </div>
</div>
@endsection