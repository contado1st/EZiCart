@php
    $hasOptions = old('variations_enabled', $draftValues['variations_enabled'] ?? (isset($product) ? $product->usesVariations() : count($draftValues['variations'] ?? []) > 0));
@endphp
<div class="form-group">
    <input type="hidden" name="variations_enabled" value="0">
    <label class="form-label" for="variations-enabled"><input id="variations-enabled" type="checkbox" name="variations_enabled" value="1" @checked($hasOptions) data-variations-toggle aria-controls="variation-options" aria-describedby="variation-mode-help"> Enable variations</label>
    <p id="variation-mode-help">Use variations for options with individual prices and stock. Turn off for one price and stock quantity.</p>
</div>
<fieldset class="form-grid border-0 p-0 min-w-0" data-base-pricing @if($hasOptions) hidden disabled @endif>
    <legend class="sr-only">Base product pricing and inventory</legend>
    <div class="form-group"><label class="form-label" for="base-product-price">Price (PHP) *</label><input id="base-product-price" type="number" step="0.01" min="0.01" max="99999999.99" name="price" class="form-control" value="{{ old('price', $draftValues['price'] ?? ($product->price ?? '')) }}" required></div>
    <div class="form-group"><label class="form-label" for="base-product-stock">Stock quantity *</label><input id="base-product-stock" type="number" min="0" name="stock" class="form-control" value="{{ old('stock', $draftValues['stock'] ?? ($product->stock ?? '')) }}" required></div>
</fieldset>
