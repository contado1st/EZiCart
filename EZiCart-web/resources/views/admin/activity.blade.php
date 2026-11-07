@extends('layouts.app')
@section('title', 'Activity logs | EZiCart')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/activity-logs.css') }}?v={{ filemtime(public_path('css/activity-logs.css')) }}">
<script src="{{ asset('js/activity-logs.js') }}?v={{ filemtime(public_path('js/activity-logs.js')) }}" defer></script>
@endpush
@section('content')
@include('components.feature-feedback')
<div class="feature-page"><header><div><h1>Activity logs</h1><p>Administrative and operational changes within your administration domain, plus system events.</p></div></header>
<form class="feature-panel feature-form" method="GET">
<label>Actor ID<input type="number" name="actor_id" value="{{ request('actor_id') }}" min="1"></label><label>Activity<input name="action" value="{{ request('action') }}" maxlength="100"></label><label>Module<select name="module"><option value="">All permitted modules</option>@foreach (['commerce', 'logistics', 'system'] as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst($module) }}</option>@endforeach</select></label><label>Record ID<input type="number" min="1" name="record" value="{{ request('record') }}"></label><label>Record type<input name="entity_type" value="{{ request('entity_type') }}" maxlength="150" placeholder="App\Models\Order"></label><label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label><button class="feature-button">Filter logs</button></form>
<div class="feature-table-scroll"><table class="feature-table activity-log-table">
    <thead><tr><th scope="col">When / Actor</th><th scope="col">Activity</th><th scope="col">Record</th><th scope="col">Changes</th></tr></thead>
    <tbody>
    @forelse ($logs as $log)
        <tr class="activity-entry">
            <td data-label="When / Actor"><time>{{ $log->created_at }}</time><strong>{{ $log->actor_id ? (trim(($log->first_name ?? '').' '.($log->last_name ?? '')) ?: 'User').' (#'.$log->actor_id.')' : 'System' }}</strong><small>Log #{{ $log->id }}</small></td>
            <td data-label="Activity"><strong>{{ \Illuminate\Support\Str::headline($log->action) }}</strong><small>{{ $log->role ? \Illuminate\Support\Str::headline($log->role) : 'System event' }}</small></td>
            <td data-label="Record">{{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</td>
            <td class="activity-toggle-cell"><button type="button" class="activity-toggle" data-activity-toggle aria-controls="activity-detail-{{ $log->id }}" aria-expanded="true" hidden><x-shop-icon name="arrowback" /><span data-activity-toggle-label>View changes</span><span class="activity-sr-only"> for log #{{ $log->id }}</span></button></td>
        </tr>
        <tr class="activity-detail-row" id="activity-detail-{{ $log->id }}"><td colspan="4"><x-activity-changes :log="$log" /></td></tr>
    @empty
        <tr><td colspan="4">No matching events.</td></tr>
    @endforelse
    </tbody>
</table></div>{{ $logs->links() }}</div>
@endsection
