@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Logistics configuration</div>
            <h1>Routing areas</h1>
            <p>Create operational zones, then map each province and municipality to its delivery zone. Multiple municipalities can use one area.</p>
        </div>
    </header>

    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Create an area</h2>
            <form class="ops-form" method="POST" action="{{ route('logistics.areas.store') }}">
                @csrf
                <div class="ops-field">
                    <label for="area-name">Area name</label>
                    <input id="area-name" name="name" value="{{ old('name') }}" maxlength="255" required>
                </div>
                <div class="ops-field">
                    <label for="area-code">Area code</label>
                    <input id="area-code" name="code" value="{{ old('code') }}" maxlength="255" pattern="[A-Za-z0-9_-]+" required>
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
                            <option value="{{ $area->id }}" @selected(old('area_id') == $area->id)>{{ $area->name }} ({{ $area->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="ops-field">
                    <label for="province">Province</label>
                    <input id="province" name="province" value="{{ old('province') }}" maxlength="191" required>
                </div>
                <div class="ops-field">
                    <label for="municipality">City / municipality</label>
                    <input id="municipality" name="municipality" value="{{ old('municipality') }}" maxlength="191" required>
                </div>
                <button class="ops-btn ops-btn--primary" type="submit">Save mapping</button>
            </form>
        </div>
    </section>

    <section class="ops-panel">
        <h2>Configured areas</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Area / Hub</th>
                        <th>Mapped municipalities</th>
                        <th>Approved riders</th>
                        <th>Parcels</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr>
                            <td><strong>{{ $area->name }}</strong><br><span>{{ $area->code }}</span>
                                @if ($area->sorting_center_id === null)
                                    <form method="POST" action="{{ route('logistics.areas.claim', $area) }}">
                                        @csrf
                                        <button class="ops-btn" type="submit">Assign to this hub</button>
                                    </form>
                                    <span class="ops-muted">Unassigned legacy area</span>
                                @else
                                    <span class="ops-muted">Assigned to this hub</span>
                                @endif
                            </td>
                            <td>
                                @forelse ($area->municipalities as $mapping)
                                    <div class="ops-area-mapping">
                                        <span>{{ $mapping->municipality }}, {{ $mapping->province }}</span>
                                        <form class="ops-form" method="POST" action="{{ route('logistics.areas.municipalities.update', $mapping) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label for="mapping-{{ $mapping->id }}">Change area</label>
                                            <select id="mapping-{{ $mapping->id }}" name="area_id" required>
                                                @foreach ($areas->where('is_active', true) as $candidate)
                                                    <option value="{{ $candidate->id }}" @selected($candidate->id === $area->id)>{{ $candidate->name }}</option>
                                                @endforeach
                                            </select>
                                            <button class="ops-btn" type="submit">Update</button>
                                        </form>
                                    </div>
                                @empty
                                    <span>No municipalities mapped yet.</span>
                                @endforelse
                            </td>
                            <td>{{ $area->riders_count }}</td>
                            <td>{{ $area->orders_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No routing areas configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
