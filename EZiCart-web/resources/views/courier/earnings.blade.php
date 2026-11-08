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
        <a class="ops-btn" href="{{ route('finance.wallet') }}">Wallet and statements</a>
        <div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Order</th><th>Work component</th><th>Earned PHP</th><th>Posted</th></tr></thead><tbody>@forelse($allocations as $allocation)<tr><td>{{ $allocation->order_number }}</td><td>{{ str_replace('_', ' ', $allocation->component) }}</td><td>{{ number_format($allocation->amount_cents / 100, 2) }}</td><td>{{ $allocation->created_at }}</td></tr>@empty<tr><td colspan="4">No settled logistics earnings have been posted.</td></tr>@endforelse</tbody></table></div>{{ $allocations->links() }}
    </section>
@endsection
