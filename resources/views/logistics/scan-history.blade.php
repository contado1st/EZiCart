@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Hub audit trail</div>
            <h1>Scan history</h1>
            <p>Recent parcel, location, and rider scans recorded for this hub. Personal addresses and contact details are not shown.</p>
        </div>
    </header>
    <section class="ops-panel">
        <form class="ops-form" method="GET">
            <div class="ops-field">
                <label for="station">Station</label>
                <select id="station" name="station">
                    <option value="">All stations</option>
                    @foreach (['intake', 'seller_pickup', 'seller_handover', 'seller_return_receipt', 'putaway', 'pick', 'cycle_count', 'dispatch_release', 'dispatch_batch_rider', 'dispatch_batch_parcel', 'dispatch_batch_release', 'delivery_start', 'return_to_seller', 'rider_exception_intake', 'failed_return_intake', 'parcel_label_reprint'] as $station)
                        <option value="{{ $station }}" @selected(request('station') === $station)>{{ str_replace('_', ' ', ucfirst($station)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ops-field">
                <label for="result">Result</label>
                <select id="result" name="result">
                    <option value="">All results</option>
                    @foreach (['accepted', 'rejected', 'correct', 'missing', 'unexpected'] as $result)
                        <option value="{{ $result }}" @selected(request('result') === $result)>{{ ucfirst($result) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Filter scans</button>
        </form>
    </section>
    <section class="ops-panel">
        <h2>Recorded scans <span class="ops-muted">{{ $scans->total() }} records</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr><th>Time</th><th>Parcel</th><th>Station</th><th>Result</th><th>Method</th><th>Operator</th><th>Details</th></tr>
                </thead>
                <tbody>
                    @forelse ($scans as $scan)
                        <tr>
                            <td>{{ $scan->created_at }}</td>
                            <td class="ops-mono">{{ $scan->order_number ?? 'Unresolved reference' }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($scan->station)) }}</td>
                            <td><span class="ops-status">{{ ucfirst($scan->result) }}</span></td>
                            <td>{{ ucfirst($scan->method) }}</td>
                            <td>{{ trim(($scan->actor_first_name ?? '').' '.($scan->actor_last_name ?? '')) ?: 'Former operator' }}</td>
                            <td>{{ $scan->failure_reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="ops-empty">No scan records match this hub and filter.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $scans->links() }}</div>
    </section>
@endsection
