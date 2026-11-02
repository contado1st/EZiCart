<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ProductVariationEditorService
{
    public function prepare(Request $request, array $data): array
    {
        if (isset($data['variations'])) {
            $data['variations'] = array_map(fn ($row) => Arr::only($row, ['id', 'type', 'value', 'price_adjustment', 'stock']), $data['variations']);
        }
        $extra = $request->validate(['variations' => ['nullable', 'array', 'max:100'], 'variations.*.sku' => ['nullable', 'string', 'max:64'], 'variations.*.stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variations.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']]);
        $paths = $combinations = $skus = [];
        foreach ($data['variations'] ?? [] as $index => &$row) {
            $key = mb_strtolower(trim($row['type']).':'.trim($row['value']));
            if (in_array($key, $combinations, true)) {
                throw ValidationException::withMessages(['variations' => 'Each variation combination must be unique.']);
            }
            $combinations[] = $key;
            $price = round((float) $data['price'] + (float) ($row['price_adjustment'] ?? 0), 2);
            if ($price < 0.01 || $price > 99999999.99) {
                throw ValidationException::withMessages(['variations' => 'Every variation must have a positive price within the supported amount.']);
            }
            $sku = trim($extra['variations'][$index]['sku'] ?? '');
            if ($sku !== '' && in_array(mb_strtolower($sku), $skus, true)) {
                throw ValidationException::withMessages(['variations' => 'Variation SKUs must be unique within a product.']);
            }
            if ($sku !== '') {
                $skus[] = mb_strtolower($sku);
            }
        }
        unset($row);
        try {
            foreach ($data['variations'] ?? [] as $index => $row) {
                if (array_key_exists('sku', $extra['variations'][$index] ?? [])) {
                    $data['variations'][$index]['sku'] = trim($extra['variations'][$index]['sku'] ?? '') ?: null;
                }
                if ($request->hasFile('variations.'.$index.'.image')) {
                    $path = $request->file('variations.'.$index.'.image')->store('products/variations', 'public');
                    if (! is_string($path)) {
                        throw new \RuntimeException('The variation photo could not be saved.');
                    }
                    $paths[] = $path;
                    $data['variations'][$index]['image_path'] = $path;
                }
            }
        } catch (\Throwable $e) {
            app(ProductMediaService::class)->deleteFiles($paths);
            throw $e;
        }

        return [$data, $paths];
    }

    public function needsReview(Product $product, array $rows): bool
    {
        $existing = $product->variations()->where('is_active', true)->get()->keyBy('id');
        if ($existing->count() !== count($rows)) {
            return true;
        }
        foreach ($rows as $row) {
            $variant = isset($row['id']) ? $existing->get($row['id']) : $existing->first(fn ($v) => $v->type === trim($row['type']) && $v->value === trim($row['value']));
            if (! $variant || $variant->type !== trim($row['type']) || $variant->value !== trim($row['value']) || isset($row['image_path'])) {
                return true;
            }
        }

        return false;
    }
}
