@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/shared/disputes.css')
@endpush

@section('content')
    <div class="dispute-form-wrapper">
        <a href="{{ route('buyer.dashboard') }}" class="u-extracted-234189090c">
            ← Back to Orders
        </a>

        <h1 class="u-extracted-df12e366b7">
            File an Order Dispute
        </h1>
        <p class="u-extracted-6114e04314">
            Submit a claim for damaged goods, wrong items, or fulfillment failures for admin intervention.
        </p>

        <div class="dispute-order-meta">
            <div><strong>Order Reference:</strong> {{ $order->order_number }}</div>
            <div><strong>Merchant:</strong> {{ $order->seller->business_name ?? 'Store Merchant' }}</div>
            <div><strong>Total Value:</strong> ₱{{ number_format($order->total_amount, 2) }}</div>
        </div>

        <form action="{{ route('buyer.orders.dispute.store', $order->id) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="u-extracted-f267e3ca68">
                <label class="u-extracted-66f2146a90">
                    Reason for Complaint *
                </label>
                <select name="reason" class="search-input u-extracted-76a6870449" required>
                    <option value="">-- Choose Dispute Reason --</option>
                    <option value="Damaged Item">Damaged Item (Broken during transit or defective packaging)</option>
                    <option value="Wrong Item Delivered">Wrong Item Delivered (Incorrect item / variation received)</option>
                    <option value="Missing Items">Missing Items (Incomplete parcel contents)</option>
                    <option value="Non-Delivery">Non-Delivery (Marked delivered but unreceived)</option>
                    <option value="Defective Goods">Defective Goods (Item does not function as described)</option>
                    <option value="Other">Other Operational Complaint</option>
                </select>
            </div>

            <div class="u-extracted-f267e3ca68">
                <label class="u-extracted-66f2146a90">
                    Incident Explanation *
                </label>
                <textarea name="description" rows="5" class="search-input u-extracted-a1b64160fb"
                    placeholder="Describe exactly what issue occurred upon opening your parcel..." required>{{ old('description') }}</textarea>
            </div>

            <div class="u-extracted-f0b24eea08">
                <label class="u-extracted-66f2146a90">
                    Attach Photo Evidence (Packaging / Damaged Goods)
                </label>
                <input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.pdf"
                    class="search-input u-extracted-76a6870449">
            </div>

            <button type="submit" class="btn-primary u-extracted-71ed464c0d">
                Submit Dispute Ticket
            </button>
        </form>
    </div>
@endsection
