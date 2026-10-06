<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waybill - {{ $order->order_number }}</title>
    @vite('resources/css/seller/waybill.css')
</head>

<body class="waybill-body">

    <div class="waybill-wrapper">
        <!-- Screen Actions Toolbar -->
        <div class="waybill-actions-bar">
            <a href="{{ route('seller.orders.index') }}" class="waybill-back-link">
                ← Back to Order Management
            </a>
            <button type="button" onclick="window.print()" class="waybill-print-btn">
                Print Waybill / Shipping Slip
            </button>
        </div>

        @if (in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true) &&
                $order->pickup_claimed_at === null &&
                $order->seller_handover_at === null)
            <section class="waybill-actions-bar" aria-label="Replace parcel label">
                <p>Current parcel label version: {{ $order->parcel_code_version }}. Replacing the code invalidates any
                    previously printed label.</p>
                <form method="POST" action="{{ route('seller.orders.waybill.reprint', $order) }}">
                    @csrf
                    <label for="reprint-reason">Reason for replacement</label>
                    <input id="reprint-reason" name="reason" maxlength="500" required>
                    <button type="submit" class="waybill-print-btn">Issue replacement label</button>
                </form>
            </section>
        @endif

        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif

        <!-- Official Printable Waybill -->
        <div class="waybill-sheet">
            <div class="waybill-brand-row">
                <div>
                    <h1 class="waybill-title">EZiCart Express</h1>
                    <div class="u-extracted-88437780af">Standard Courier Parcel
                        Dispatch</div>
                </div>
                <div class="waybill-tracking-block">
                    <div class="waybill-tracking-number">{{ $order->order_number }}</div>
                    <div class="waybill-parcel-code">Parcel: {{ $order->parcel_code }}</div>
                    <div class="waybill-qr">{!! $parcelQr !!}</div>
                </div>
            </div>

            <!-- Sender / Recipient Address Information -->
            <div class="waybill-parties-grid">
                <div class="waybill-party-cell">
                    <div class="waybill-section-tag">Sender (Merchant)</div>
                    <div class="waybill-party-name">{{ $order->seller->business_name ?? 'EZiCart Seller' }}</div>
                    <div class="waybill-party-text">{{ $order->seller->contact_no }}</div>
                    <div class="waybill-party-text">{{ $order->seller->street_address }},
                        {{ $order->seller->barangay }}
                    </div>
                    <div class="waybill-party-text">{{ $order->seller->municipality }}, {{ $order->seller->province }}
                    </div>
                </div>

                <div class="waybill-party-cell">
                    <div class="waybill-section-tag">Recipient (Buyer)</div>
                    <div class="waybill-party-name">{{ $order->recipient_name }}</div>
                    <div class="waybill-party-text">{{ $order->recipient_contact }}</div>
                    <div class="waybill-party-text">{{ $order->street_address }}, {{ $order->barangay }}</div>
                    <div class="waybill-party-text">{{ $order->municipality }}, {{ $order->province }}</div>
                </div>
            </div>

            <!-- Parcel Items Detail -->
            <table class="waybill-items-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th class="u-extracted-188b5f39d0">Qty</th>
                        <th class="u-extracted-f337f3c9a6">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td class="u-extracted-72a68ac344">{{ $item->quantity }}</td>
                            <td class="u-extracted-13cbe03b9a">₱{{ number_format($item->item_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Footnote & Collection Protocol -->
            <div class="waybill-footer-grid">
                <div class="waybill-instructions-cell">
                    <div class="waybill-section-tag">Delivery Instructions & Remarks</div>
                    <div>
                        {{ $order->notes ?? 'No special courier notes provided. Deliver during standard dispatch hours.' }}
                    </div>
                </div>

                <div class="waybill-amount-cell">
                    <div class="waybill-amount-label">Payment Method: {{ $order->payment_method }}</div>
                    <div class="waybill-total-price">
                        ₱{{ number_format($order->total_amount, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
