@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/shared/platform-controls.css')
@endpush

@section('content')
    <div class="dash-wrapper">
<main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Platform Announcements & Bulletins</h1>
                    <p class="dash-subtitle">Broadcast operational notices, maintenance schedules, and policy updates.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="dash-alert-success"><x-shop-icon name="checklist" /> {{ session('success') }}</div>
            @endif

            <div class="controls-grid">
                <!-- Create Announcement -->
                <div class="controls-card">
                    <h2 class="controls-title">Create Announcement</h2>
                    <form action="{{ route('admin.announcements.store') }}" method="POST">
                        @csrf

                        <div class="controls-field">
                            <label class="controls-label">Title *</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                placeholder="e.g. Scheduled System Maintenance" class="controls-input" required>
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
                            <textarea name="content" rows="4" class="controls-textarea" placeholder="Provide complete notice details..."
                                required>{{ old('content') }}</textarea>
                        </div>

                        <div class="controls-field">
                            <label class="controls-label">Expiration Date (Optional)</label>
                            <input type="date" name="expires_at" class="controls-input">
                        </div>

                        <button type="submit" class="dash-btn-primary u-extracted-ec7de1f956">
                            Publish Announcement
                        </button>
                    </form>
                </div>

                <!-- Announcements Table -->
                <div class="dash-panel">
                    <h2 class="courier-section-title">Published Notices ({{ $announcements->total() }})</h2>

                    <div class="dash-table-wrapper u-extracted-dab43fb936">
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
                                            <div class="u-extracted-cf0c302441">
                                                {{ \Illuminate\Support\Str::limit($item->content, 60) }}</div>
                                        </td>
                                        <td><span
                                                class="badge-target-role">{{ strtoupper(str_replace('_', ' ', $item->target_role)) }}</span>
                                        </td>
                                        <td>
                                            <span class="status-pill banner-type-{{ $item->type }}">
                                                {{ ucfirst($item->type) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                class="status-pill {{ $item->is_active ? 'status-completed' : 'status-failed' }}">
                                                {{ $item->is_active ? 'Active' : 'Archived' }}
                                            </span>
                                        </td>
                                        <td>{{ $item->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <div class="u-extracted-6d9d6fda77">
                                                <form action="{{ route('admin.announcements.toggle', $item->id) }}"
                                                    method="POST" data-decision="admin.announcements.toggle" data-decision-title="Change announcement visibility?" data-decision-message="This changes whether users can see the selected announcement." data-decision-label="Change visibility" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dash-btn-sm u-extracted-e013d740bb">
                                                        {{ $item->is_active ? 'Archive' : 'Activate' }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.announcements.destroy', $item->id) }}"
                                                    method="POST" data-decision="admin.announcements.destroy" data-decision-title="Delete this announcement?" data-decision-message="This permanently deletes the selected announcement." data-decision-label="Delete announcement" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-danger">Delete</button>
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

                    <div class="u-extracted-dab43fb936">
                        {{ $announcements->links() }}
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection
