@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Storage locations</h1>
            <p>Hub staging zones, capacity tracking, put-away verification, and cycle counting.</p>
        </div>
    </header>

    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Create a storage location</h2>
            <form class="ops-form" method="POST" action="{{ route('logistics.storage.locations.create') }}">
                @csrf
                <div class="ops-field">
                    <label for="label">Location label</label>
                    <input id="label" name="label" maxlength="120" required placeholder="e.g. Rack A - Shelf 2">
                </div>
                <div class="ops-field">
                    <label for="type">Zone type</label>
                    <select id="type" name="type" required>
                        @foreach (['RECEIVING', 'SORTING', 'STAGING', 'DISPATCH', 'EXCEPTION', 'RETURNS'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ops-field">
                    <label for="area_id">Routing area (optional)</label>
                    <select id="area_id" name="area_id">
                        <option value="">Any area / Hub-wide</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ops-field">
                    <label for="capacity">Capacity (0 for unlimited)</label>
                    <input id="capacity" name="capacity" type="number" min="0" value="0">
                </div>
                <button class="ops-btn ops-btn--primary" type="submit">Create location</button>
            </form>
        </div>

        <div class="ops-panel">
            <h2>Put away parcel</h2>
            <form class="ops-form" method="POST" action="{{ route('logistics.storage.putaway') }}">
                @csrf
                <div class="ops-field">
                    <label for="parcel_reference">Parcel QR or code</label>
                    <input id="parcel_reference" name="parcel_reference" placeholder="EZP:…" required autocomplete="off">
                </div>
                <div class="ops-field">
                    <label for="location_reference">Location QR or code</label>
                    <input id="location_reference" name="location_reference" placeholder="EZL:…" required autocomplete="off">
                </div>
                <input type="hidden" name="method" value="manual">
                <button class="ops-btn ops-btn--primary" type="submit">Verify and place</button>
            </form>
        </div>
    </section>

    <section class="ops-panel">
        <h2>Cycle count verification</h2>
        <p class="ops-muted">Scan the location label, then enter one parcel QR/code per line. The system validates the scanned set against active hub placements.</p>
        <form class="ops-form" method="POST" action="{{ route('logistics.storage.cycle-count') }}">
            @csrf
            <div class="ops-field">
                <label for="count_location">Location QR or code</label>
                <input id="count_location" name="location_reference" placeholder="EZL:…" required autocomplete="off">
            </div>
            <div class="ops-field">
                <label for="scanned_parcels">Scanned parcel codes (one per line)</label>
                <textarea id="scanned_parcels" name="scanned_parcels" rows="4" placeholder="EZP:…&#10;EZP:…"></textarea>
            </div>
            <input type="hidden" name="method" value="manual">
            <button class="ops-btn ops-btn--primary" type="submit">Compare cycle count</button>
        </form>

        @if (session('cycle_report'))
            <div class="ops-alert ops-alert--compact">
                <h3>Count result for location: <strong>{{ session('cycle_report.location') }}</strong></h3>
                <div class="ops-stats ops-stats--priority">
                    <div class="ops-stat">
                        <span>Correct parcels</span>
                        <strong>{{ count(session('cycle_report.correct')) }}</strong>
                    </div>
                    <div class="ops-stat ops-stat--danger">
                        <span>Missing parcels</span>
                        <strong>{{ count(session('cycle_report.missing')) }}</strong>
                    </div>
                    <div class="ops-stat ops-stat--warning">
                        <span>Unexpected scans</span>
                        <strong>{{ count(session('cycle_report.unexpected')) }}</strong>
                    </div>
                </div>
                @foreach (['correct' => 'Correct parcels', 'missing' => 'Missing parcels', 'unexpected' => 'Unexpected scans'] as $key => $label)
                    <div>
                        <strong>{{ $label }}:</strong> {{ implode(', ', session('cycle_report.' . $key)) ?: 'None' }}
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="ops-panel">
        <h2>Hub storage locations <span class="ops-muted">{{ $locations->count() }} configured</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Location & QR</th>
                        <th>Type</th>
                        <th>Routing area</th>
                        <th>Occupancy</th>
                        <th>Oldest parcel dwell</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($locations as $location)
                        <tr>
                            <td data-label="Location">
                                <div class="ops-mono"><strong>{{ $location->code }}</strong></div>
                                <div>{{ $location->label }}</div>
                                <div class="logistics-badge-qr" role="img" aria-label="Location QR Code">
                                    {!! $location->qr_svg !!}
                                </div>
                            </td>
                            <td data-label="Type">
                                <span class="ops-status ops-status--pink">{{ $location->type }}</span>
                            </td>
                            <td data-label="Area">
                                {{ $location->area_id ? $areas->firstWhere('id', $location->area_id)?->name ?? 'Inactive area' : 'Any area' }}
                            </td>
                            <td data-label="Occupancy">
                                @php
                                    $activeCount = (int) ($location->active_count ?? 0);
                                    $capacity = (int) $location->capacity;
                                    $isFull = $capacity > 0 && $activeCount >= $capacity;
                                @endphp
                                <span class="ops-status {{ $isFull ? 'ops-status--danger' : 'ops-status--green' }}">
                                    {{ $activeCount }} / {{ $capacity > 0 ? $capacity : '∞' }}
                                </span>
                            </td>
                            <td data-label="Dwell">{{ $location->oldest_placement_at ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">Create a location to start tracking hub storage.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="ops-panel">
        <h2>Active placements <span class="ops-muted">{{ $placements->count() }} recent</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Parcel</th>
                        <th>Location</th>
                        <th>Placed at</th>
                        <th>Dwell time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($placements as $placement)
                        <tr>
                            <td class="ops-mono" data-label="Parcel">
                                <strong>{{ $placement->order_number }}</strong>
                            </td>
                            <td data-label="Location">
                                <span class="ops-mono">{{ $placement->location_code }}</span>
                                <div class="ops-muted">{{ $placement->location_label }}</div>
                            </td>
                            <td data-label="Placed at" class="ops-mono">{{ $placement->placed_at }}</td>
                            <td data-label="Dwell time">{{ $placement->dwell_time }}</td>
                            <td data-label="Action">
                                <form class="ops-form" method="POST" action="{{ route('logistics.storage.pick') }}">
                                    @csrf
                                    <input type="hidden" name="parcel_reference" value="EZP:{{ $placement->parcel_code }}">
                                    <input type="hidden" name="location_reference" value="EZL:{{ $placement->location_code }}">
                                    <input type="hidden" name="method" value="manual">
                                    <button class="ops-btn" type="submit">Remove from location</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">No parcels currently have an active storage placement.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
