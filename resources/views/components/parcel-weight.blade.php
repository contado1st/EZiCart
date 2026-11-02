@props(['order', 'compact' => false])
<span @class(['parcel-weight-label', 'is-missing' => !$order->parcel_weight_grams])>Packed weight: {{ \App\Services\ParcelLoadService::kg($order->parcel_weight_grams) }}@if(!$order->parcel_weight_grams) — pickup/dispatch blocked @endif</span>
@if(!$compact)
    @php
        $estimate = app(\App\Services\ParcelLoadService::class)->estimate($order);
        $canWeigh = (auth()->user()->role === 'seller' && auth()->id() === $order->seller_id && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true))
            || (auth()->user()->role === 'sorting_center' && auth()->id() === $order->sorting_center_id && in_array($order->status, ['AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER'], true) && !$order->hub_released_at);
    @endphp
    <p class="web-muted">Item estimate: {{ \App\Services\ParcelLoadService::kg($estimate) }}. Actual packed weight must include packaging.@if($order->weight_recorded_at) Last weighed {{ $order->weight_recorded_at->format('d M Y, h:i A') }}.@endif</p>
    @if(in_array(auth()->user()->role, ['seller', 'courier', 'sorting_center', 'logistics_admin'], true))
        @php($order->loadMissing(['pickupCourier', 'deliveryCourier']))
        @foreach(collect([$order->pickupCourier, $order->deliveryCourier])->filter()->unique('id') as $assignedRider)<x-vehicle-capacity :rider="$assignedRider" />@endforeach
        <a class="dash-action-link" href="{{ route('load-planning.index') }}">Check combined vehicle load and remaining capacity</a>
    @endif
    @if($estimate !== null && $order->parcel_weight_grams && $order->parcel_weight_grams < $estimate)<p class="profile-field-error">Measured weight is below the item estimate. Check the scale and product weights before planning transport.</p>@endif
    @if($canWeigh)
        <details class="parcel-weight-editor"><summary>{{ $order->parcel_weight_grams ? 'Reweigh packed parcel' : 'Record packed parcel weight' }}</summary>
            <form class="web-form" method="POST" action="{{ route('orders.weight', $order) }}" data-decision="parcel-weight" data-decision-title="Record measured parcel weight?" data-decision-message="Use the actual packed weight from a scale. This updates load planning and may require a different vehicle." data-decision-label="Record weight" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf @method('PATCH')
                <label for="parcel-weight-{{ $order->id }}">Packed parcel weight (kg)<input id="parcel-weight-{{ $order->id }}" type="number" name="parcel_weight_kg" min="0.001" max="100000" step="0.001" value="{{ old('parcel_weight_kg', $order->parcel_weight_grams ? $order->parcel_weight_grams / 1000 : '') }}" required></label>
                <label for="parcel-weight-reason-{{ $order->id }}">Measurement note<input id="parcel-weight-reason-{{ $order->id }}" name="reason" minlength="5" maxlength="500" required placeholder="Scale measurement and reason for any correction"></label><button class="dash-btn-sm dash-btn-primary" type="submit">Record actual weight</button>
            </form>
        </details>
    @endif
@endif
