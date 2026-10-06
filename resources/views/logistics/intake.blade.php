@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Parcel intake</div>
            <h1>Scan and receive</h1>
            <p>Only parcels picked up by a rider are eligible for hub handoff.</p>
        </div>
    </header>
    <section class="ops-panel">
        <h2>Find a parcel</h2>
        <form class="ops-form" action="{{ route('logistics.scan') }}" method="POST">@csrf<div class="ops-field"><label
                    for="reference">Order number / waybill reference</label><input id="reference" name="reference"
                    value="{{ old('reference', request('search')) }}" required autocomplete="off" autofocus
                    placeholder="EZC-…"></div>
            <div class="ops-field"><label for="location_reference">Returns / Exception location for failed parcels
                    (optional)</label>
                <input id="location_reference" name="location_reference" placeholder="EZL:…">
            </div>
            <input id="scan-method" type="hidden" name="method" value="manual">
            <button class="ops-btn ops-btn--primary" type="submit">Verify and receive</button>
        </form>
        <div class="ops-form">
            <button class="ops-btn" id="start-camera-scan" type="button">Use camera</button>
            <button class="ops-btn" id="stop-camera-scan" type="button" hidden>Stop camera</button>
            <p class="ops-muted" id="camera-scan-status" role="status">Handheld scanners and typed references are also
                supported. Camera scanning requires browser support and camera permission.</p>
            <video id="camera-scan-video" playsinline muted hidden class="u-extracted-026d481fdb"></video>
        </div>
        <p class="ops-muted">Barcode scanners can type into this field and submit. Unknown or duplicate references are
            rejected.</p>
    </section>
    <section class="ops-panel">
        <h2>Inbound parcels <span class="ops-muted">{{ $parcels->total() }} awaiting receipt</span></h2>
        <form class="ops-form" method="GET" action="{{ route('logistics.intake') }}">
            <div class="ops-field"><label for="search">Search order number</label><input id="search" name="search"
                    value="{{ request('search') }}"></div><button class="ops-btn" type="submit">Search</button>
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
                            <td>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</td>
                            <td>{{ $order->pickupCourier?->first_name ?? '—' }} {{ $order->pickupCourier?->last_name }}</td>
                            <td>{{ $order->municipality }}, {{ $order->province }}</td>
                            <td>
                                <form method="POST" action="{{ route('logistics.orders.receive', $order) }}">@csrf<button
                                        class="ops-btn ops-btn--primary" type="submit">Confirm hub handoff</button></form>
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="5">
                                <div class="ops-empty">No inbound parcels are waiting for intake.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $parcels->links() }}</div>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const startButton = document.getElementById('start-camera-scan');
            const stopButton = document.getElementById('stop-camera-scan');
            const video = document.getElementById('camera-scan-video');
            const reference = document.getElementById('reference');
            const method = document.getElementById('scan-method');
            const status = document.getElementById('camera-scan-status');
            const form = reference.form;
            let stream = null;
            let active = false;

            const stop = () => {
                active = false;
                stream?.getTracks().forEach((track) => track.stop());
                stream = null;
                video.srcObject = null;
                video.hidden = true;
                startButton.hidden = false;
                stopButton.hidden = true;
            };

            stopButton.addEventListener('click', stop);
            startButton.addEventListener('click', async () => {
                if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                    status.textContent =
                        'Camera scanning is not supported here. Use a handheld scanner or type the reference.';
                    return;
                }

                try {
                    const detector = new BarcodeDetector({
                        formats: ['qr_code']
                    });
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment'
                        }
                    });
                    video.srcObject = stream;
                    video.hidden = false;
                    await video.play();
                    active = true;
                    startButton.hidden = true;
                    stopButton.hidden = false;
                    method.value = 'camera';
                    status.textContent = 'Point the camera at a parcel or location QR code.';

                    const scan = async () => {
                        if (!active) return;
                        try {
                            const codes = await detector.detect(video);
                            if (codes.length > 0 && codes[0].rawValue) {
                                reference.value = codes[0].rawValue;
                                status.textContent =
                                    'QR code captured. Submitting for server validation…';
                                form.requestSubmit();
                                return;
                            }
                        } catch (error) {
                            status.textContent =
                                'Could not read this QR code. Try again or enter the reference manually.';
                        }
                        window.requestAnimationFrame(scan);
                    };
                    window.requestAnimationFrame(scan);
                } catch (error) {
                    stop();
                    status.textContent =
                        'Camera access was unavailable. Use a handheld scanner or type the reference.';
                }
            });

            form.addEventListener('submit', () => {
                if (!active) method.value = 'manual';
                stop();
            });
        });
    </script>
@endsection
