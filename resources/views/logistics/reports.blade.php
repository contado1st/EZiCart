@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Parcel throughput</h1>
            <p>Counts and throughput are derived from parcel order lifecycle timestamps within the selected reporting window.</p>
        </div>
    </header>

    <section class="ops-panel">
        <form class="ops-form" method="GET" action="{{ route('logistics.reports') }}" aria-label="Select report period">
            <div class="ops-field">
                <label for="report-from">From date</label>
                <input id="report-from" type="date" name="from" value="{{ $from->toDateString() }}">
            </div>
            <div class="ops-field">
                <label for="report-to">To date</label>
                <input id="report-to" type="date" name="to" value="{{ $to->toDateString() }}">
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Update report</button>
        </form>
    </section>

    {{-- Priority Metrics Row --}}
    <section class="ops-stats ops-stats--priority" aria-label="Key operational indicators">
        <div class="ops-stat">
            <span>Received</span>
            <strong>{{ $stats['received'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Sorted</span>
            <strong>{{ $stats['sorted'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Dispatched</span>
            <strong>{{ $stats['dispatched'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Delivered</span>
            <strong>{{ $stats['delivered'] }}</strong>
        </div>
        <div class="ops-stat ops-stat--danger">
            <span>Failed attempts</span>
            <strong>{{ $stats['failed'] }}</strong>
        </div>
    </section>

    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Orders placed by day</h2>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($volume as $day)
                            <tr>
                                <td data-label="Date" class="ops-mono">{{ $day->day }}</td>
                                <td data-label="Orders"><strong>{{ $day->total }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">
                                    <div class="ops-empty">No order volume recorded for this period.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ops-panel">
            <h2>Parcel count by destination area</h2>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Routing area</th>
                            <th>Parcels</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($areaCounts as $area)
                            <tr>
                                <td data-label="Area">
                                    <strong>{{ $area->destinationArea?->name ?? 'Unassigned area' }}</strong>
                                </td>
                                <td data-label="Parcels"><strong>{{ $area->total }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">
                                    <div class="ops-empty">No sorted parcels for this period.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ops-panel">
        <div class="ops-banner">
            <div>
                <h2>Current operational backlog</h2>
                <p>
                    <strong>{{ $stats['backlog'] }}</strong> parcels are currently in pickup, hub, sorting, or delivery queues.
                    Backlog is a live snapshot of items requiring hub action; date selection does not alter this active count.
                </p>
            </div>
            <a class="ops-btn ops-btn--primary" href="{{ route('logistics.dashboard') }}">Open triage board</a>
        </div>
    </section>
@endsection
