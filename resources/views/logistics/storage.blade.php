@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div><div class="ops-eyebrow">Hub inventory</div><h1>Storage locations</h1>
            <p>Location and parcel QR codes identify records. The server checks hub, route, capacity, and parcel state.</p>
        </div>
    </header>
    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Create a location</h2>
            <form class="ops-form" method="POST" action="{{ route('logistics.storage.locations.create') }}">
                @csrf
                <div class="ops-field"><label for="label">Location label</label><input id="label" name="label" maxlength="120" required></div>
                <div class="ops-field"><label for="type">Type</label><select id="type" name="type" required>
                    @foreach (['RECEIVING', 'SORTING', 'STAGING', 'DISPATCH', 'EXCEPTION', 'RETURNS'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                </select></div>
                <div class="ops-field"><label for="area_id">Area (optional)</label><select id="area_id" name="area_id"><option value="">Any area</option>
                    @foreach ($areas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach
                </select></div>
                <div class="ops-field"><label for="capacity">Capacity (0 means unlimited)</label><input id="capacity" name="capacity" type="number" min="0" value="0"></div>
                <button class="ops-btn ops-btn--primary">Create location</button>
            </form>
        </div>
        <div class="ops-panel">
            <h2>Put away parcel</h2>
            <form class="ops-form" method="POST" action="{{ route('logistics.storage.putaway') }}">
                @csrf
                <div class="ops-field"><label for="parcel_reference">Parcel QR or parcel code</label><input id="parcel_reference" name="parcel_reference" placeholder="EZP:…" required></div>
                <div class="ops-field"><label for="location_reference">Location QR or location code</label><input id="location_reference" name="location_reference" placeholder="EZL:…" required></div>
                <input type="hidden" name="method" value="manual">
                <button class="ops-btn ops-btn--primary">Verify and place</button>
            </form>
        </div>
    </section>
    <section class="ops-panel">
        <h2>Cycle count</h2>
        <p class="ops-muted">Scan the location label, then enter one parcel QR/code per line. The system compares the scanned set with active placements.</p>
        <form class="ops-form" method="POST" action="{{ route('logistics.storage.cycle-count') }}">@csrf
            <div class="ops-field"><label for="count_location">Location QR or code</label><input id="count_location" name="location_reference" placeholder="EZL:…" required></div>
            <div class="ops-field"><label for="scanned_parcels">Scanned parcels</label><textarea id="scanned_parcels" name="scanned_parcels" rows="6" placeholder="EZP:…"></textarea></div>
            <input type="hidden" name="method" value="manual"><button class="ops-btn ops-btn--primary">Compare cycle count</button>
        </form>
        @if (session('cycle_report'))
            <h3>Count result: {{ session('cycle_report.location') }}</h3>
            <p>Correct: {{ count(session('cycle_report.correct')) }} · Missing: {{ count(session('cycle_report.missing')) }} · Unexpected: {{ count(session('cycle_report.unexpected')) }}</p>
            @foreach (['correct' => 'Correct parcels', 'missing' => 'Missing parcels', 'unexpected' => 'Unexpected scans'] as $key => $label)
                <div><strong>{{ $label }}</strong>: {{ implode(', ', session('cycle_report.'.$key)) ?: 'None' }}</div>
            @endforeach
        @endif
    </section>
    <section class="ops-panel">
        <h2>Locations <span class="ops-muted">{{ $locations->count() }}</span></h2>
        <div class="ops-table-wrap"><table class="ops-table">
            <thead><tr><th>QR label</th><th>Type</th><th>Area</th><th>Occupancy</th><th>Oldest parcel</th></tr></thead>
            <tbody>@forelse($locations as $location)
                <tr><td><div class="ops-mono">{{ $location->code }}</div><div>{{ $location->label }}</div><div>{!! $location->qr_svg !!}</div></td>
                    <td>{{ $location->type }}</td><td>{{ $location->area_id ? ($areas->firstWhere('id', $location->area_id)?->name ?? 'Inactive area') : 'Any area' }}</td>
                    <td>{{ (int) ($location->active_count ?? 0) }} / {{ $location->capacity ?: '∞' }}</td>
                    <td>{{ $location->oldest_placement_at ?? '—' }}</td></tr>
            @empty<tr><td colspan="5"><div class="ops-empty">Create a location to start tracking hub storage.</div></td></tr>@endforelse</tbody>
        </table></div>
    </section>
    <section class="ops-panel">
        <h2>Active placements <span class="ops-muted">{{ $placements->count() }} recent</span></h2>
        <div class="ops-table-wrap"><table class="ops-table">
            <thead><tr><th>Parcel</th><th>Location</th><th>Placed</th><th>Dwell time</th><th>Pick</th></tr></thead>
            <tbody>@forelse($placements as $placement)
                <tr><td class="ops-mono">{{ $placement->order_number }}</td><td>{{ $placement->location_code }} · {{ $placement->location_label }}</td><td>{{ $placement->placed_at }}</td><td>{{ $placement->dwell_time }}</td><td>
                    <form class="ops-form" method="POST" action="{{ route('logistics.storage.pick') }}">@csrf
                        <input type="hidden" name="parcel_reference" value="EZP:{{ $placement->parcel_code }}">
                        <input type="hidden" name="location_reference" value="EZL:{{ $placement->location_code }}">
                        <input type="hidden" name="method" value="manual"><button class="ops-btn">Remove from location</button>
                    </form>
                </td></tr>
            @empty<tr><td colspan="5"><div class="ops-empty">No parcels have an active storage placement.</div></td></tr>@endforelse</tbody>
        </table></div>
    </section>
@endsection
