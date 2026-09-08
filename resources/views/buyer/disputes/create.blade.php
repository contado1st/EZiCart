@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}">
@endpush

@section('content')
<div class="dispute-form-wrapper">
    <a href="{{ route('buyer.dashboard') }}" style="color: var(--slate-500); font-size: 0.8125rem; font-weight: 700; text-decoration: none;">
        ← Back to Orders
    </a>

    <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--slate-900); margin: 0.75rem 0 0.25rem 0;">
        File an Order Dispute
    </h1>
    <p style="font-size: 0.875rem; color: var(--slate-500); margin-bottom: 1.5rem;">
        Submit a claim for damaged goods, wrong items, or fulfillment failures for admin intervention.
    </p>

    <div class="dispute-order-meta">
        <div><strong>Order Reference:</strong> {{ $order->order_number }}</div>
        <div><strong>Merchant:</strong> {{ $order->seller->business_name ?? 'Store Merchant' }}</div>
        <div><strong>Total Value:</strong> ₱{{ number_format($order->total_amount, 2) }}</div>
    </div>

    <form action="{{ route('buyer.orders.dispute.store', $order->id) }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div style="margin-bottom: 1rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem; color: var(--slate-700);">
                Reason for Complaint *
            </label>
            <select name="reason" class="search-input" style="width: 100%; border: 1px solid var(--slate-300);" required>
                <option value="">-- Choose Dispute Reason --</option>
                <option value="Damaged Item">Damaged Item (Broken during transit or defective packaging)</option>
                <option value="Wrong Item Delivered">Wrong Item Delivered (Incorrect item / variation received)</option>
                <option value="Missing Items">Missing Items (Incomplete parcel contents)</option>
                <option value="Non-Delivery">Non-Delivery (Marked delivered but unreceived)</option>
                <option value="Defective Goods">Defective Goods (Item does not function as described)</option>
                <option value="Other">Other Operational Complaint</option>
            </select>
        </div>

        <div style="margin-bottom: 1rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem; color: var(--slate-700);">
                Incident Explanation *
            </label>
            <textarea name="description" rows="5" class="search-input" style="width: 100%; border: 1px solid var(--slate-300); resize: vertical;" placeholder="Describe exactly what issue occurred upon opening your parcel..." required>{{ old('description') }}</textarea>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.35rem; color: var(--slate-700);">
                Attach Photo Evidence (Packaging / Damaged Goods)
            </label>
            <input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.pdf" class="search-input" style="width: 100%; border: 1px solid var(--slate-300);">
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; padding: 0.75rem; border: none; font-weight: 800; cursor: pointer;">
            Submit Dispute Ticket
        </button>
    </form>
</div>
@endsection