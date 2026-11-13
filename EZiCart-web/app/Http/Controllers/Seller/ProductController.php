<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductComplianceEvent;
use App\Models\ProductDraft;
use App\Services\AdministrativeAuditService;
use App\Services\ParcelLoadService;
use App\Services\ProductCategoryService;
use App\Services\ProductDraftService;
use App\Services\ProductMediaService;
use App\Services\ProductVariationEditorService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(private AdministrativeAuditService $audits, private ProductMediaService $media) {}

    public function index()
    {
        $products = $this->authenticatedUser()->products()->with('variations')->latest()->paginate(25);

        return view('seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('seller.products.create');
    }

    public function store(Request $request)
    {
        $request->validate(['draft_id' => ['nullable', 'integer']]);
        $createdPaths = [];
        try {
            return DB::transaction(function () use ($request, &$createdPaths) {
                $draft = $request->filled('draft_id') ? ProductDraft::where('user_id', $this->authenticatedUser()->id)->whereKey($request->input('draft_id'))->lockForUpdate()->firstOrFail() : null;
                if ($draft?->submitted_at) {
                    return redirect()->route('seller.products.index')->with('success', 'This draft was already submitted. No duplicate product was created.');
                }
                if ($draft) {
                    $request = app(ProductDraftService::class)->hydrate($request, $draft);
                }
                $product = $this->createProduct($request);
                $createdPaths = [$product->image_path, ...$product->media()->pluck('path')->all(), ...$product->variations()->pluck('image_path')->all()];
                if ($draft) {
                    $privatePaths = array_column($draft->uploads, 'path');
                    $draft->update(['submitted_at' => now(), 'submitted_product_id' => $product->id, 'uploads' => []]);
                    DB::afterCommit(fn () => app(ProductDraftService::class)->deleteFiles($privatePaths));
                }

                return redirect()->route('seller.products.index')->with('success', 'Product submitted for compliance review.');
            });
        } catch (\Throwable $exception) {
            $this->media->deleteFiles($createdPaths);
            $this->rethrowSkuConflict($exception);
        }
    }

    private function createProduct(Request $request): Product
    {
        if ($request->has('variations_present') && ! $request->has('variations')) {
            $request->merge(['variations' => []]);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'weight_kg' => ParcelLoadService::KG_RULES,
            'length_mm' => ['nullable', 'integer', 'min:1', 'max:5000'], 'width_mm' => ['nullable', 'integer', 'min:1', 'max:5000'], 'height_mm' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048|prohibited_if:remove_cover,1',
            ...$this->media->rules(),
            'variations' => 'nullable|array',
            'variations.*.type' => 'required_with:variations|string|max:50',
            'variations.*.value' => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.stock' => 'nullable|integer|min:0',
        ]);

        $validated['category'] = app(ProductCategoryService::class)->validate($validated['category']);
        $staged = $this->media->stage($request, firstImageIsCover: ! $request->hasFile('image'));
        $imagePath = null;
        $variantPaths = [];
        try {
            [$validated, $variantPaths] = app(ProductVariationEditorService::class)->prepare($request, $validated);
            if ($request->boolean('variations_present') && ! empty($validated['variations'])) {
                $validated['stock'] = array_sum(array_column($validated['variations'], 'stock'));
            }
            $cover = $request->file('image') ?? collect($request->file('gallery_images', []))->first();
            if ($cover) {
                $imagePath = $cover->store('products', 'public');
                if (! is_string($imagePath)) {
                    throw new \RuntimeException('The product cover could not be stored.');
                }
            }

            $created = DB::transaction(function () use ($validated, $imagePath, $staged): Product {
                $product = $this->authenticatedUser()->products()->create([
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'description' => $validated['description'] ?? null,
                    'price' => $validated['price'],
                    'weight_grams' => ParcelLoadService::grams($validated['weight_kg']),
                    'length_mm' => $validated['length_mm'] ?? null, 'width_mm' => $validated['width_mm'] ?? null, 'height_mm' => $validated['height_mm'] ?? null,
                    'stock' => $validated['stock'],
                    'image_path' => $imagePath,
                    'is_archived' => false,
                ]);
                $product->refresh();
                $this->media->apply($product, $staged, []);
                ProductComplianceEvent::query()->create([
                    'product_id' => $product->id,
                    'actor_id' => $product->user_id,
                    'action' => 'submitted',
                    'previous_status' => null,
                    'new_status' => $product->compliance_status,
                    'note' => 'New product submitted for compliance review.',
                ]);

                if (! empty($validated['variations'])) {
                    foreach ($validated['variations'] as $variationData) {
                        if (! empty($variationData['type']) && ! empty($variationData['value'])) {
                            $product->variations()->create([
                                'type' => trim($variationData['type']),
                                'value' => trim($variationData['value']),
                                'price_adjustment' => $variationData['price_adjustment'] ?? 0.00,
                                'stock' => $variationData['stock'] ?? 0,
                                'sku' => $variationData['sku'] ?? null,
                                'image_path' => $variationData['image_path'] ?? null,
                            ]);
                        }
                    }
                }

                return $product;
            });
        } catch (\Throwable $exception) {
            $this->media->deleteFiles([$imagePath, ...array_column($staged, 'path'), ...$variantPaths]);
            throw $exception;
        }

        return $created;
    }

    public function edit(Product $product)
    {
        abort_if($product->user_id !== $this->authenticatedUser()->id, 403);
        $product->load(['variations', 'media']);

        return view('seller.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        if ($request->has('variations_present') && ! $request->has('variations')) {
            $request->merge(['variations' => []]);
        }
        abort_if($product->user_id !== $this->authenticatedUser()->id, 403);
        $validatedVariantIds = $request->validate(['variations.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variations', 'id')->where('product_id', $product->id)]]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'weight_kg' => ParcelLoadService::KG_RULES,
            'length_mm' => ['nullable', 'integer', 'min:1', 'max:5000'], 'width_mm' => ['nullable', 'integer', 'min:1', 'max:5000'], 'height_mm' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048|prohibited_if:remove_cover,1',
            ...$this->media->rules($product),
            'variations' => 'nullable|array',
            'variations.*.type' => 'required_with:variations|string|max:50',
            'variations.*.value' => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.stock' => 'nullable|integer|min:0',
        ]);

        $validated = array_replace_recursive($validated, $validatedVariantIds);
        $validated['category'] = app(ProductCategoryService::class)->validate($validated['category'], $product);
        $staged = $this->media->stage($request, firstImageIsCover: ! $product->image_path && ! $request->hasFile('image') && ! $request->boolean('remove_cover'));
        $uploadedImagePath = null;
        $variantPaths = [];
        try {
            [$validated, $variantPaths] = app(ProductVariationEditorService::class)->prepare($request, $validated);
            if ($request->boolean('variations_present') && ! empty($validated['variations'])) {
                $validated['stock'] = array_sum(array_column($validated['variations'], 'stock'));
            }
            $cover = $request->file('image');
            if (! $cover && ! $product->image_path && ! $request->boolean('remove_cover')) {
                $cover = collect($request->file('gallery_images', []))->first();
            }
            if ($cover) {
                $uploadedImagePath = $cover->store('products', 'public');
                if (! is_string($uploadedImagePath)) {
                    throw new \RuntimeException('The product cover could not be stored.');
                }
            }

            return DB::transaction(function () use ($request, $product, $validated, $uploadedImagePath, $staged) {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $previousValues = $product->only(['name', 'price', 'stock', 'category', 'weight_grams', 'length_mm', 'width_mm', 'height_mm']);
                $previousValues['variations'] = $product->variations()->get(['id', 'type', 'value', 'sku', 'image_path', 'stock', 'price_adjustment', 'is_active'])->map(fn ($variant) => $variant->only(['id', 'type', 'value', 'sku', 'image_path', 'stock', 'price_adjustment', 'is_active']))->all();
                $previousValues['media'] = $product->media()->get(['id', 'kind', 'path', 'sort_order'])->toArray();
                $previousValues['image_path'] = $product->image_path;
                $previousImagePath = $product->image_path;
                $imagePath = $request->boolean('remove_cover') ? null : ($uploadedImagePath ?? $product->image_path);
                $complianceFieldsChanged = $product->name !== $validated['name']
                    || (int) $product->weight_grams !== ParcelLoadService::grams($validated['weight_kg'])
                    || (int) $product->length_mm !== (int) ($validated['length_mm'] ?? 0)
                    || (int) $product->width_mm !== (int) ($validated['width_mm'] ?? 0)
                    || (int) $product->height_mm !== (int) ($validated['height_mm'] ?? 0)
                    || $product->category !== $validated['category']
                    || $product->description !== ($validated['description'] ?? null)
                    || $uploadedImagePath !== null || $staged !== []
                    || ! empty($validated['remove_media'])
                    || ($request->boolean('remove_cover') && filled($previousImagePath));
                if ($request->has('variations')) {
                    $complianceFieldsChanged = $complianceFieldsChanged || app(ProductVariationEditorService::class)->needsReview($product, $validated['variations'] ?? []);
                }

                $this->media->apply($product, $staged, $validated['remove_media'] ?? []);

                $product->update([
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'description' => $validated['description'] ?? null,
                    'price' => $validated['price'],
                    'weight_grams' => ParcelLoadService::grams($validated['weight_kg']),
                    'length_mm' => $validated['length_mm'] ?? null, 'width_mm' => $validated['width_mm'] ?? null, 'height_mm' => $validated['height_mm'] ?? null,
                    'stock' => $validated['stock'],
                    'image_path' => $imagePath,
                ]);

                if ($complianceFieldsChanged && $product->compliance_status !== 'pending_review') {
                    $previousStatus = $product->compliance_status;
                    $product->forceFill([
                        'compliance_status' => 'pending_review',
                        'compliance_note' => null,
                        'compliance_reviewed_by' => null,
                        'compliance_reviewed_at' => null,
                    ])->save();
                    ProductComplianceEvent::query()->create([
                        'product_id' => $product->id,
                        'actor_id' => $this->authenticatedUser()->id,
                        'action' => 'resubmitted',
                        'previous_status' => $previousStatus,
                        'new_status' => 'pending_review',
                        'note' => 'Seller changed product details that require another compliance review.',
                    ]);
                }

                // Omitted variants mean no change; removed variants are retired, never deleted.
                if ($request->has('variations')) {
                    DB::transaction(function () use ($product, $validated): void {
                        Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                        $existing = $product->variations()->lockForUpdate()->get();
                        $retained = [];
                        foreach ($validated['variations'] ?? [] as $data) {
                            $variant = isset($data['id']) ? $existing->firstWhere('id', $data['id']) : $existing->first(fn ($item) => $item->type === trim($data['type']) && $item->value === trim($data['value']));
                            $attributes = [
                                'type' => trim($data['type']), 'value' => trim($data['value']),
                                'price_adjustment' => $data['price_adjustment'] ?? 0,
                                'stock' => $data['stock'] ?? 0, 'is_active' => true,
                                'sku' => array_key_exists('sku', $data) ? $data['sku'] : $variant?->sku,
                                'image_path' => $data['image_path'] ?? $variant?->image_path,
                            ];
                            if ($variant) {
                                $variant->forceFill($attributes)->save();
                            } else {
                                $variant = $product->variations()->forceCreate($attributes);
                            }
                            $retained[] = $variant->id;
                        }
                        $product->variations()->whereNotIn('id', $retained)->update(['is_active' => false]);
                    });
                }

                $nextValues = $product->only(['name', 'price', 'stock', 'category', 'weight_grams', 'length_mm', 'width_mm', 'height_mm']);
                $nextValues['variations'] = $product->variations()->get(['id', 'type', 'value', 'sku', 'image_path', 'stock', 'price_adjustment', 'is_active'])->map(fn ($variant) => $variant->only(['id', 'type', 'value', 'sku', 'image_path', 'stock', 'price_adjustment', 'is_active']))->all();
                $nextValues['media'] = $product->media()->get(['id', 'kind', 'path', 'sort_order'])->toArray();
                $nextValues['image_path'] = $product->image_path;
                $this->audits->record($this->authenticatedUser(), $product, 'seller_catalog_updated', $previousValues, $nextValues, 'Seller catalog and inventory update.');
                if ($imagePath !== $previousImagePath && $previousImagePath) {
                    DB::afterCommit(fn () => $this->media->deleteFiles([$previousImagePath]));
                }

                return redirect()->route('seller.products.index')->with('success', 'Product updated successfully.');
            });
        } catch (\Throwable $exception) {
            $this->media->deleteFiles([$uploadedImagePath, ...array_column($staged, 'path'), ...$variantPaths]);
            $this->rethrowSkuConflict($exception);
        }
    }

    public function availability(Request $request, Product $product)
    {
        abort_unless($product->user_id === $this->authenticatedUser()->id, 403);
        $data = $request->validate(['is_seller_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($product, $data): void {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = ['is_seller_active' => (bool) $locked->is_seller_active];
            $locked->forceFill(['is_seller_active' => (bool) $data['is_seller_active']])->save();
            $this->audits->record($this->authenticatedUser(), $locked, 'seller_product_availability', $before, ['is_seller_active' => (bool) $locked->is_seller_active], 'Seller changed availability; administrator compliance is unchanged.');
        });

        return back()->with('success', 'Availability updated. Only listings approved by compliance can be sold.');
    }

    private function rethrowSkuConflict(\Throwable $exception): never
    {
        if ($exception instanceof UniqueConstraintViolationException && (str_contains($exception->getMessage(), 'seller_sku_unique') || str_contains($exception->getMessage(), 'product_variations.seller_id'))) {
            throw ValidationException::withMessages(['variations' => 'A SKU is already used in your store. Check the variation SKUs and try again.']);
        }
        throw $exception;
    }

    public function destroy(Product $product)
    {
        abort_if($product->user_id !== $this->authenticatedUser()->id, 403);

        $archived = DB::transaction(function () use ($product): bool {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($product->complianceEvents()->exists() || OrderItem::where('product_id', $product->id)->exists()) {
                $product->update(['is_archived' => true]);

                return true;
            }
            $paths = [$product->image_path, ...$product->media()->pluck('path')->all(), ...$product->variations()->whereNotNull('image_path')->pluck('image_path')->all()];
            $product->delete();
            DB::afterCommit(fn () => $this->media->deleteFiles($paths));

            return false;
        });

        return redirect()->route('seller.products.index')->with('success', $archived ? 'Product archived so its order and compliance history is preserved.' : 'Product removed from inventory.');
    }
}
