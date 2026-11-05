@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Scan audit history</h1>
            <p>Parcel, storage location, and rider scans recorded for this hub. Real-time audit trail across intake, sorting, and dispatch.</p>
        </div>
    </header>

    <section class="ops-panel">
        <form class="ops-form" method="GET" action="{{ route('logistics.scan-history') }}" aria-label="Filter scan history">
            <div class="ops-field">
                <label for="station">Station</label>
                <select id="station" name="station">
                    <option value="">All stations</option>
                    @foreach (['intake', 'seller_pickup', 'seller_handover', 'seller_return_receipt', 'putaway', 'pick', 'cycle_count', 'dispatch_release', 'dispatch_batch_rider', 'dispatch_batch_parcel', 'dispatch_batch_release', 'delivery_start', 'return_to_seller', 'rider_exception_intake', 'failed_return_intake', 'parcel_label_reprint'] as $station)
                        <option value="{{ $station }}" @selected(request('station') === $station)>
                            {{ str_replace('_', ' ', ucfirst($station)) }}
                        </option>
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
            <button class="ops-btn ops-btn--primary" type="submit">Apply filters</button>
            @if (request()->filled('station') || request()->filled('result'))
                <a class="ops-btn" href="{{ route('logistics.scan-history') }}">Clear filters</a>
            @endif
        </form>

        @if (request()->filled('station') || request()->filled('result'))
            <ul class="ops-filter-chips" aria-label="Active filters">
                @if (request()->filled('station'))
                    <li>Station: <strong>{{ str_replace('_', ' ', ucfirst(request('station'))) }}</strong></li>
                @endif
                @if (request()->filled('result'))
                    <li>Result: <strong>{{ ucfirst(request('result')) }}</strong></li>
                @endif
            </ul>
        @endif
    </section>

    <section class="ops-panel">
        <h2>Recorded scans <span class="ops-muted">{{ $scans->total() }} records</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Parcel</th>
                        <th>Station</th>
                        <th>Result</th>
                        <th>Scan method</th>
                        <th>Operator</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($scans as $scan)
                        <tr>
                            <td data-label="Timestamp" class="ops-mono">{{ $scan->created_at }}</td>
                            <td data-label="Parcel" class="ops-mono">
                                <strong>{{ $scan->order_number ?? 'Unresolved reference' }}</strong>
                            </td>
                            <td data-label="Station">{{ str_replace('_', ' ', ucfirst($scan->station)) }}</td>
                            <td data-label="Result">
                                @php
                                    $resultClass = match (strtolower($scan->result)) {
                                        'accepted', 'correct' => 'ops-status--green',
                                        'rejected', 'missing' => 'ops-status--danger',
                                        'unexpected' => 'ops-status--amber',
                                        default => 'ops-status--pink',
                                    };
                                @endphp
                                <span class="ops-status {{ $resultClass }}">{{ ucfirst($scan->result) }}</span>
                            </td>
                            <td data-label="Method">{{ ucfirst($scan->method) }}</td>
                            <td data-label="Operator">
                                {{ trim(($scan->actor_first_name ?? '') . ' ' . ($scan->actor_last_name ?? '')) ?: 'Former operator' }}
                            </td>
                            <td data-label="Details">{{ $scan->failure_reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="ops-empty">No scan records match this hub and filter criteria.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $scans->links() }}</div>
    </section>
@endsection
