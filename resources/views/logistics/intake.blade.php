@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Parcel intake</h1>
            <p>Scan incoming parcel labels to verify hub custody from pickup riders.</p>
        </div>
        <div class="courier-actions">
            <span class="logistics-scan-station-badge">Intake Station Active</span>
        </div>
    </header>

    @if (session('success'))
        <div class="logistics-scan-result-card logistics-scan-result-card--success" role="status">
            <div>
                <strong>✓ Parcel verified & accepted</strong>
                <p>{{ session('success') }}</p>
            </div>
        </div>
    @elseif ($errors->any())
        <div class="logistics-scan-result-card logistics-scan-result-card--error" role="alert">
            <div>
                <strong>✕ Scan cannot be accepted</strong>
                <p>{{ $errors->first() }}</p>
            </div>
        </div>
    @endif

    <section class="ops-panel logistics-scan-console" aria-labelledby="intake-scan-title">
        <div class="logistics-scan-header">
            <div>
                <h2 id="intake-scan-title">Scan parcel</h2>
                <p class="ops-muted">Scan the EZP QR printed on the waybill with this station’s camera. A scanner gun and manual reference entry are also available.</p>
            </div>
            <div class="ops-scanner__tabs" role="group" aria-label="Parcel scanning method">
                <button type="button" class="ops-scanner__tab-btn is-active" data-scan-mode="camera" aria-pressed="true">QR camera</button>
                <button type="button" class="ops-scanner__tab-btn" data-scan-mode="handheld" aria-pressed="false">Scanner gun</button>
                <button type="button" class="ops-scanner__tab-btn" data-scan-mode="manual" aria-pressed="false">Manual entry</button>
            </div>
        </div>

        <form class="ops-form ops-intake-form logistics-qr-intake-form" action="{{ route('logistics.scan') }}" method="POST">
            @csrf
            <div class="ops-field">
                <label for="reference">Parcel QR code or reference</label>
                <input id="reference"
                    name="reference"
                    data-qr-input
                    class="logistics-scan-input-lg"
                    value="{{ old('reference', request('search')) }}"
                    required
                    autofocus
                    autocomplete="off"
                    placeholder="Scan a parcel QR code or enter EZP:…"
                    aria-describedby="intake-reference-help">
                <p class="ops-muted" id="intake-reference-help">The camera reads the EZP parcel QR printed on the waybill. A handheld scanner or order number also works.</p>
            </div>

            <div class="ops-field">
                <label for="location_reference">Returns / Exception location (for failed parcels)</label>
                <input id="location_reference"
                    name="location_reference"
                    class="logistics-scan-input-lg"
                    placeholder="EZL:… (optional)">
            </div>

            <input type="hidden" name="method" value="manual">

            <button class="ops-btn ops-btn--primary logistics-scan-btn-lg" type="submit">Verify parcel</button>

            <div class="ops-scanner__viewport-wrap" data-qr-video-container hidden>
                <video class="ops-scanner__video" data-qr-video playsinline muted hidden></video>
                <div class="ops-scanner__reticle" aria-hidden="true"></div>
            </div>

            <div class="ops-scan-controls">
                <button class="ops-btn ops-btn--primary" type="button" data-qr-start>Start QR camera</button>
                <button class="ops-btn ops-btn--danger" type="button" data-qr-stop hidden>Close camera</button>
                <p class="ops-muted" data-qr-status role="status" aria-live="polite">QR scanner ready. Start the camera to scan a parcel label.</p>
            </div>
        </form>
    </section>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Inbound parcels awaiting intake</h2>
                <p class="ops-muted">{{ $parcels->total() }} parcels currently with pickup couriers en route to this hub.</p>
            </div>
        </div>

        <form class="ops-form" method="GET" action="{{ route('logistics.intake') }}" aria-label="Search inbound parcels">
            <div class="ops-field">
                <label for="search">Filter by order number</label>
                <input id="search" name="search" value="{{ request('search') }}" placeholder="Order number…">
            </div>
            <button class="ops-btn" type="submit">Filter</button>
            @if (request()->filled('search'))
                <a class="ops-btn" href="{{ route('logistics.intake') }}">Clear</a>
            @endif
        </form>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Pickup rider</th>
                        <th>Destination</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parcels as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>
                                <strong>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</strong>
                                <div class="ops-muted">{{ $order->seller?->municipality }}</div>
                            </td>
                            <td>
                                {{ $order->pickupCourier ? $order->pickupCourier->first_name . ' ' . $order->pickupCourier->last_name : '—' }}
                            </td>
                            <td>{{ $order->municipality }}, {{ $order->province }}</td>
                            <td>
                                <form method="POST" action="{{ route('logistics.orders.receive', $order) }}">
                                    @csrf
                                    <button class="ops-btn ops-btn--primary" type="submit">Confirm hub handoff</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">
                                    <strong>All caught up</strong>
                                    <p>No inbound parcels are waiting for intake at this time.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $parcels->links() }}</div>
    </section>
@endsection
