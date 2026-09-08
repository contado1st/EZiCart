@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/platform-controls.css') }}">
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
                <a href="{{ route('admin.disputes.index') }}" class="dash-nav-item">⚖️ Dispute Arbitration</a>
                <a href="{{ route('admin.announcements.index') }}" class="dash-nav-item active">📢 Announcements</a>
                <a href="{{ route('admin.moderation.index') }}" class="dash-nav-item">🚫 Account Moderation</a>
            </nav>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="dash-logout-btn">🚪 Logout</button>
        </form>
    </aside>

    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Platform Announcements & Bulletins</h1>
                <p class="dash-subtitle">Broadcast operational notices, maintenance schedules, and policy updates.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <div class="controls-grid">
            <!-- Create Announcement -->
            <div class="controls-card">
                <h2 class="controls-title">Create Announcement</h2>
                <form action="{{ route('admin.announcements.store') }}" method="POST">
                    @csrf

                    <div class="controls-field">
                        <label class="controls-label">Title *</label>
                        <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Scheduled System Maintenance" class="controls-input" required>
                    </div>

                    <div class="controls-field">
                        <label class="controls-label">Notice Type *</label>
                        <select name="type" class="controls-select" required>
                            <option value="info">Information (Blue)</option>
                            <option value="warning">Warning / Advisory (Yellow)</option>
                            <option value="urgent">Urgent Alert (Red)</option>
                            <option value="maintenance">System Maintenance (Gray)</option>
                        </select>
                    </div>

                    <div class="controls-field">
                        <label class="controls-label">Target Audience *</label>
                        <select name="target_role" class="controls-select" required>
                            <option value="all">Entire Platform (All Users)</option>
                            <option value="buyer">Buyers Only</option>
                            <option value="seller">Merchants & Sellers Only</option>
                            <option value="courier">Delivery Riders Only</option>
                            <option value="sorting_center">Sorting Centers Only</option>
                        </select>
                    </div>

                    <div class="controls-field">
                        <label class="controls-label">Message Content *</label>
                        <textarea name="content" rows="4" class="controls-textarea" placeholder="Provide complete notice details..." required>{{ old('content') }}</textarea>
                    </div>

                    <div class="controls-field">
                        <label class="controls-label">Expiration Date (Optional)</label>
                        <input type="date" name="expires_at" class="controls-input">
                    </div>

                    <button type="submit" class="dash-btn-primary" style="width: 100%; justify-content: center; margin-top: 0.5rem;">
                        Publish Announcement
                    </button>
                </form>
            </div>

            <!-- Announcements Table -->
            <div class="dash-panel">
                <h2 class="courier-section-title">Published Notices ({{ $announcements->total() }})</h2>

                <div class="dash-table-wrapper" style="margin-top: 1rem;">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Audience</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($announcements as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->title }}</strong>
                                        <div style="font-size: 0.75rem; color: var(--dash-text-muted);">{{ \Illuminate\Support\Str::limit($item->content, 60) }}</div>
                                    </td>
                                    <td><span class="badge-target-role">{{ strtoupper(str_replace('_', ' ', $item->target_role)) }}</span></td>
                                    <td>
                                        <span class="status-pill banner-type-{{ $item->type }}">
                                            {{ ucfirst($item->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $item->is_active ? 'status-completed' : 'status-failed' }}">
                                            {{ $item->is_active ? 'Active' : 'Archived' }}
                                        </span>
                                    </td>
                                    <td>{{ $item->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <div style="display: flex; gap: 0.35rem;">
                                            <form action="{{ route('admin.announcements.toggle', $item->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="dash-btn-sm" style="border: 1px solid var(--dash-border); background: #fff;">
                                                    {{ $item->is_active ? 'Archive' : 'Activate' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.announcements.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this announcement permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dash-btn-sm dash-btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="dash-table-empty">No announcements active.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1rem;">
                    {{ $announcements->links() }}
                </div>
            </div>
        </div>
    </main>
</div>
@endsection