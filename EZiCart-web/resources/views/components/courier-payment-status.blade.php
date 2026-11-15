@props(['order', 'ready'])
@if($order->payment_method === 'COD')
    <div class="courier-cod-badge"><x-shop-icon name="money" /> Cash on Delivery: PHP {{ number_format($order->total_amount, 2) }}</div>
@elseif($ready)
    <span class="ops-status ops-status--green">Verified wallet payment</span><p class="ops-muted">Collect PHP 0.00. Do not collect cash. Buyer code, recipient confirmation and delivery proof are still required.</p>
@else
    <span class="ops-status ops-status--danger">Payment verification hold</span><p class="ops-muted">Do not dispatch or collect cash. Ask administration to review the recorded payment.</p>
@endif
