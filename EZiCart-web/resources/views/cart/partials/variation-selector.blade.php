@if(!empty($item['variation_options']))
    @php
        $retryingVariation = old('cart_key') === (string) $key && $errors->any();
        $chosenVariation = $retryingVariation && is_scalar(old('variation_id')) ? (string) old('variation_id') : (string) ($item['variation_id'] ?? '');
        $hasChosenVariation = in_array($chosenVariation, array_map(fn ($option) => (string) $option['id'], $item['variation_options']), true);
        $hasAvailableVariation = count(array_filter($item['variation_options'], fn ($option) => $option['selectable'] && $option['unit_cents'] > 0)) > 0;
        $previewOption = collect($item['variation_options'])->first(fn ($option) => (string) $option['id'] === $chosenVariation);
    @endphp
    <details class="cart-variation-editor" @if($retryingVariation) open @endif>
        <summary>Change variation</summary>
        <form method="POST" action="{{ route('cart.variation', $key) }}" class="cart-variation-form" data-cart-variation-preview>
            @csrf @method('PATCH')
            <input type="hidden" name="cart_key" value="{{ $key }}">
            <label for="cart-variation-{{ $key }}">Choose variation<span class="cart-sr-only"> for {{ $item['name'] }}</span></label>
            <select id="cart-variation-{{ $key }}" name="variation_id" required aria-describedby="cart-variation-help-{{ $key }}" data-variant-preview-select>
                <option value="" disabled @selected(!$hasChosenVariation)>Choose an available variation</option>
                @foreach($item['variation_options'] as $option)
                    <option value="{{ $option['id'] }}" data-preview-image="{{ $option['image_url'] }}" data-preview-label="{{ $option['label'] }}" data-preview-own-image="{{ $option['has_own_image'] ? '1' : '0' }}" @selected($chosenVariation === (string) $option['id']) @disabled(!$option['selectable'] || $option['unit_cents'] < 1)>{{ $option['label'] }} — &#8369;{{ number_format($option['unit_cents'] / 100, 2) }} · {{ $option['stock'] }} in stock{{ !$option['selectable'] ? ' (not enough for this quantity)' : '' }}</option>
                @endforeach
            </select>
            <figure class="cart-variant-photo">
                <img data-variant-preview-image @if($previewOption && $previewOption['image_url']) src="{{ $previewOption['image_url'] }}" @else hidden @endif alt="{{ $previewOption['label'] ?? 'Selected variation' }}" width="96" height="96">
                <figcaption data-variant-preview-caption aria-live="polite">{{ $previewOption ? ($previewOption['image_url'] ? ($previewOption['has_own_image'] ? 'Variation photo: ' : 'Product photo: ') : 'No photo available: ').$previewOption['label'] : 'Choose a variation to preview its photo.' }}</figcaption>
            </figure>
            <small id="cart-variation-help-{{ $key }}">Keeps your quantity. An option already in your cart combines quantities. Offers and availability are checked when you save.</small>
            <button class="cart-variation-save" type="submit" @disabled(!$hasAvailableVariation)>Save variation</button>
        </form>
    </details>
@endif
