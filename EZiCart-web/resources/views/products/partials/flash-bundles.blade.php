@if($flashSlot)
<section class="pdp-purchase-facts" aria-label="Flash sale"><strong>{{ $flashSlot->starts_at->isFuture() ? 'Upcoming flash sale' : 'Flash sale' }} · PHP {{ number_format($flashSlot->unit_cents / 100, 2) }}</strong>
<p>{{ min($product->stock, $flashSlot->quantity_limit - $flashSlot->allocated_quantity) }} flash units left. Units beyond the remaining allocation use the current store-sale or ordinary price.</p>
@if($flashSlot->starts_at->isFuture())<p>Starts {{ $flashSlot->starts_at->timezone('Asia/Manila')->format('d M Y H:i:s') }} Philippine time.</p>@else<p>Ends in <span data-flash-timer data-ends-at="{{ $flashSlot->ends_at->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}">{{ $flashSlot->ends_at->timezone('Asia/Manila')->format('H:i:s') }}</span></p>@endif
@if(auth()->user()?->role === 'buyer')<form method="POST" action="{{ route('buyer.flash-reminders.store', $flashSlot) }}">@csrf<input type="hidden" name="enabled" value="{{ $flashReminder ? 0 : 1 }}"><button class="pdp-text-button" type="submit">{{ $flashReminder ? 'Cancel reminder' : 'Remind me' }}</button></form>@else<a href="{{ route('login') }}">Sign in for a reminder</a>@endif
<small>Price and available quantity are confirmed at checkout.</small></section>
@endif
@foreach($bundleOffers as $offer)
@if($offer->products->isNotEmpty() && ($offer->kind !== 'gift' || $offer->gift))<section class="pdp-purchase-facts"><strong>{{ $offer->name }}</strong><p>Choose {{ $offer->threshold }} qualifying units (mixed options count){{ $offer->kind === 'percent' ? ' for '.$offer->percent.'% off eligible items.' : ' and select up to '.$offer->gift_quantity.' gift unit(s) free per qualifying set.' }}</p>
<p>Qualifying products: @foreach($offer->products as $eligible)<a href="{{ route('product.show', $eligible) }}">{{ $eligible->name }}</a>{{ $loop->last ? '' : ', ' }}@endforeach</p>
@if($offer->kind === 'gift')<p>Gift: <a href="{{ route('product.show', $offer->gift) }}">{{ $offer->gift->name }}</a>. Add the gift and qualifying items to the selected cart together.</p>@endif
<small>Checkout rechecks qualification, stock, purchase limits and parcel weight.</small></section>@endif
@endforeach
@once<script src="{{ asset('js/flash-promotions.js') }}?v={{ filemtime(public_path('js/flash-promotions.js')) }}" defer></script>@endonce
