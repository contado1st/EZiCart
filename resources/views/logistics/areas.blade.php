@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Routing areas</h1>
            <p>Create operational zones, then map each province and municipality to its delivery zone. Multiple municipalities can use one area.</p>
        </div>
    </header>

    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Create a routing area</h2>
            <form class="ops-form" method="POST" action="{{ route('logistics.areas.store') }}">
                @csrf
                <div class="ops-field">
                    <label for="area-name">Area name</label>
                    <input id="area-name" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="e.g. North Sector">
                </div>
                <div class="ops-field">
                    <label for="area-code">Area code</label>
                    <input id="area-code" name="code" value="{{ old('code') }}" maxlength="255" pattern="[A-Za-z0-9_-]+" required placeholder="e.g. north-sector">
                </div>
                <button class="ops-btn ops-btn--primary" type="submit">Create area</button>
            </form>
        </div>

        <div class="ops-panel">
            <h2>Map a municipality</h2>
            <form class="ops-form" method="POST" action="{{ route('logistics.areas.municipalities.store') }}">
                @csrf
                <div class="ops-field">
                    <label for="mapping-area">Routing area</label>
                    <select id="mapping-area" name="area_id" required>
                        <option value="">Choose an area</option>
                        @foreach ($areas->where('is_active', true) as $area)
                            <option value="{{ $area->id }}" @selected(old('area_id') == $area->id)>
                                {{ $area->name }} ({{ $area->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="ops-field">
                    <label for="province">Province</label>
                    <input id="province" name="province" value="{{ old('province') }}" maxlength="191" required placeholder="e.g. Laguna">
                </div>
                <div class="ops-field">
                    <label for="municipality">City / municipality</label>
                    <input id="municipality" name="municipality" value="{{ old('municipality') }}" maxlength="191" required placeholder="e.g. Santa Cruz">
                </div>
                <button class="ops-btn ops-btn--primary" type="submit">Save mapping</button>
            </form>
        </div>
    </section>

    <section class="ops-panel">
        <h2>Configured areas <span class="ops-muted">{{ $areas->count() }} areas</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Area / Hub status</th>
                        <th>Mapped municipalities</th>
                        <th>Approved riders</th>
                        <th>Active parcels</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr>
                            <td data-label="Area">
                                <strong>{{ $area->name }}</strong>
                                <div class="ops-mono">{{ $area->code }}</div>
                                @if ($area->sorting_center_id === null)
                                    <form method="POST" action="{{ route('logistics.areas.claim', $area) }}">
                                        @csrf
                                        <button class="ops-btn" type="submit">Assign to this hub</button>
                                    </form>
                                    <span class="ops-status ops-status--amber">Unassigned legacy area</span>
                                @else
                                    <span class="ops-status ops-status--green">Assigned to this hub</span>
                                @endif
                            </td>
                            <td data-label="Municipalities">
                                @forelse ($area->municipalities as $mapping)
                                    <div class="ops-area-mapping">
                                        <span><strong>{{ $mapping->municipality }}, {{ $mapping->province }}</strong></span>
                                        <form class="ops-form" method="POST" action="{{ route('logistics.areas.municipalities.update', $mapping) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label for="mapping-{{ $mapping->id }}">Change area</label>
                                            <select id="mapping-{{ $mapping->id }}" name="area_id" required>
                                                @foreach ($areas->where('is_active', true) as $candidate)
                                                    <option value="{{ $candidate->id }}" @selected($candidate->id === $area->id)>
                                                        {{ $candidate->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="ops-btn" type="submit">Update</button>
                                        </form>
                                    </div>
                                @empty
                                    <span class="ops-muted">No municipalities mapped yet.</span>
                                @endforelse
                            </td>
                            <td data-label="Riders">
                                <span class="logistics-workload-tag {{ $area->riders_count > 0 ? 'logistics-workload-tag--light' : 'logistics-workload-tag--full' }}">
                                    {{ $area->riders_count }} rider(s)
                                </span>
                            </td>
                            <td data-label="Parcels">
                                <span class="ops-mono">{{ $area->orders_count }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No routing areas configured yet.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
