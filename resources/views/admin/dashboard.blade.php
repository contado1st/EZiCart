@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <!-- Sidebar -->
<!-- Main Content Area -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Marketplace overview</h1>
                    <p class="dash-subtitle">System-wide monitoring of marketplace transactions, commission revenue, and
                        verifications.</p>
                </div>
                <div>
                    <a href="{{ route('admin.registrations.index') }}" class="dash-btn-primary">
                        Verify Users ({{ $stats['pending_users'] }})
                    </a>
                    <a href="{{ route('admin.compliance.products.index') }}"
                        class="dash-btn-primary u-extracted-71c7258ecd">
                        Review Products ({{ $stats['pending_product_reviews'] }})
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="dash-alert-success"><x-shop-icon name="checklist" /> {{ session('success') }}</div>
            @endif

            <!-- Financial & Logistics KPI Cards -->
            <section class="feature-panel"><h2>Platform activity</h2><div class="feature-grid">
                @foreach (['buyers' => 'Buyer accounts', 'sellers' => 'Approved sellers', 'products' => 'Approved listings', 'orders' => 'Orders placed', 'delivered' => 'Parcels delivered', 'failed_attempts' => 'Failed delivery attempts'] as $key => $label)
                    <p><strong>{{ number_format($stats[$key]) }}</strong><br>{{ $label }}</p>
                @endforeach
            </div></section>
            <div class="dash-stats-grid">
                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="money" /></span>
                    <div class="dash-stat-label">Verified gross commission</div>
                    <div class="dash-stat-value success">₱{{ number_format($stats['total_commission'], 2) }}</div>
                    <div class="dash-stat-subtext success">Completed orders with verified remittances, before promotion costs</div>
                </div>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="cart1" /></span>
                    <div class="dash-stat-label">Gross Merchandise Value</div>
                    <div class="dash-stat-value primary">₱{{ number_format($stats['gmv'], 2) }}</div>
                    <div class="dash-stat-subtext">Total transaction volume</div>
                </div>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="shield" /></span>
                    <div class="dash-stat-label">Pending Verifications</div>
                    <div class="dash-stat-value warning">{{ $stats['pending_users'] }}</div>
                    <div class="dash-stat-subtext">Sellers and couriers awaiting review</div>
                </div>

                <a href="{{ route('admin.compliance.products.index') }}" class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="package1" /></span>
                    <div class="dash-stat-label">Product Reviews</div>
                    <div class="dash-stat-value warning">{{ $stats['pending_product_reviews'] }}</div>
                    <div class="dash-stat-subtext">New listings awaiting category and compliance review</div>
                </a>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="delivery1" /></span>
                    <div class="dash-stat-label">Parcels in Transit</div>
                    <div class="dash-stat-value">{{ $stats['active_parcels'] }}</div>
                    <div class="dash-stat-subtext">Active courier deliveries</div>
                </div>
            </div>

            <!-- Workflow Grid -->
            <div class="dash-workflow-grid">
                <!-- Left: System Transactions -->
                <div class="dash-panel">
                    <h3 class="dash-panel-title">Recent Platform Orders</h3>

                    <div class="dash-table-wrapper">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Store</th>
                                    <th>Buyer</th>
                                    <th>Total</th>
                                    <th>Commission</th>
                                    <th>Status</th>
                                    <th>Order action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                    <tr>
                                        <td class="dash-text-bold">{{ $order->order_number }}<div><a
                                                    href="{{ route('admin.orders.messages.show', $order) }}">Open order
                                                    conversation</a></div>
                                        </td>
                                        <td>{{ $order->seller->business_name ?? 'Store' }}</td>
                                        <td>{{ $order->recipient_name }}</td>
                                        <td class="dash-text-bold">₱{{ number_format($order->total_amount, 2) }}</td>
                                        <td class="dash-text-success-bold">₱{{ number_format($order->commission_fee, 2) }}
                                        </td>
                                        <td>
                                            <span
                                                class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                                {{ str_replace('_', ' ', $order->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if (in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true) &&
                                                    $order->picked_up_at === null &&
                                                    $order->seller_handover_at === null)
                                                <form action="{{ route('admin.orders.cancel', $order) }}" method="POST" data-decision="admin.orders.cancel" data-decision-title="Cancel this order?" data-decision-message="Check the order and reason carefully before recording an administrative cancellation." data-decision-label="Cancel order" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    @csrf
                                                    <label class="sr-only"
                                                        for="admin-cancel-reason-{{ $order->id }}">Cancellation reason
                                                        for {{ $order->order_number }}</label>
                                                    <input id="admin-cancel-reason-{{ $order->id }}" name="reason"
                                                        maxlength="500" required placeholder="Reason for cancellation">
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-outline">Cancel</button>
                                                </form>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="dash-table-empty">
                                            No transactions recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: Quick Verification Queue -->
                <div class="dash-panel">
                    <x-workspace-distribution :values="$recentOrders->countBy('status')->all()" label="Orders by status" description="Status mix of the latest platform orders." />
                    <div class="dash-panel-header-row">
                        <h3 class="dash-panel-title dash-panel-title-compact">Pending Approvals</h3>
                        <a href="{{ route('admin.registrations.index') }}" class="dash-action-link">View All →</a>
                    </div>

                    @forelse($pendingUsers as $pUser)
                        <div class="dash-approval-card">
                            <div class="dash-approval-row">
                                <div>
                                    <div class="dash-approval-name">{{ $pUser->first_name }} {{ $pUser->last_name }}</div>
                                    <div class="dash-approval-email">{{ $pUser->email }}</div>
                                    <span
                                        class="dash-badge dash-badge-category dash-approval-badge">{{ ucfirst($pUser->role) }}</span>
                                </div>
                                <div class="dash-approval-actions">
                                    <form action="{{ route('admin.registrations.approve', $pUser->id) }}" method="POST" data-decision="admin.registrations.approve" data-decision-title="Approve this account?" data-decision-message="Confirm that you reviewed the submitted credentials and that this account meets the approval requirements." data-decision-label="Approve account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        @csrf
                                        <button type="submit" class="dash-btn-sm dash-btn-success">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.registrations.reject', $pUser->id) }}" method="POST" data-decision="admin.registrations.reject" data-decision-title="Reject this application?" data-decision-message="Check the rejection reason before recording your decision." data-decision-label="Reject application" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        @csrf
                                        <button type="submit" class="dash-btn-sm dash-btn-danger">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="dash-empty-box">
                            <x-shop-icon name="checklist" /> All user registration requests have been addressed.
                        </div>
                    @endforelse
                </div>
            </div>
        </main>
    </div>
@endsection
