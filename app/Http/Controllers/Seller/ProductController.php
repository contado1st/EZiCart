<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductComplianceEvent;
use App\Services\AdministrativeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function index()
    {
        $products = $this->authenticatedUser()->products()->with('variations')->latest()->paginate(10);

        return view('seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('seller.products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variations' => 'nullable|array',
            'variations.*.type' => 'required_with:variations|string|max:50',
            'variations.*.value' => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.stock' => 'nullable|integer|min:0',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = $this->authenticatedUser()->products()->create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image_path' => $imagePath,
            'is_archived' => false,
        ]);
        $product->refresh();
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
                    ]);
                }
            }
        }

        return redirect()->route('seller.products.index')->with('success', 'Product and variations created successfully.');
    }

    public function edit(Product $product)
    {
        abort_if($product->user_id !== $this->authenticatedUser()->id, 403);
        $product->load('variations');

        return view('seller.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        abort_if($product->user_id !== $this->authenticatedUser()->id, 403);
        $validatedVariantIds = $request->validate(['variations.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variations', 'id')->where('product_id', $product->id)]]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variations' => 'nullable|array',
            'variations.*.type' => 'required_with:variations|string|max:50',
            'variations.*.value' => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.stock' => 'nullable|integer|min:0',
        ]);

        $validated = array_replace_recursive($validated, $validatedVariantIds);
        $uploadedImagePath = $request->hasFile('image') ? $request->file('image')->store('products', 'public') : null;
        try {
            return DB::transaction(function () use ($request, $product, $validated, $uploadedImagePath) {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $previousValues = $product->only(['name', 'price', 'stock', 'category']);
                $previousValues['variations'] = $product->variations()->get(['id', 'type', 'value', 'stock', 'price_adjustment', 'is_active'])->toArray();
                $previousImagePath = $product->image_path;
                $imagePath = $uploadedImagePath ?? $product->image_path;
                $complianceFieldsChanged = $product->name !== $validated['name']
                    || $product->category !== $validated['category']
                    || $product->description !== ($validated['description'] ?? null)
                    || $request->hasFile('image');

                $product->update([
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'description' => $validated['description'] ?? null,
                    'price' => $validated['price'],
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

                $nextValues = $product->only(['name', 'price', 'stock', 'category']);
                $nextValues['variations'] = $product->variations()->get(['id', 'type', 'value', 'stock', 'price_adjustment', 'is_active'])->toArray();
                $this->audits->record($this->authenticatedUser(), $product, 'seller_catalog_updated', $previousValues, $nextValues, 'Seller catalog and inventory update.');
                if ($uploadedImagePath && $previousImagePath) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($previousImagePath));
                }

                return redirect()->route('seller.products.index')->with('success', 'Product updated successfully.');
            });
        } catch (\Throwable $exception) {
            if ($uploadedImagePath) {
                Storage::disk('public')->delete($uploadedImagePath);
            }
            throw $exception;
        }
    }

    public function destroy(Product $product)
    {
        abort_if($product->user_id !== $this->authenticatedUser()->id, 403);

        if ($product->complianceEvents()->exists()) {
            $product->update(['is_archived' => true]);

            return redirect()->route('seller.products.index')->with('success', 'Product archived so its compliance review history is preserved.');
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()->route('seller.products.index')->with('success', 'Product removed from inventory.');
    }
}
