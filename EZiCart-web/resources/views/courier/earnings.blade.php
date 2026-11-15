@extends('layouts.courier')
@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Earnings</h1>
            <p>View courier compensation information.</p>
        </div>
    </header>
    <section class="ops-panel" role="status">
        <h2>Available financial balance: PHP {{ number_format($available / 100, 2) }}</h2>
        <p>Earnings are credited from approved tariff components after verified work and eligible settlement. Legacy shipping fees without an approved compensation schedule remain unallocated.</p>
        <div class="courier-actions"><a class="ops-btn" href="{{ route('finance.wallet') }}">Wallet and statements</a>
        <a class="ops-btn" href="{{ route('courier.history') }}">Work history and pending estimates</a></div>
        <p class="ops-muted">This table lists recorded allocations, with ledger verification and reversal state. Estimates appear in work history and are never included in the available balance.</p>
        <div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Order</th><th>Work component</th><th>Allocated PHP</th><th>Net settled PHP</th><th>Posting state</th><th>Posted</th></tr></thead><tbody>
        @forelse($allocations as $allocation)
            @php($posting = $postings[$allocation->id])
            <tr><td><a href="{{ route('courier.history', ['search' => $allocation->order_number]) }}">{{ $allocation->order_number }}</a></td><td>{{ ucfirst(str_replace('_', ' ', $allocation->component)) }} @if($posting['leg'])<div class="ops-muted">{{ ucfirst(str_replace('-', ' #', $posting['leg'])) }}</div>@endif</td><td>{{ number_format($allocation->amount_cents / 100, 2) }}</td><td>{{ number_format($posting['posted_cents'] / 100, 2) }}</td><td><strong>{{ $posting['status'] }}</strong><div class="ops-muted">{{ $posting['note'] }}</div></td><td>{{ $allocation->created_at }}</td></tr>
        @empty<tr><td colspan="6">No settled logistics earnings have been posted.</td></tr>@endforelse
        </tbody></table></div>{{ $allocations->links() }}
    </section>
@endsection
