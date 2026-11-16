@props(['name', 'image' => null, 'src' => null, 'missingLabel' => 'Image unavailable'])
<div {{ $attributes->class(['product-table-item']) }}>
    <x-product-thumbnail :path="$image" :src="$src" :label="$name" :missing-label="$missingLabel" />
    <div class="product-table-copy"><div class="product-table-name">{{ $name }}</div>{{ $slot }}</div>
</div>
