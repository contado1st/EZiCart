<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the seller's products.
     */
    public function index()
    {
        $products = auth()->user()->products()->with('variations')->latest()->paginate(10);
        return view('seller.products.index', compact('products'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        $sellerCategory = $this->getSellerCategory();

        return view('seller.products.create', compact('sellerCategory'));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $sellerCategory = $this->getSellerCategory();

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'category'              => ['required', 'string', Rule::in([$sellerCategory])],
            'description'           => 'nullable|string',
            'price'                 => 'required|numeric|min:0.01',
            'stock'                 => 'required|integer|min:0',
            'image'                 => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variations'            => 'nullable|array',
            'variations.*.type'     => 'required_with:variations|string|max:50',
            'variations.*.value'    => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.stock'    => 'nullable|integer|min:0',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = auth()->user()->products()->create([
            'name'        => $validated['name'],
            'category'    => $sellerCategory, // Enforce registered business category
            'description' => $validated['description'] ?? null,
            'price'       => $validated['price'],
            'stock'       => $validated['stock'],
            'image_path'  => $imagePath,
            'is_active'   => true,
        ]);

        if (!empty($validated['variations'])) {
            foreach ($validated['variations'] as $variationData) {
                if (!empty($variationData['type']) && !empty($variationData['value'])) {
                    $product->variations()->create([
                        'type'             => trim($variationData['type']),
                        'value'            => trim($variationData['value']),
                        'price_adjustment' => $variationData['price_adjustment'] ?? 0.00,
                        'stock'            => $variationData['stock'] ?? 0,
                    ]);
                }
            }
        }

        return redirect()->route('seller.products.index')->with('success', 'Product created successfully under your registered category.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        $sellerCategory = $product->category ?? $this->getSellerCategory();
        $product->load('variations');

        return view('seller.products.edit', compact('product', 'sellerCategory'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        $sellerCategory = $product->category ?? $this->getSellerCategory();

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'category'              => ['required', 'string', Rule::in([$sellerCategory])],
            'description'           => 'nullable|string',
            'price'                 => 'required|numeric|min:0.01',
            'stock'                 => 'required|integer|min:0',
            'image'                 => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_archived'           => 'nullable|boolean',
            'variations'            => 'nullable|array',
            'variations.*.type'     => 'required_with:variations|string|max:50',
            'variations.*.value'    => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.stock'    => 'nullable|integer|min:0',
        ]);

        $imagePath = $product->image_path;
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'name'        => $validated['name'],
            'category'    => $sellerCategory,
            'description' => $validated['description'] ?? null,
            'price'       => $validated['price'],
            'stock'       => $validated['stock'],
            'image_path'  => $imagePath,
            'is_archived' => $request->has('is_archived'),
        ]);

        // Refresh variations
        $product->variations()->delete();
        if (!empty($validated['variations'])) {
            foreach ($validated['variations'] as $variationData) {
                if (!empty($variationData['type']) && !empty($variationData['value'])) {
                    $product->variations()->create([
                        'type'             => trim($variationData['type']),
                        'value'            => trim($variationData['value']),
                        'price_adjustment' => $variationData['price_adjustment'] ?? 0.00,
                        'stock'            => $variationData['stock'] ?? 0,
                    ]);
                }
            }
        }

        return redirect()->route('seller.products.index')->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()->route('seller.products.index')->with('success', 'Product removed from inventory.');
    }

    /**
     * Helper method to dynamically extract the seller's registered line of business / category.
     */
    private function getSellerCategory(): string
    {
        $user = auth()->user();

        return $user->line_of_business 
            ?? $user->business_category 
            ?? $user->category 
            ?? ($user->sellerProfile->line_of_business ?? 'General');
    }
}