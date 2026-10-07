@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/shared/disputes.css')
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
            <a href="{{ route('admin.disputes.index') }}" class="u-extracted-7fc4d6b00a">
                ← Back to Disputes
            </a>

            <div class="dash-header u-extracted-0dc8cc6234">
                <div>
                    <h1 class="dash-title">Case #DIS-{{ str_pad($dispute->id, 4, '0', STR_PAD_LEFT) }}</h1>
                    <p class="dash-subtitle">Order Reference: {{ $dispute->order->order_number }} &bull; Reason:
                        <strong>{{ $dispute->reason }}</strong>
                    </p>
                </div>
                <span class="status-pill status-badge-{{ strtolower(str_replace('_', '-', $dispute->status)) }}">
                    {{ str_replace('_', ' ', $dispute->status) }}
                </span>
            </div>

            @if (session('success'))
                <div class="dash-alert-success">✅ {{ session('success') }}</div>
            @endif

            <div class="dispute-detail-grid">
                <!-- Case Evidence & Statements -->
                <div class="dash-panel">
                    <h2 class="courier-section-title">Buyer Complaint & Statements</h2>

                    <div class="u-extracted-d9bf56ffb1">
                        {{ $dispute->description }}
                    </div>

                    @if ($dispute->evidence_path)
                        <div class="dispute-evidence-box">
                            <strong class="u-extracted-b9174c0407">Attached Evidence:</strong>
                            <div class="u-extracted-0dc8cc6234">
                                <a href="{{ route('admin.disputes.evidence', $dispute) }}">Download private evidence</a>
                            </div>
                        </div>
                    @endif

                    <h2 class="courier-section-title u-extracted-cee5c5825a">Order Items in Dispute</h2>
                    <div class="dash-table-wrapper u-extracted-342d988c44">
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
                                @foreach ($dispute->order->items as $item)
                                    <tr>
                                        <td>
                                            {{ $item->product_name }}
                                            @if ($item->variation_info)
                                                <span class="u-extracted-696134c27b">({{ $item->variation_info }})</span>
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

                    <form action="{{ route('admin.disputes.resolve', $dispute->id) }}" method="POST"
                        class="u-extracted-dab43fb936">
                        @csrf
                        @method('PATCH')

                        <div class="u-extracted-f267e3ca68">
                            <label class="u-extracted-fec9eb9496">
                                Arbitration Verdict *
                            </label>
                            <select name="status" class="search-input u-extracted-70322f707f" required>
                                <option value="UNDER_REVIEW" {{ $dispute->status === 'UNDER_REVIEW' ? 'selected' : '' }}>
                                    Mark as Under Investigation</option>
                                <option value="REFUND_APPROVED"
                                    {{ $dispute->status === 'REFUND_APPROVED' ? 'selected' : '' }}>Approve refund for
                                    Buyer (payout must be processed separately)</option>
                                <option value="REPLACEMENT_APPROVED"
                                    {{ $dispute->status === 'REPLACEMENT_APPROVED' ? 'selected' : '' }}>Order Merchant
                                    Replacement</option>
                                <option value="REJECTED" {{ $dispute->status === 'REJECTED' ? 'selected' : '' }}>Dismiss
                                    Claim (Claimant at Fault)</option>
                                <option value="RESOLVED" {{ $dispute->status === 'RESOLVED' ? 'selected' : '' }}>Mark
                                    Completely Resolved</option>
                            </select>
                            <p class="ops-muted">This records the dispute decision. It does not issue or confirm a payment
                                refund.</p>
                        </div>

                        <div class="u-extracted-f267e3ca68">
                            <label class="u-extracted-fec9eb9496">
                                Arbitration Notes & Ruling *
                            </label>
                            <textarea name="admin_notes" rows="6" class="search-input u-extracted-ef2dd8c267"
                                placeholder="Document legal grounds and arbitration instructions..." required>{{ old('admin_notes', $dispute->admin_notes) }}</textarea>
                        </div>

                        <button type="submit" class="dash-btn-primary u-extracted-b52608060a">
                            Save Arbitration Ruling
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
@endsection
