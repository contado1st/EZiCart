<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the seller's products.
     */
    public function index()
    {
        $products = auth()->user()->products()
            ->with(['variations', 'images'])
            ->latest()
            ->paginate(10);

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
            'name' => 'required|string|max:255',
            'category' => ['required', 'string', Rule::in([$sellerCategory])],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'stock' => 'required|integer|min:0',
            // Multiple product images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:3072',
            'primary_image_index' => 'nullable|integer',
            // Legacy single image fallback
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
            // Discount fields
            'enable_discount' => 'nullable|boolean',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0.01',
            // Voucher fields
            'enable_voucher' => 'nullable|boolean',
            'voucher_code' => 'nullable|string|max:50|unique:vouchers,code',
            'voucher_type' => 'nullable|in:percent,fixed',
            'voucher_value' => 'nullable|numeric|min:0.01',
            'voucher_min_spend' => 'nullable|numeric|min:0',
            'voucher_max_discount' => 'nullable|numeric|min:0',
            'voucher_start_date' => 'nullable|date',
            'voucher_expires_at' => 'nullable|date|after_or_equal:voucher_start_date',
            'voucher_is_active' => 'nullable|boolean',
            // Variations
            'variations' => 'nullable|array',
            'variations.*.type' => 'required_with:variations|string|max:50',
            'variations.*.value' => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.price' => 'nullable|numeric|min:0',
            'variations.*.stock' => 'nullable|integer|min:0',
            'variations.*.sku' => 'nullable|string|max:100',
            'variations.*.image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        // Validation for discount logic
        $hasDiscount = ! empty($request->input('enable_discount'));
        $discountType = null;
        $discountValue = null;

        if ($hasDiscount) {
            $discountType = $validated['discount_type'] ?? null;
            $discountValue = $validated['discount_value'] ?? null;

            if (! $discountType || ! $discountValue) {
                return back()->withInput()->with('error', 'Please specify both discount type and amount when discount is enabled.');
            }

            if ($discountType === 'percent' && (float) $discountValue > 100) {
                return back()->withInput()->with('error', 'Percentage discount cannot exceed 100%.');
            }

            if ($discountType === 'fixed' && (float) $discountValue >= (float) $validated['price']) {
                return back()->withInput()->with('error', 'Fixed discount cannot exceed or equal the product base price.');
            }
        }

        // Validation for voucher logic
        $hasVoucher = ! empty($request->input('enable_voucher'));
        if ($hasVoucher) {
            if (empty($validated['voucher_code']) || empty($validated['voucher_value']) || empty($validated['voucher_type'])) {
                return back()->withInput()->with('error', 'Please provide voucher code, type, and discount amount when voucher is enabled.');
            }
            if ($validated['voucher_type'] === 'percent' && (float) $validated['voucher_value'] > 100) {
                return back()->withInput()->with('error', 'Voucher percentage discount cannot exceed 100%.');
            }
        }

        DB::beginTransaction();

        try {
            // 1. Create base product with 'pending' status
            $product = auth()->user()->products()->create([
                'name' => $validated['name'],
                'category' => $sellerCategory,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock' => $validated['stock'],
                'status' => 'pending', // Requires admin approval
                'rejection_reason' => null,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
            ]);

            // 2. Handle multiple image uploads
            $primaryIndex = (int) ($request->input('primary_image_index', 0));
            $uploadedImages = [];

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $idx => $imgFile) {
                    $path = $imgFile->store('products', 'public');
                    $isPrimary = ($idx === $primaryIndex);

                    $imgRecord = $product->images()->create([
                        'image_path' => $path,
                        'is_primary' => $isPrimary,
                        'sort_order' => $idx,
                    ]);

                    $uploadedImages[] = $imgRecord;
                }
            } elseif ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $imgRecord = $product->images()->create([
                    'image_path' => $path,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
                $uploadedImages[] = $imgRecord;
            }

            // Sync product's main image_path column with primary image
            $primaryImg = collect($uploadedImages)->firstWhere('is_primary', true) ?? collect($uploadedImages)->first();
            if ($primaryImg) {
                $product->update(['image_path' => $primaryImg->image_path]);
            }

            // 3. Handle variations
            if (! empty($validated['variations'])) {
                foreach ($validated['variations'] as $vKey => $variationData) {
                    if (! empty($variationData['type']) && ! empty($variationData['value'])) {
                        $varImagePath = null;
                        if ($request->hasFile("variations.{$vKey}.image")) {
                            $varImagePath = $request->file("variations.{$vKey}.image")->store('products/variations', 'public');
                        }

                        $priceAdjustment = $variationData['price_adjustment'] ?? 0.00;
                        $varPrice = $variationData['price'] ?? null;

                        // If direct price is provided, compute adjustment relative to base price
                        if ($varPrice !== null && (float) $varPrice > 0 && empty($variationData['price_adjustment'])) {
                            $priceAdjustment = round((float) $varPrice - (float) $validated['price'], 2);
                        }

                        $product->variations()->create([
                            'type' => trim($variationData['type']),
                            'value' => trim($variationData['value']),
                            'price_adjustment' => $priceAdjustment,
                            'price' => $varPrice,
                            'stock' => $variationData['stock'] ?? 0,
                            'sku' => $variationData['sku'] ?? null,
                            'image_path' => $varImagePath,
                        ]);
                    }
                }
            }

            // 4. Handle optional product voucher
            if ($hasVoucher) {
                Voucher::create([
                    'seller_id' => auth()->id(),
                    'product_id' => $product->id,
                    'code' => strtoupper(trim($validated['voucher_code'])),
                    'type' => $validated['voucher_type'],
                    'value' => $validated['voucher_value'],
                    'min_spend' => $validated['voucher_min_spend'] ?? 0.00,
                    'max_discount' => $validated['voucher_max_discount'] ?? null,
                    'start_date' => $validated['voucher_start_date'] ?? null,
                    'expires_at' => $validated['voucher_expires_at'] ?? null,
                    'is_active' => $request->has('voucher_is_active') ? true : true,
                ]);
            }

            DB::commit();

            return redirect()->route('seller.products.index')
                ->with('success', 'Product submitted successfully! It is now pending admin approval before appearing in the marketplace.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to create product: '.$e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        $sellerCategory = $product->category ?? $this->getSellerCategory();
        $product->load(['variations', 'images', 'activeVoucher', 'vouchers']);

        return view('seller.products.edit', compact('product', 'sellerCategory'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        $sellerCategory = $product->category ?? $this->getSellerCategory();

        // Get existing voucher if any for validation rule exception
        $existingVoucher = $product->vouchers()->latest()->first();
        $voucherRule = 'nullable|string|max:50';
        if ($existingVoucher) {
            $voucherRule .= '|unique:vouchers,code,'.$existingVoucher->id;
        } else {
            $voucherRule .= '|unique:vouchers,code';
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', 'string', Rule::in([$sellerCategory])],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'stock' => 'required|integer|min:0',
            'is_archived' => 'nullable|boolean',
            // Multiple product images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:3072',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer',
            'primary_image_id' => 'nullable|integer',
            'primary_new_index' => 'nullable|integer',
            // Discount fields
            'enable_discount' => 'nullable|boolean',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0.01',
            // Voucher fields
            'enable_voucher' => 'nullable|boolean',
            'voucher_code' => $voucherRule,
            'voucher_type' => 'nullable|in:percent,fixed',
            'voucher_value' => 'nullable|numeric|min:0.01',
            'voucher_min_spend' => 'nullable|numeric|min:0',
            'voucher_max_discount' => 'nullable|numeric|min:0',
            'voucher_start_date' => 'nullable|date',
            'voucher_expires_at' => 'nullable|date|after_or_equal:voucher_start_date',
            'voucher_is_active' => 'nullable|boolean',
            // Variations
            'variations' => 'nullable|array',
            'variations.*.id' => 'nullable|integer',
            'variations.*.type' => 'required_with:variations|string|max:50',
            'variations.*.value' => 'required_with:variations|string|max:50',
            'variations.*.price_adjustment' => 'nullable|numeric',
            'variations.*.price' => 'nullable|numeric|min:0',
            'variations.*.stock' => 'nullable|integer|min:0',
            'variations.*.sku' => 'nullable|string|max:100',
            'variations.*.existing_image' => 'nullable|string',
            'variations.*.image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        // Validation for discount
        $hasDiscount = ! empty($request->input('enable_discount'));
        $discountType = null;
        $discountValue = null;

        if ($hasDiscount) {
            $discountType = $validated['discount_type'] ?? null;
            $discountValue = $validated['discount_value'] ?? null;

            if (! $discountType || ! $discountValue) {
                return back()->withInput()->with('error', 'Please specify both discount type and amount when discount is enabled.');
            }

            if ($discountType === 'percent' && (float) $discountValue > 100) {
                return back()->withInput()->with('error', 'Percentage discount cannot exceed 100%.');
            }

            if ($discountType === 'fixed' && (float) $discountValue >= (float) $validated['price']) {
                return back()->withInput()->with('error', 'Fixed discount cannot exceed or equal the product base price.');
            }
        }

        // Validation for voucher logic
        $hasVoucher = ! empty($request->input('enable_voucher'));
        if ($hasVoucher) {
            if (empty($validated['voucher_code']) || empty($validated['voucher_value']) || empty($validated['voucher_type'])) {
                return back()->withInput()->with('error', 'Please provide voucher code, type, and discount amount when voucher is enabled.');
            }
            if ($validated['voucher_type'] === 'percent' && (float) $validated['voucher_value'] > 100) {
                return back()->withInput()->with('error', 'Voucher percentage discount cannot exceed 100%.');
            }
        }

        DB::beginTransaction();

        try {
            // Check if status should reset to pending (if previously approved or rejected)
            // Editing essential product info requires re-approval by admin
            $newStatus = $product->status;
            if ($product->status === 'approved' || $product->status === 'rejected') {
                $newStatus = 'pending';
            }

            // 1. Update product base attributes
            $product->update([
                'name' => $validated['name'],
                'category' => $sellerCategory,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock' => $validated['stock'],
                'is_archived' => $request->has('is_archived'),
                'status' => $newStatus,
                'rejection_reason' => $newStatus === 'pending' ? null : $product->rejection_reason,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
            ]);

            // 2. Handle removal of selected existing images
            if (! empty($validated['delete_images'])) {
                $toDelete = $product->images()->whereIn('id', $validated['delete_images'])->get();
                foreach ($toDelete as $delImg) {
                    Storage::disk('public')->delete($delImg->image_path);
                    $delImg->delete();
                }
            }

            // 3. Handle newly uploaded images
            if ($request->hasFile('images')) {
                $currentMaxSort = $product->images()->max('sort_order') ?? 0;
                foreach ($request->file('images') as $i => $imgFile) {
                    $path = $imgFile->store('products', 'public');
                    $product->images()->create([
                        'image_path' => $path,
                        'is_primary' => false,
                        'sort_order' => $currentMaxSort + $i + 1,
                    ]);
                }
            }

            // 4. Update primary image designation
            if ($request->filled('primary_image_id')) {
                $chosenPrimaryId = (int) $request->input('primary_image_id');
                $product->images()->update(['is_primary' => false]);
                $product->images()->where('id', $chosenPrimaryId)->update(['is_primary' => true]);
            }

            // Ensure at least one image is marked primary if images exist
            $primaryImg = $product->images()->where('is_primary', true)->first();
            if (! $primaryImg) {
                $firstImg = $product->images()->first();
                if ($firstImg) {
                    $firstImg->update(['is_primary' => true]);
                    $primaryImg = $firstImg;
                }
            }

            // Mirror primary image path into products.image_path for backwards compatibility
            $product->update([
                'image_path' => $primaryImg ? $primaryImg->image_path : null,
            ]);

            // 5. Update Variations
            // Collect existing variations to delete obsolete image files
            $existingVarImages = $product->variations()->pluck('image_path')->filter()->all();

            $keptVarImagePaths = [];
            $product->variations()->delete();

            if (! empty($validated['variations'])) {
                foreach ($validated['variations'] as $vKey => $variationData) {
                    if (! empty($variationData['type']) && ! empty($variationData['value'])) {
                        $varImagePath = null;

                        if ($request->hasFile("variations.{$vKey}.image")) {
                            $varImagePath = $request->file("variations.{$vKey}.image")->store('products/variations', 'public');
                        } elseif (! empty($variationData['existing_image'])) {
                            $varImagePath = $variationData['existing_image'];
                            $keptVarImagePaths[] = $varImagePath;
                        }

                        $priceAdjustment = $variationData['price_adjustment'] ?? 0.00;
                        $varPrice = $variationData['price'] ?? null;

                        if ($varPrice !== null && (float) $varPrice > 0 && empty($variationData['price_adjustment'])) {
                            $priceAdjustment = round((float) $varPrice - (float) $validated['price'], 2);
                        }

                        $product->variations()->create([
                            'type' => trim($variationData['type']),
                            'value' => trim($variationData['value']),
                            'price_adjustment' => $priceAdjustment,
                            'price' => $varPrice,
                            'stock' => $variationData['stock'] ?? 0,
                            'sku' => $variationData['sku'] ?? null,
                            'image_path' => $varImagePath,
                        ]);
                    }
                }
            }

            // Delete abandoned variation images
            foreach ($existingVarImages as $oldImg) {
                if (! in_array($oldImg, $keptVarImagePaths)) {
                    Storage::disk('public')->delete($oldImg);
                }
            }

            // 6. Handle Voucher
            if ($hasVoucher) {
                $voucherData = [
                    'seller_id' => auth()->id(),
                    'product_id' => $product->id,
                    'code' => strtoupper(trim($validated['voucher_code'])),
                    'type' => $validated['voucher_type'],
                    'value' => $validated['voucher_value'],
                    'min_spend' => $validated['voucher_min_spend'] ?? 0.00,
                    'max_discount' => $validated['voucher_max_discount'] ?? null,
                    'start_date' => $validated['voucher_start_date'] ?? null,
                    'expires_at' => $validated['voucher_expires_at'] ?? null,
                    'is_active' => true,
                ];

                if ($existingVoucher) {
                    $existingVoucher->update($voucherData);
                } else {
                    Voucher::create($voucherData);
                }
            } else {
                if ($existingVoucher) {
                    $existingVoucher->update(['is_active' => false]);
                }
            }

            DB::commit();

            $statusNotice = ($newStatus === 'pending')
                ? 'Product updated successfully and submitted for admin review.'
                : 'Product updated successfully.';

            return redirect()->route('seller.products.index')->with('success', $statusNotice);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to update product: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        // Delete gallery images
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->image_path);
        }

        // Delete variation images
        foreach ($product->variations as $var) {
            if ($var->image_path) {
                Storage::disk('public')->delete($var->image_path);
            }
        }

        // Delete fallback main image if stored separately
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
