<nav class="workspace-order-tabs" aria-label="Financial operations">
    <a href="{{ route('finance.wallet') }}" @if(request()->routeIs('finance.wallet')) aria-current="page" @endif>Finance &amp; Wallet</a>
    @if(auth()->user()->role !== 'sorting_center')<a href="{{ route('finance.index') }}" title="Order payments, remittances, refunds and payouts" @if(request()->routeIs('finance.index')) aria-current="page" @endif>Financial records</a>@endif
    <a href="{{ route('finance.shipments') }}" @if(request()->routeIs('finance.shipments')) aria-current="page" @endif>Shipping breakdowns</a>
    @if(in_array(auth()->user()->role, ['courier', 'sorting_center', 'logistics_admin'], true))<a href="{{ route('finance.batches') }}" @if(request()->routeIs('finance.batches')) aria-current="page" @endif>Cash remittance batches</a>@endif
    @if(auth()->user()->isLogisticsAdmin())<a href="{{ route('finance.rates') }}" @if(request()->routeIs('finance.rates')) aria-current="page" @endif>Delivery tariffs</a>@endif
</nav>
