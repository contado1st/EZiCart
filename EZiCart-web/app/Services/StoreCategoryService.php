<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreCategoryService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    private function lockSeller(User $seller): void
    {
        $fresh = User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
        abort_unless($fresh->role === 'seller' && $fresh->status === 'approved', 403);
    }

    public function save(User $seller, array $data, ?StoreCategory $category = null): StoreCategory
    {
        try {
            return DB::transaction(function () use ($seller, $data, $category) {
                $this->lockSeller($seller);
                if ($category) {
                    $category = StoreCategory::whereKey($category->id)->where('seller_id', $seller->id)->lockForUpdate()->firstOrFail();
                }
                $name = preg_replace('/\A\s+|\s+\z/u', '', $data['name']);
                if ($name === '' || mb_strlen($name) > 50) {
                    throw ValidationException::withMessages(['name' => 'Use a category name of 1 to 50 characters.']);
                }
                $normalized = mb_strtolower($name);
                $query = StoreCategory::where('seller_id', $seller->id);
                if ((clone $query)->where('normalized_name', $normalized)->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists()) {
                    throw ValidationException::withMessages(['name' => 'This category name already exists in your store.']);
                }
                if (! $category && (clone $query)->count() >= 200) {
                    throw ValidationException::withMessages(['name' => 'Your store can have up to 200 categories.']);
                }
                $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
                if ($parentId) {
                    $parent = (clone $query)->whereKey($parentId)->first();
                    if (! $parent || $parent->parent_id || $parent->id === $category?->id || ($category && StoreCategory::where('parent_id', $category->id)->exists())) {
                        throw ValidationException::withMessages(['parent_id' => 'Choose a top-level category in your store. Only two levels are allowed.']);
                    }
                }
                $before = $category?->only(['name', 'parent_id', 'sort_order', 'is_visible']) ?? [];
                $oldParent = $category?->parent_id;
                $category ??= new StoreCategory(['seller_id' => $seller->id]);
                $category->fill(['name' => $name, 'normalized_name' => $normalized, 'parent_id' => $parentId,
                    'is_visible' => (bool) $data['is_visible'], 'sort_order' => $data['position']])->save();
                $siblings = (clone $query)->where('parent_id', $parentId)->whereKeyNot($category->id)->orderBy('sort_order')->orderBy('id')->get()->all();
                array_splice($siblings, min(max(0, $data['position'] - 1), count($siblings)), 0, [$category]);
                foreach ($siblings as $position => $sibling) {
                    $sibling->update(['sort_order' => $position + 1]);
                }
                if ($oldParent !== $parentId) {
                    foreach ((clone $query)->where('parent_id', $oldParent)->orderBy('sort_order')->orderBy('id')->get() as $position => $sibling) {
                        $sibling->update(['sort_order' => $position + 1]);
                    }
                }
                $this->audits->record($seller, $category, 'store_category_saved', $before, $category->fresh()->only(['name', 'parent_id', 'sort_order', 'is_visible']), 'Seller updated store navigation. Platform category and publication are unchanged.');

                return $category;
            });
        } catch (UniqueConstraintViolationException $error) {
            // MySQL collation may equate names that a SQLite/text comparison does not.
            if (str_contains($error->getMessage(), 'normalized_name')) {
                throw ValidationException::withMessages(['name' => 'This category name already exists in your store.']);
            }
            throw $error;
        }
    }

    public function preview(User $seller, StoreCategory $category): array
    {
        abort_unless((int) $category->seller_id === $seller->id, 404);
        $categories = StoreCategory::where('seller_id', $seller->id)->where(fn ($q) => $q->whereKey($category->id)->orWhere('parent_id', $category->id))->orderBy('id')->get();
        $memberships = DB::table('product_store_category')->whereIn('store_category_id', $categories->modelKeys())->orderBy('store_category_id')->orderBy('product_id')->get();

        return ['categories' => $categories, 'product_count' => $memberships->pluck('product_id')->unique()->count(), 'token' => hash('sha256', json_encode([$categories->toArray(), $memberships->toArray()], JSON_THROW_ON_ERROR))];
    }

    public function delete(User $seller, StoreCategory $category, string $token): void
    {
        DB::transaction(function () use ($seller, $category, $token) {
            $this->lockSeller($seller);
            $locked = StoreCategory::whereKey($category->id)->where('seller_id', $seller->id)->lockForUpdate()->firstOrFail();
            $preview = $this->preview($seller, $locked);
            if (! hash_equals($preview['token'], $token)) {
                throw ValidationException::withMessages(['category' => 'Category contents changed. Open the deletion preview again.']);
            }
            $this->audits->record($seller, $locked, 'store_category_deleted', ['categories' => $preview['categories']->pluck('name')->all(), 'affected_products' => $preview['product_count']], [], 'Removed navigation only. Products without another membership appear in Uncategorized.');
            StoreCategory::where('parent_id', $locked->id)->delete();
            $locked->delete();
        });
    }

    public function assign(User $seller, Product $product, array $ids): void
    {
        DB::transaction(function () use ($seller, $product, $ids) {
            $this->lockSeller($seller);
            $locked = Product::whereKey($product->id)->where('user_id', $seller->id)->lockForUpdate()->firstOrFail();
            if (count($ids) > 3 || StoreCategory::where('seller_id', $seller->id)->whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['categories' => 'Choose up to three categories from your own store.']);
            }
            $before = $locked->storeCategories()->pluck('store_categories.id')->all();
            $locked->storeCategories()->sync($ids);
            $this->audits->record($seller, $locked, 'store_category_membership', ['categories' => $before], ['categories' => $ids], 'Store navigation changed; product details and platform category are retained.');
        });
    }
}
