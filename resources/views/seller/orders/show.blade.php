@extends('layouts.seller')

@section('title', 'Order #' . $order->order_number . ' - EZiCart Seller')

@section('content')
<div class="dash-header">
    <div>
        <a href="{{ route('seller.orders.index') }}" style="color: var(--slate-500); text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
            ← Back to All Orders
        </a>
        <h1 class="dash-title">Order #{{ $order->order_number }}</h1>
        <p class="dash-subtitle">Placed on {{ $order->created_at->format('M d, Y h:i A') }} by <strong>{{ $order->buyer->first_name ?? 'Buyer' }} {{ $order->buyer->last_name ?? '' }}</strong></p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="dash-btn-sm" style="background: white; border: 1px solid var(--slate-300); color: var(--slate-700); text-decoration: none; padding: 0.5rem 0.85rem;">
            🖨️ Print Waybill
        </a>

        @if($order->status === 'PLACED')
            <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="PREPARING">
                <button type="submit" class="dash-btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                    📦 Confirm & Pack Order
                </button>
            </form>
        @elseif(in_array($order->status, ['PREPARING', 'CONFIRMED']))
            <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="READY_FOR_PICKUP">
                <button type="submit" class="dash-btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem; background: #059669; border-color: #059669;">
                    ✓ Mark Ready for Pickup
                </button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success">✅ {{ session('success') }}</div>
@endif

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Left Column: Products -->
    <div>
        <div class="dash-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin: 0;">
                    Ordered Items ({{ $order->items->count() }})
                </h3>
                <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                    {{ str_replace('_', ' ', $order->status) }}
                </span>
            </div>

            @foreach($order->items as $item)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--slate-100);">
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        @if($item->product && $item->product->image_path)
                            <img src="{{ asset('storage/' . $item->product->image_path) }}" alt="{{ $item->product_name }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid var(--slate-200);">
                        @else
                            <div style="width: 50px; height: 50px; background: var(--slate-100); border-radius: 6px; display: flex; align-items: center; justify-content: center;">📦</div>
                        @endif
                        <div>
                            <strong style="color: var(--slate-800); font-size: 0.95rem;">{{ $item->product_name }}</strong>
                            @if($item->variation_info)
                                <div style="font-size: 0.8rem; color: var(--ez-primary); font-weight: 600;">
                                    Option: {{ $item->variation_info }}
                                </div>
                            @endif
                            <div style="font-size: 0.8rem; color: var(--slate-500); margin-top: 0.2rem;">
                                ₱{{ number_format($item->price, 2) }} &times; {{ $item->quantity }} units
                            </div>
                        </div>
                    </div>
                    <div style="font-weight: 800; color: var(--slate-800); font-size: 1rem;">
                        ₱{{ number_format($item->subtotal ?? $item->item_total, 2) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Right Column: Buyer & Financials -->
    <div>
        <!-- Shipping Destination -->
        <div class="dash-panel" style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin-bottom: 0.75rem;">
                📍 Delivery Destination
            </h3>
            <div style="font-weight: 700; color: var(--slate-800); font-size: 0.95rem;">
                {{ $order->recipient_name }}
            </div>
            <div style="font-size: 0.85rem; color: var(--slate-500); margin-top: 0.2rem;">
                📞 {{ $order->recipient_contact }}
            </div>
            <div style="font-size: 0.85rem; color: var(--slate-700); margin-top: 0.5rem; line-height: 1.4;">
                {{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}
            </div>
            @if($order->notes)
                <div style="margin-top: 0.75rem; background: var(--slate-50); padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.8rem; color: var(--slate-600);">
                    <strong>Notes:</strong> {{ $order->notes }}
                </div>
            @endif
        </div>

        <!-- Financial Summary -->
        <div class="dash-panel">
            <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin-bottom: 1rem;">
                💰 Financial Summary
            </h3>

            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--slate-600); margin-bottom: 0.5rem;">
                <span>Item Subtotal:</span>
                <span>₱{{ number_format($order->subtotal, 2) }}</span>
            </div>

            @if($order->discount_amount > 0)
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #059669; margin-bottom: 0.5rem;">
                    <span>Voucher Discount ({{ $order->voucher_code }}):</span>
                    <span>-₱{{ number_format($order->discount_amount, 2) }}</span>
                </div>
            @endif

            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #dc2626; margin-bottom: 0.5rem;">
                <span>10% Platform Commission:</span>
                <span>-₱{{ number_format($order->commission_fee, 2) }}</span>
            </div>

            <div style="height: 1px; background: var(--slate-200); margin: 0.75rem 0;"></div>

            <div style="display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: 800; color: var(--ez-primary); margin-bottom: 0.5rem;">
                <span>Net Store Payout:</span>
                <span>₱{{ number_format(max(0, $order->subtotal - $order->discount_amount - $order->commission_fee), 2) }}</span>
            </div>

            <div style="font-size: 0.8rem; color: var(--slate-500); margin-top: 0.5rem;">
                Payment Method: <strong>{{ $order->payment_method }}</strong>
            </div>
        </div>
    </div>
</div>
@endsection

