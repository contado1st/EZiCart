@props(['summary'])
@forelse($summary['rows'] as $row)
    <div class="py-2">
        <div class="flex flex-wrap items-center gap-2">
            <strong>{{ ucfirst(str_replace('_', ' ', $row['component'])) }}</strong>
            @if($row['leg'])<span class="ops-muted">{{ ucfirst(str_replace('-', ' #', $row['leg'])) }}</span>@endif
            <span class="ops-status {{ $row['status'] === 'Settled' ? 'ops-status--green' : 'ops-status--amber' }}">{{ $row['status'] }}</span>
            <span>{{ $row['amount_cents'] === null ? 'Amount unavailable' : 'PHP '.number_format($row['amount_cents'] / 100, 2) }}</span>
        </div>
        <p class="ops-muted">{{ $row['note'] }}</p>
        @if($row['work_recorded_at'])<p class="ops-muted">Work recorded: <time>{{ \Illuminate\Support\Carbon::parse($row['work_recorded_at'])->format('d M Y H:i') }}</time></p>@endif
        @if($row['recorded_at'])<p class="ops-muted">Ledger record: <time>{{ $row['recorded_at']->format('d M Y H:i') }}</time></p>@endif
    </div>
@empty
    <p class="ops-muted">No compensated work component is recorded for you. A past offer or failed attempt does not itself earn a delivery payment.</p>
@endforelse
