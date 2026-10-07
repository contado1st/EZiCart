@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
<div class="admin-container" style="max-width: 900px; margin: 2rem auto; padding: 0 1rem;">
    <div class="admin-header-row" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="admin-page-title" style="font-size: 1.75rem; font-weight: 800; color: var(--slate-900);">Edit Product</h1>
            <p class="admin-page-desc" style="color: var(--slate-500); font-size: 0.9rem;">Modify product details, multiple images, discount, voucher, and variations.</p>
        </div>
        <a href="{{ route('seller.products.index') }}" style="color: var(--slate-500); text-decoration: none; font-weight: 600; font-size: 0.875rem;">← Back to Inventory</a>
    </div>

    @if($product->status === 'approved')
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 0.875rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.875rem;">
            ℹ️ <strong>Note:</strong> Modifying key details (pricing, images, variations, discounts, vouchers) will submit this product for admin re-approval.
        </div>
    @elseif($product->status === 'rejected')
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 0.875rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.875rem;">
            ⚠️ <strong>Rejection Reason from Admin:</strong> {{ $product->rejection_reason }}
            <div style="margin-top: 0.25rem; font-size: 0.8rem; color: #b91c1c;">Editing and updating this product will resubmit it for review.</div>
        </div>
    @endif

    @if(session('error'))
        <div style="background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            ❌ {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div style="background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <strong style="display: block; margin-bottom: 0.25rem;">Please correct the following errors:</strong>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="admin-table-wrapper" style="padding: 2rem; background: white; border-radius: 12px; border: 1px solid var(--slate-200); box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <form action="{{ route('seller.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" id="productEditForm">
            @csrf
            @method('PUT')

            <!-- 1. PRODUCT INFORMATION -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📌</span> Product Information
                </h3>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 600;">Product Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">Category (Assigned to Your Store)</label>
                        <input type="text" class="form-control" value="{{ $sellerCategory }}" readonly style="background-color: var(--slate-100); cursor: not-allowed; font-weight: 700; color: var(--slate-700);">
                        <input type="hidden" name="category" value="{{ $sellerCategory }}">
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--slate-100); margin: 2rem 0;">

            <!-- 2. PRODUCT IMAGES (MULTIPLE UPLOAD & MANAGEMENT) -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>🖼️</span> Product Images
                </h3>
                <p style="color: var(--slate-500); font-size: 0.85rem; margin-bottom: 1rem;">
                    Manage existing photos, set your primary cover photo, or upload additional photos.
                </p>

                <!-- Existing Images Management -->
                @if($product->images->count() > 0)
                    <div style="margin-bottom: 1.25rem;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.5rem;">Current Gallery Photos:</span>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 1rem;">
                            @foreach($product->images as $img)
                                <div id="existingImgCard_{{ $img->id }}" style="position: relative; border-radius: 8px; overflow: hidden; border: 2px solid {{ $img->is_primary ? 'var(--ez-primary)' : 'var(--slate-200)' }}; background: white; padding-bottom: 2rem;">
                                    <img src="{{ asset('storage/' . $img->image_path) }}" alt="{{ $product->name }}" style="width: 100%; height: 100px; object-fit: cover;">
                                    
                                    <!-- Primary Selector Radio -->
                                    <label style="position: absolute; bottom: 4px; left: 6px; right: 6px; display: flex; align-items: center; justify-content: center; gap: 4px; font-size: 0.7rem; font-weight: 700; cursor: pointer; color: {{ $img->is_primary ? 'var(--ez-primary)' : 'var(--slate-600)' }};">
                                        <input type="radio" name="primary_image_id" value="{{ $img->id }}" {{ $img->is_primary ? 'checked' : '' }}>
                                        <span>Cover</span>
                                    </label>

                                    <!-- Delete Checkbox -->
                                    <label style="position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.7); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center; gap: 3px;" title="Check to delete this photo upon saving">
                                        <input type="checkbox" name="delete_images[]" value="{{ $img->id }}" onchange="toggleImageMarkDelete({{ $img->id }}, this)">
                                        <span>Delete</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($product->image_path)
                    <div style="margin-bottom: 1.25rem;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.5rem;">Current Photo:</span>
                        <img src="{{ asset('storage/' . $product->image_path) }}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid var(--slate-200);">
                    </div>
                @endif

                <!-- Upload New Images -->
                <div style="border: 2px dashed var(--slate-300); border-radius: 10px; padding: 1.25rem; text-align: center; background: var(--slate-50); cursor: pointer; margin-top: 0.75rem;" onclick="document.getElementById('editMultiImageInput').click();">
                    <span style="font-size: 1.75rem; display: block; margin-bottom: 0.25rem;">📁</span>
                    <strong style="color: var(--ez-primary); font-size: 0.9rem;">+ Add New Product Images</strong>
                    <p style="color: var(--slate-400); font-size: 0.75rem; margin: 0.25rem 0 0;">Upload new images to include in this product's gallery</p>
                    <input type="file" name="images[]" id="editMultiImageInput" multiple accept="image/jpeg,image/png,image/jpg,image/webp" style="display: none;" onchange="handleEditImageSelection(this)">
                </div>

                <!-- New Uploads Preview -->
                <div id="newImagePreviewContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 1rem; margin-top: 1rem;"></div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--slate-100); margin: 2rem 0;">

            <!-- 3. PRICING & STOCK -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>💰</span> Pricing & Stock
                </h3>

                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">Original Base Price (₱) *</label>
                        <input type="number" step="0.01" min="0.01" name="price" id="basePriceInput" class="form-control" value="{{ old('price', $product->price) }}" required oninput="calculateDiscountPreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">Current Stock Units *</label>
                        <input type="number" min="0" name="stock" class="form-control" value="{{ old('stock', $product->stock) }}" required>
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--slate-100); margin: 2rem 0;">

            <!-- 4. PRODUCT DISCOUNT -->
            @php
                $hasDiscountInit = old('enable_discount', $product->has_discount);
            @endphp
            <div style="margin-bottom: 2rem; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 700; color: var(--slate-900); font-size: 1rem;">
                        <input type="checkbox" name="enable_discount" id="enableDiscountToggle" value="1" {{ $hasDiscountInit ? 'checked' : '' }} onchange="toggleDiscountSection()">
                        <span>🏷️ Enable Product Discount</span>
                    </label>
                    <span style="font-size: 0.75rem; color: var(--slate-500); background: white; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--slate-200);">Optional</span>
                </div>
                <p style="color: var(--slate-500); font-size: 0.825rem; margin-bottom: 1rem;">
                    The base price remains unchanged in the database while buyers receive the discount markdown.
                </p>

                <div id="discountFieldsPanel" style="display: {{ $hasDiscountInit ? 'block' : 'none' }}; border-top: 1px dashed var(--slate-300); padding-top: 1rem;">
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Type</label>
                            <select name="discount_type" id="discountTypeSelect" class="form-control" onchange="calculateDiscountPreview()">
                                <option value="percent" {{ old('discount_type', $product->discount_type) === 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                                <option value="fixed" {{ old('discount_type', $product->discount_type) === 'fixed' ? 'selected' : '' }}>Fixed Amount (₱)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Value</label>
                            <input type="number" step="0.01" min="0.01" name="discount_value" id="discountValueInput" class="form-control" value="{{ old('discount_value', $product->discount_value) }}" placeholder="e.g. 20" oninput="calculateDiscountPreview()">
                        </div>
                    </div>

                    <!-- Live Discount Preview -->
                    <div id="discountPreviewCard" style="background: white; border: 1px solid var(--slate-200); border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <span style="font-size: 0.75rem; color: var(--slate-400); display: block;">Buyer Marketplace Price:</span>
                            <span id="previewFinalPrice" style="font-size: 1.25rem; font-weight: 800; color: var(--ez-primary);">₱0.00</span>
                            <span id="previewOriginalPrice" style="text-decoration: line-through; color: var(--slate-400); font-size: 0.85rem; margin-left: 0.5rem;">₱0.00</span>
                        </div>
                        <span id="previewDiscountBadge" style="background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 0.8rem; padding: 4px 8px; border-radius: 4px;">
                            0% OFF
                        </span>
                    </div>
                </div>
            </div>

            <!-- 5. PRODUCT VOUCHER -->
            @php
                $existingVoucher = $product->activeVoucher ?? $product->vouchers()->latest()->first();
                $hasVoucherInit = old('enable_voucher', $existingVoucher && $existingVoucher->is_active);
            @endphp
            <div style="margin-bottom: 2rem; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 700; color: var(--slate-900); font-size: 1rem;">
                        <input type="checkbox" name="enable_voucher" id="enableVoucherToggle" value="1" {{ $hasVoucherInit ? 'checked' : '' }} onchange="toggleVoucherSection()">
                        <span>🎟️ Create / Manage Product Voucher</span>
                    </label>
                    <span style="font-size: 0.75rem; color: var(--slate-500); background: white; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--slate-200);">Optional</span>
                </div>
                <p style="color: var(--slate-500); font-size: 0.825rem; margin-bottom: 1rem;">
                    Attach a promotional discount coupon specific to this product.
                </p>

                <div id="voucherFieldsPanel" style="display: {{ $hasVoucherInit ? 'block' : 'none' }}; border-top: 1px dashed var(--slate-300); padding-top: 1rem;">
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Voucher Code *</label>
                            <input type="text" name="voucher_code" class="form-control" style="text-transform: uppercase;" value="{{ old('voucher_code', $existingVoucher->code ?? '') }}" placeholder="e.g. SAVE20">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Type</label>
                            <select name="voucher_type" class="form-control">
                                <option value="percent" {{ old('voucher_type', $existingVoucher->type ?? '') === 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                                <option value="fixed" {{ old('voucher_type', $existingVoucher->type ?? '') === 'fixed' ? 'selected' : '' }}>Fixed Amount (₱)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Amount *</label>
                            <input type="number" step="0.01" min="0.01" name="voucher_value" class="form-control" value="{{ old('voucher_value', $existingVoucher->value ?? '') }}" placeholder="e.g. 20">
                        </div>
                    </div>

                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Minimum Purchase (₱)</label>
                            <input type="number" step="0.01" min="0" name="voucher_min_spend" class="form-control" value="{{ old('voucher_min_spend', $existingVoucher->min_spend ?? 0) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Maximum Discount Cap (₱)</label>
                            <input type="number" step="0.01" min="0" name="voucher_max_discount" class="form-control" value="{{ old('voucher_max_discount', $existingVoucher->max_discount ?? '') }}" placeholder="Optional">
                        </div>
                    </div>

                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Start Date</label>
                            <input type="date" name="voucher_start_date" class="form-control" value="{{ old('voucher_start_date', $existingVoucher && $existingVoucher->start_date ? $existingVoucher->start_date->format('Y-m-d') : '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Expiration Date</label>
                            <input type="date" name="voucher_expires_at" class="form-control" value="{{ old('voucher_expires_at', $existingVoucher && $existingVoucher->expires_at ? $existingVoucher->expires_at->format('Y-m-d') : '') }}">
                        </div>
                    </div>

                    <div style="margin-top: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: var(--slate-700);">
                            <input type="checkbox" name="voucher_is_active" value="1" {{ old('voucher_is_active', $existingVoucher->is_active ?? 1) ? 'checked' : '' }}>
                            <span>Voucher is currently active</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 6. PRODUCT VARIATIONS -->
            <div style="margin-bottom: 2rem; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                            <span>🧩</span> Product Variations
                        </h3>
                        <p style="color: var(--slate-500); font-size: 0.825rem; margin: 0.25rem 0 0;">
                            Configure variation types (Color, Size, etc.) with option images, prices, and inventory.
                        </p>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--slate-500); background: white; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--slate-200);">Optional</span>
                </div>

                <div id="variationGroupsContainer" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 1.25rem;"></div>

                <button type="button" onclick="addVariationGroup()" style="margin-top: 1rem; background: white; border: 1px dashed var(--ez-primary); color: var(--ez-primary); padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                    + Add Variation Type (e.g. Color, Size)
                </button>
            </div>

            <!-- 7. DESCRIPTION & ARCHIVE -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📝</span> Product Description
                </h3>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <textarea name="description" class="form-control" rows="5">{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: var(--slate-800);">
                        <input type="checkbox" name="is_archived" value="1" {{ $product->is_archived ? 'checked' : '' }}>
                        <span>Archive Product (hide from active customer catalog)</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1rem; font-weight: 700; border-radius: 8px; cursor: pointer;">
                Update Product
            </button>
        </form>
    </div>
</div>

<script>
    // ----------------------------------------------------
    // Existing Images Management
    // ----------------------------------------------------
    function toggleImageMarkDelete(imgId, checkbox) {
        const card = document.getElementById(`existingImgCard_${imgId}`);
        if (checkbox.checked) {
            card.style.opacity = '0.4';
            card.style.filter = 'grayscale(100%)';
        } else {
            card.style.opacity = '1';
            card.style.filter = 'none';
        }
    }

    // New Image Uploads in Edit
    let newSelectedImageFiles = [];

    function handleEditImageSelection(input) {
        if (!input.files || input.files.length === 0) return;
        for (let i = 0; i < input.files.length; i++) {
            newSelectedImageFiles.push(input.files[i]);
        }
        renderNewImagePreviews();
    }

    function renderNewImagePreviews() {
        const container = document.getElementById('newImagePreviewContainer');
        container.innerHTML = '';

        newSelectedImageFiles.forEach((file, index) => {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'position: relative; border-radius: 8px; overflow: hidden; border: 2px dashed var(--ez-primary); background: white;';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.cssText = 'width: 100%; height: 100px; object-fit: cover; display: block;';

            const badge = document.createElement('div');
            badge.style.cssText = 'position: absolute; bottom: 0; left: 0; right: 0; font-size: 0.65rem; font-weight: 700; text-align: center; padding: 2px 0; background: var(--slate-800); color: white;';
            badge.textContent = 'NEW';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerHTML = '&times;';
            removeBtn.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; font-size: 14px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;';
            removeBtn.onclick = (e) => {
                e.stopPropagation();
                newSelectedImageFiles.splice(index, 1);
                renderNewImagePreviews();
            };

            wrapper.appendChild(img);
            wrapper.appendChild(badge);
            wrapper.appendChild(removeBtn);
            container.appendChild(wrapper);
        });

        const dt = new DataTransfer();
        newSelectedImageFiles.forEach(f => dt.items.add(f));
        document.getElementById('editMultiImageInput').files = dt.files;
    }

    // ----------------------------------------------------
    // Discount Calculation & Toggle
    // ----------------------------------------------------
    function toggleDiscountSection() {
        const enabled = document.getElementById('enableDiscountToggle').checked;
        document.getElementById('discountFieldsPanel').style.display = enabled ? 'block' : 'none';
        if (enabled) {
            calculateDiscountPreview();
        }
    }

    function calculateDiscountPreview() {
        const basePrice = parseFloat(document.getElementById('basePriceInput').value) || 0;
        const discountType = document.getElementById('discountTypeSelect').value;
        const discountVal = parseFloat(document.getElementById('discountValueInput').value) || 0;

        let finalPrice = basePrice;
        let badgeText = '0% OFF';

        if (discountVal > 0 && basePrice > 0) {
            if (discountType === 'percent') {
                const deduction = basePrice * (discountVal / 100);
                finalPrice = Math.max(0, basePrice - deduction);
                badgeText = discountVal + '% OFF';
            } else {
                finalPrice = Math.max(0, basePrice - discountVal);
                const pct = Math.round((discountVal / basePrice) * 100);
                badgeText = '₱' + discountVal.toFixed(2) + ' OFF (' + pct + '%)';
            }
        }

        document.getElementById('previewFinalPrice').textContent = '₱' + finalPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('previewOriginalPrice').textContent = '₱' + basePrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('previewDiscountBadge').textContent = badgeText;
    }

    function toggleVoucherSection() {
        const enabled = document.getElementById('enableVoucherToggle').checked;
        document.getElementById('voucherFieldsPanel').style.display = enabled ? 'block' : 'none';
    }

    // ----------------------------------------------------
    // Variations Builder
    // ----------------------------------------------------
    let variationGroupCount = 0;
    let globalOptionCounter = 0;

    function addVariationGroup(groupName = '') {
        const groupId = variationGroupCount++;
        const container = document.getElementById('variationGroupsContainer');

        const groupCard = document.createElement('div');
        groupCard.id = `varGroup_${groupId}`;
        groupCard.style.cssText = 'background: white; border: 1px solid var(--slate-200); border-radius: 8px; padding: 1.25rem;';

        groupCard.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div style="flex: 1; margin-right: 1rem;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--slate-600); display: block; margin-bottom: 0.25rem;">Variation Name *</label>
                    <input type="text" class="form-control var-group-type" placeholder="e.g. Color, Size, Storage, RAM" value="${groupName}" style="font-weight: 700; max-width: 300px;" oninput="syncOptionTypeNames(${groupId})">
                </div>
                <button type="button" onclick="document.getElementById('varGroup_${groupId}').remove()" style="background: none; border: none; color: #dc2626; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                    ✕ Remove Type
                </button>
            </div>

            <div id="optionsContainer_${groupId}" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>

            <button type="button" onclick="addOptionToGroup(${groupId})" style="margin-top: 0.75rem; background: var(--slate-100); border: 1px solid var(--slate-300); color: var(--slate-700); padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                + Add Option
            </button>
        `;

        container.appendChild(groupCard);
        return groupId;
    }

    function addOptionToGroup(groupId, optValue = '', optPrice = '', optStock = 10, optSku = '', optExistingImg = '') {
        const optId = globalOptionCounter++;
        const container = document.getElementById(`optionsContainer_${groupId}`);
        const groupType = document.querySelector(`#varGroup_${groupId} .var-group-type`)?.value || 'Variation';

        const row = document.createElement('div');
        row.id = `optionRow_${optId}`;
        row.style.cssText = 'background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 6px; padding: 0.75rem; display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1.5fr auto; gap: 0.5rem; align-items: center;';

        const existingImgThumb = optExistingImg ? `
            <img src="{{ asset('storage') }}/${optExistingImg}" style="width: 28px; height: 28px; object-fit: cover; border-radius: 4px;">
            <input type="hidden" name="variations[${optId}][existing_image]" value="${optExistingImg}">
        ` : '';

        row.innerHTML = `
            <input type="hidden" name="variations[${optId}][type]" class="opt-hidden-type" value="${groupType}">
            
            <div>
                <label style="font-size: 0.7rem; color: var(--slate-500); display: block;">Option Value *</label>
                <input type="text" name="variations[${optId}][value]" class="form-control" placeholder="e.g. Red, XL" value="${optValue}" required style="font-size: 0.85rem; padding: 0.4rem 0.6rem;">
            </div>

            <div>
                <label style="font-size: 0.7rem; color: var(--slate-500); display: block;" title="Product discount will apply to this price">Price / Base (₱)</label>
                <input type="number" step="0.01" name="variations[${optId}][price]" class="form-control" placeholder="e.g. 500.00" value="${optPrice}" style="font-size: 0.85rem; padding: 0.4rem 0.6rem;">
            </div>

            <div>
                <label style="font-size: 0.7rem; color: var(--slate-500); display: block;">Stock</label>
                <input type="number" min="0" name="variations[${optId}][stock]" class="form-control" value="${optStock}" style="font-size: 0.85rem; padding: 0.4rem 0.6rem;">
            </div>

            <div>
                <label style="font-size: 0.7rem; color: var(--slate-500); display: block;">SKU</label>
                <input type="text" name="variations[${optId}][sku]" class="form-control" placeholder="Optional" value="${optSku}" style="font-size: 0.85rem; padding: 0.4rem 0.6rem;">
            </div>

            <div>
                <label style="font-size: 0.7rem; color: var(--slate-500); display: block;">Option Image</label>
                <div style="display: flex; align-items: center; gap: 0.35rem;">
                    ${existingImgThumb}
                    <input type="file" name="variations[${optId}][image]" accept="image/*" class="form-control" style="font-size: 0.75rem; padding: 0.25rem;" onchange="previewVarImage(this, ${optId})">
                    <img id="varImgPreview_${optId}" src="" style="width: 28px; height: 28px; object-fit: cover; border-radius: 4px; display: none;">
                </div>
            </div>

            <button type="button" onclick="document.getElementById('optionRow_${optId}').remove()" style="background: none; border: none; color: #dc2626; font-size: 1.1rem; cursor: pointer; padding: 0.2rem 0.4rem;" title="Remove this option">
                ✕
            </button>
        `;

        container.appendChild(row);
    }

    function syncOptionTypeNames(groupId) {
        const val = document.querySelector(`#varGroup_${groupId} .var-group-type`)?.value || 'Variation';
        const rows = document.querySelectorAll(`#optionsContainer_${groupId} .opt-hidden-type`);
        rows.forEach(r => r.value = val);
    }

    function previewVarImage(input, optId) {
        const preview = document.getElementById(`varImgPreview_${optId}`);
        if (input.files && input.files[0]) {
            preview.src = URL.createObjectURL(input.files[0]);
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    // Populate existing variations
    document.addEventListener('DOMContentLoaded', function () {
        calculateDiscountPreview();

        @php
            $groupedVariations = $product->variations->groupBy('type');
        @endphp

        @if($groupedVariations->count() > 0)
            @foreach($groupedVariations as $type => $vars)
                {
                    const currentGroupId = addVariationGroup(@json($type));
                    @foreach($vars as $v)
                        addOptionToGroup(
                            currentGroupId, 
                            @json($v->value), 
                            @json($v->price !== null && $v->price > 0 ? $v->price : ($v->price_adjustment != 0 ? $product->price + $v->price_adjustment : '')), 
                            @json($v->stock), 
                            @json($v->sku ?? ''), 
                            @json($v->image_path ?? '')
                        );
                    @endforeach
                }
            @endforeach
        @endif
    });
</script>
@endsection