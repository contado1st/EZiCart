@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <aside class="dash-sidebar">
            <div>
                <div class="dash-profile-badge">
                    <span class="dash-role-tag dash-role-admin">Super Admin</span>
                    <h2 class="dash-profile-title">Product Compliance</h2>
                    <p class="dash-profile-subtitle">Manual listing review and seller warnings</p>
                </div>
                <nav class="dash-nav">
                    <a href="{{ route('admin.dashboard') }}" class="dash-nav-item">Platform Overview</a>
                    <a href="{{ route('admin.registrations.index') }}" class="dash-nav-item">User Approvals</a>
                    <a href="{{ route('admin.compliance.products.index') }}" class="dash-nav-item active">Product Compliance</a>
                </nav>
            </div>
        </aside>

        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Product compliance</h1>
                    <p class="dash-subtitle">Review seller-submitted product details and record the reason for each flag.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="dash-alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.compliance.products.index') }}" class="dash-panel" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:end; padding:1rem;">
                <div class="form-group"><label class="form-label" for="search">Search product, category, or seller</label><input id="search" class="form-control" name="search" value="{{ request('search') }}" maxlength="100"></div>
                <div class="form-group"><label class="form-label" for="status">Status</label><select id="status" class="form-control" name="status"><option value="">Needs review and flagged</option>@foreach (['pending_review' => 'Pending review', 'flagged' => 'Flagged', 'approved' => 'Approved'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
                <button class="dash-btn-primary" type="submit">Filter</button>
            </form>

            <div class="dash-table-wrapper" style="margin-top:1rem;">
                <table class="dash-table">
                    <thead><tr><th>Product and category</th><th>Seller</th><th>Review history</th><th>Review action</th></tr></thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td><strong>{{ $product->name }}</strong><div>{{ $product->category }}</div><div class="text-muted-small">{{ $product->description }}</div><span class="dash-badge dash-badge-archived">{{ str_replace('_', ' ', ucfirst($product->compliance_status)) }}</span></td>
                                <td>{{ $product->seller?->business_name ?? $product->seller?->first_name }}<div class="text-muted-small">Seller category: {{ $product->seller?->line_of_business ?: 'Not provided' }}</div><div class="text-muted-small">{{ $product->seller?->email }}</div><div class="text-muted-small">{{ $product->warning_count }} recorded warning(s)</div><a href="{{ route('admin.moderation.index', ['search' => $product->seller?->email]) }}">Review seller account</a></td>
                                <td>@forelse ($product->complianceEvents->take(3) as $event)<div><strong>{{ ucfirst(str_replace('_', ' ', $event->action)) }}</strong> · {{ $event->actor?->first_name }} {{ $event->actor?->last_name }}<div class="text-muted-small">{{ $event->created_at->format('M d, Y H:i') }}{{ $event->note ? ' · '.$event->note : '' }}</div></div>@empty<span class="text-muted-small">No review events.</span>@endforelse</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.compliance.products.review', $product) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="form-group"><label class="form-label" for="decision-{{ $product->id }}">Decision</label><select id="decision-{{ $product->id }}" class="form-control" name="compliance_status"><option value="approved">Approve listing</option><option value="flagged">Flag and warn seller</option></select></div>
                                        <div class="form-group"><label class="form-label" for="note-{{ $product->id }}">Review note (required when flagging)</label><textarea id="note-{{ $product->id }}" class="form-control" name="compliance_note" maxlength="1000"></textarea></div>
                                        <button class="dash-btn-sm dash-btn-primary" type="submit">Save review</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.compliance.products.warn', $product) }}" style="margin-top:1rem;">
                                        @csrf
                                        <div class="form-group"><label class="form-label" for="warning-{{ $product->id }}">Separate seller warning</label><textarea id="warning-{{ $product->id }}" class="form-control" name="warning_note" maxlength="1000" required></textarea></div>
                                        <button class="dash-btn-sm" type="submit">Issue warning</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="dash-table-empty">No products match this review queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $products->links() }}
        </main>
    </div>
@endsection
