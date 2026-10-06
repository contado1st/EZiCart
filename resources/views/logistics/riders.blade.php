@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Rider roster</h1>
            <p>Review rider applications, manage hub area assignments, and maintain active delivery eligibility.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink">{{ $riders->total() }} riders</span>
        </div>
    </header>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Roster records</h2>
                <p class="ops-muted">Riders must be assigned to at least one hub service area to view full profiles and verify documents.</p>
            </div>
        </div>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Rider & badge</th>
                        <th>Contact & vehicle</th>
                        <th>Service areas</th>
                        <th>Workload</th>
                        <th>Status</th>
                        <th>Verification docs</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($riders as $rider)
                        <tr>
                            <td>
                                <strong>{{ $rider->first_name }} {{ $rider->last_name }}</strong>
                                @if ($rider->assigned_to_hub)
                                    <div class="ops-muted">{{ $rider->email }}</div>
                                    <div class="ops-mono">EZR:{{ $rider->badge_code }}</div>
                                    <div class="logistics-badge-qr" role="img" aria-label="QR badge for {{ $rider->first_name }}">
                                        {!! $rider->badge_qr !!}
                                    </div>
                                @else
                                    <div class="ops-muted">Unassigned rider candidate</div>
                                @endif
                            </td>
                            <td>
                                @if ($rider->assigned_to_hub)
                                    <div><strong>{{ $rider->contact_no }}</strong></div>
                                    <div class="ops-muted">
                                        {{ $rider->vehicle_type ?? 'Vehicle not set' }} · {{ $rider->plate_number ?? 'No plate' }}
                                    </div>
                                @else
                                    <span class="ops-muted">Assign to a hub area to view contact and vehicle details.</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('logistics.riders.areas.update', $rider) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="rider-areas-{{ $rider->id }}">Service areas</label>
                                    <select id="rider-areas-{{ $rider->id }}" name="area_ids[]" multiple>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" @selected($rider->serviceAreas->contains('id', $area->id))>
                                                {{ $area->name }} ({{ $area->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="ops-btn" type="submit">Save areas</button>
                                </form>
                            </td>
                            <td>
                                @if ($rider->assigned_to_hub)
                                    <strong>{{ $rider->active_deliveries_count }} active</strong>
                                    <div class="ops-muted">
                                        {{ $rider->completed_deliveries_count }} delivered · {{ $rider->failed_deliveries_count }} failed
                                    </div>
                                @else
                                    <span class="ops-muted">Not assigned</span>
                                @endif
                            </td>
                            <td>
                                <span class="ops-status {{ $rider->status === 'approved' ? 'ops-status--green' : ($rider->status === 'pending' ? 'ops-status--amber' : 'ops-status--danger') }}">
                                    {{ ucfirst($rider->status) }}
                                </span>
                            </td>
                            <td>
                                @if ($rider->assigned_to_hub)
                                    <div class="courier-actions">
                                        @foreach (['identity' => 'ID', 'license' => 'License', 'vehicle' => 'OR/CR'] as $type => $label)
                                            @if (
                                                ($type === 'identity' && $rider->id_path) ||
                                                ($type === 'license' && $rider->license_path) ||
                                                ($type === 'vehicle' && $rider->or_cr_path))
                                                <a class="logistics-doc-chip" href="{{ route('logistics.riders.documents.show', [$rider, $type]) }}">
                                                    📄 {{ $label }}
                                                </a>
                                            @else
                                                <span class="logistics-doc-pending">{{ $label }} pending</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="ops-muted">Assign to this hub before reviewing documents.</span>
                                @endif
                            </td>
                            <td>
                                @if (!$rider->assigned_to_hub)
                                    <span class="ops-muted">Assign one or more service areas to claim this rider for your hub.</span>
                                @elseif ($rider->status === 'pending')
                                    <div class="courier-actions">
                                        <form method="POST" action="{{ route('logistics.riders.approve', $rider) }}">
                                            @csrf
                                            <button class="ops-btn ops-btn--primary" type="submit">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('logistics.riders.reject', $rider) }}">
                                            @csrf
                                            <button class="ops-btn ops-btn--danger" type="submit">Reject</button>
                                        </form>
                                    </div>
                                @elseif($rider->status === 'approved')
                                    <form class="ops-form" method="POST" action="{{ route('logistics.riders.suspend', $rider) }}">
                                        @csrf
                                        <div class="ops-field">
                                            <input name="reason" required maxlength="500" aria-label="Suspension reason" placeholder="Reason for suspension…">
                                        </div>
                                        <button class="ops-btn ops-btn--danger" type="submit">Suspend</button>
                                    </form>
                                @elseif($rider->status === 'suspended')
                                    @if ($rider->suspension_source === 'logistics' && $rider->suspension_previous_status === 'approved')
                                        <form method="POST" action="{{ route('logistics.riders.reactivate', $rider) }}">
                                            @csrf
                                            <button class="ops-btn" type="submit">Reactivate</button>
                                        </form>
                                    @else
                                        <span class="ops-muted">Admin review required</span>
                                    @endif
                                @else
                                    <span class="ops-muted">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="ops-empty">
                                    <strong>No rider profiles found</strong>
                                    <p>No rider applications or profiles match this hub.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $riders->links() }}</div>
    </section>
@endsection
