@props(['product'])
<span>{{ $product->display_price_label }}</span>@if($product->display_price_label !== $product->original_display_price_label)<small><s>{{ $product->original_display_price_label }}</s> <span>Sale{{ ! $product->currentFlashSale && $product->currentSale?->kind === 'percent' ? ' · '.$product->currentSale->value.'% off' : '' }}</span></small>@endif
@if($product->currentFlashSale)<small>Flash · {{ min($product->stock, $product->currentFlashSale->quantity_limit - $product->currentFlashSale->allocated_quantity) }} left</small>@endif
