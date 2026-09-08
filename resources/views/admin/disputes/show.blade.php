@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag dash-role-admin">Super Admin</span>
                <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
            </div>
            <nav class="dash-nav">
                <a href="{{ route('admin.dashboard') }}" class="dash-nav-item">📊 Platform Overview</a>
                <a href="{{ route('admin.registrations.index') }}" class="dash-nav-item">🛡️ User Approvals</a>
                <a href="{{ route('admin.reports.index') }}" class="dash-nav-item">📑 Financial & Commission</a>
                <a href="{{ route('admin.disputes.index') }}" class="dash-nav-item active">⚖️ Dispute Arbitration</a>
            </nav>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="dash-logout-btn">🚪 Logout</button>
        </form>
    </aside>

    <main class="dash-main">
        <a href="{{ route('admin.disputes.index') }}" style="color: var(--dash-text-muted); font-size: 0.8125rem; font-weight: 700; text-decoration: none;">
            ← Back to Disputes
        </a>

        <div class="dash-header" style="margin-top: 0.5rem;">
            <div>
                <h1 class="dash-title">Case #DIS-{{ str_pad($dispute->id, 4, '0', STR_PAD_LEFT) }}</h1>
                <p class="dash-subtitle">Order Reference: {{ $dispute->order->order_number }} &bull; Reason: <strong>{{ $dispute->reason }}</strong></p>
            </div>
            <span class="status-pill status-badge-{{ strtolower(str_replace('_', '-', $dispute->status)) }}">
                {{ str_replace('_', ' ', $dispute->status) }}
            </span>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <div class="dispute-detail-grid">
            <!-- Case Evidence & Statements -->
            <div class="dash-panel">
                <h2 class="courier-section-title">Buyer Complaint & Statements</h2>
                
                <div style="margin-top: 1rem; font-size: 0.875rem; color: var(--dash-text-main); line-height: 1.6;">
                    {{ $dispute->description }}
                </div>

                @if($dispute->evidence_path)
                    <div class="dispute-evidence-box">
                        <strong style="font-size: 0.8125rem;">Attached Evidence:</strong>
                        <div style="margin-top: 0.5rem;">
                            <a href="{{ asset('storage/' . $dispute->evidence_path) }}" target="_blank">
                                <img src="{{ asset('storage/' . $dispute->evidence_path) }}" alt="Evidence" class="dispute-evidence-thumb">
                            </a>
                        </div>
                    </div>
                @endif

                <h2 class="courier-section-title" style="margin-top: 2rem;">Order Items in Dispute</h2>
                <div class="dash-table-wrapper" style="margin-top: 0.75rem;">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Unit Price</th>
                                <th>Qty</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dispute->order->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->product_name }}
                                        @if($item->variation_info)
                                            <span style="font-size: 0.75rem; color: var(--dash-primary);">({{ $item->variation_info }})</span>
                                        @endif
                                    </td>
                                    <td>₱{{ number_format($item->unit_price ?? $item->price, 2) }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>₱{{ number_format($item->item_total ?? $item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Arbitration Decision Panel -->
            <div class="dash-panel">
                <h2 class="courier-section-title">Admin Decision</h2>

                <form action="{{ route('admin.disputes.resolve', $dispute->id) }}" method="POST" style="margin-top: 1rem;">
                    @csrf
                    @method('PATCH')

                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem; color: var(--dash-text-muted);">
                            Arbitration Verdict *
                        </label>
                        <select name="status" class="search-input" style="width: 100%; border: 1px solid var(--dash-border);" required>
                            <option value="UNDER_REVIEW" {{ $dispute->status === 'UNDER_REVIEW' ? 'selected' : '' }}>Mark as Under Investigation</option>
                            <option value="REFUND_APPROVED" {{ $dispute->status === 'REFUND_APPROVED' ? 'selected' : '' }}>Approve Full Refund to Buyer</option>
                            <option value="REPLACEMENT_APPROVED" {{ $dispute->status === 'REPLACEMENT_APPROVED' ? 'selected' : '' }}>Order Merchant Replacement</option>
                            <option value="REJECTED" {{ $dispute->status === 'REJECTED' ? 'selected' : '' }}>Dismiss Claim (Claimant at Fault)</option>
                            <option value="RESOLVED" {{ $dispute->status === 'RESOLVED' ? 'selected' : '' }}>Mark Completely Resolved</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem; color: var(--dash-text-muted);">
                            Arbitration Notes & Ruling *
                        </label>
                        <textarea name="admin_notes" rows="6" class="search-input" style="width: 100%; border: 1px solid var(--dash-border); resize: vertical;" placeholder="Document legal grounds and arbitration instructions..." required>{{ old('admin_notes', $dispute->admin_notes) }}</textarea>
                    </div>

                    <button type="submit" class="dash-btn-primary" style="width: 100%; justify-content: center;">
                        Save Arbitration Ruling
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>
@endsection