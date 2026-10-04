@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Fleet administration</div>
            <h1>Rider roster</h1>
            <p>Review applications and keep dispatch eligibility current.</p>
        </div>
    </header>
    <section class="ops-panel">
        <h2>Riders <span class="ops-muted">{{ $riders->total() }} records</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Contact & vehicle</th>
                        <th>Area</th>
                        <th>Workload</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($riders as $rider)
                        <tr>
                            <td><strong>{{ $rider->first_name }} {{ $rider->last_name }}</strong>
                                <div class="ops-muted">{{ $rider->email }}</div>
                            </td>
                            <td>{{ $rider->contact_no }}<div class="ops-muted">
                                    {{ $rider->vehicle_type ?? 'Vehicle not set' }} ·
                                    {{ $rider->plate_number ?? 'No plate' }}</div>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('logistics.riders.areas.update', $rider) }}">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="rider-areas-{{ $rider->id }}">Service areas</label>
                                    <select id="rider-areas-{{ $rider->id }}" name="area_ids[]" multiple>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" @selected($rider->serviceAreas->contains('id', $area->id))>{{ $area->name }} ({{ $area->code }})</option>
                                        @endforeach
                                    </select>
                                    <button class="ops-btn" type="submit">Save areas</button>
                                </form>
                            </td>
                            <td>{{ $rider->active_deliveries_count }} active<div class="ops-muted">
                                    {{ $rider->completed_deliveries_count }} delivered ·
                                    {{ $rider->failed_deliveries_count }} failed</div>
                            </td>
                            <td><span
                                    class="ops-status {{ $rider->status === 'approved' ? 'ops-status--green' : ($rider->status === 'pending' ? 'ops-status--amber' : '') }}">{{ ucfirst($rider->status) }}</span>
                            </td>
                            <td>
                                @foreach (['identity' => 'ID', 'license' => 'License', 'vehicle' => 'OR/CR'] as $type => $label)
                                    @if (($type === 'identity' && $rider->id_path) || ($type === 'license' && $rider->license_path) || ($type === 'vehicle' && $rider->or_cr_path))
                                        <a href="{{ route('logistics.riders.documents.show', [$rider, $type]) }}">{{ $label }}</a> @else<span
                                            class="ops-muted">{{ $label }} pending</span>
                                    @endif
                                @endforeach
                            </td>
                            <td>
                                @if ($rider->status === 'pending')
                                    <div class="courier-actions">
                                        <form method="POST" action="{{ route('logistics.riders.approve', $rider) }}">
                                            @csrf<button class="ops-btn ops-btn--primary">Approve</button></form>
                                        <form method="POST" action="{{ route('logistics.riders.reject', $rider) }}">
                                            @csrf<button class="ops-btn ops-btn--danger">Reject</button></form>
                                    </div>
                                @elseif($rider->status === 'approved')
                                    <form method="POST" action="{{ route('logistics.riders.suspend', $rider) }}">
                                        @csrf<button class="ops-btn ops-btn--danger">Suspend</button></form>
                                @elseif($rider->status === 'suspended')
                                    <form method="POST" action="{{ route('logistics.riders.reactivate', $rider) }}">
                                    @csrf<button class="ops-btn">Reactivate</button></form>@else<span
                                        class="ops-muted">No action</span>
                                @endif
                            </td>
                    </tr>@empty<tr>
                            <td colspan="7">
                                <div class="ops-empty">No rider applications or profiles found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $riders->links() }}</div>
    </section>
@endsection
