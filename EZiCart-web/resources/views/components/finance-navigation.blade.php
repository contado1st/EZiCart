<nav class="workspace-order-tabs" aria-label="Financial operations">
    <a href="{{ route('finance.wallet') }}">Finance &amp; Wallet</a>
    @if(auth()->user()->role !== 'sorting_center')<a href="{{ route('finance.index') }}" title="Order payments, remittances, refunds and payouts">Financial records</a>@endif
    <a href="{{ route('finance.shipments') }}">Shipping breakdowns</a>
    @if(in_array(auth()->user()->role, ['courier', 'sorting_center', 'logistics_admin'], true))<a href="{{ route('finance.batches') }}">Cash remittance batches</a>@endif
    @if(auth()->user()->isLogisticsAdmin())<a href="{{ route('finance.rates') }}">Delivery tariffs</a>@endif
</nav>
