<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ProductCategoryService
{
    public function label(string $category): string
    {
        return config('web-commerce.category_aliases')[$category] ?? $category;
    }

    public function options(): array
    {
        return config('web-commerce.categories');
    }

    public function browseOptions(iterable $existing): array
    {
        return array_values(array_unique([...$this->options(), ...collect($existing)->map(fn ($name) => $this->label($name))->filter()->all()]));
    }

    public function filter(Builder $query, string $category): void
    {
        $label = $this->label($category);
        $aliases = array_keys(array_filter(config('web-commerce.category_aliases'), fn ($value) => $value === $label));
        $query->whereIn('category', array_unique([$label, $category, ...$aliases]));
    }

    public function validate(string $category, ?Product $product = null): string
    {
        $label = $this->label($category);
        if (! in_array($label, $this->options(), true) && $category !== $product?->category) {
            throw ValidationException::withMessages(['category' => 'Choose a supported product category.']);
        }

        // Preserve legacy values submitted by older forms; new forms use canonical options.
        return $category;
    }
}
