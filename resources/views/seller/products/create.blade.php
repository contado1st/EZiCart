@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
<div class="admin-container" style="max-width: 900px; margin: 2rem auto; padding: 0 1rem;">
    <div class="admin-header-row" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="admin-page-title" style="font-size: 1.75rem; font-weight: 800; color: var(--slate-900);">Add New Product</h1>
            <p class="admin-page-desc" style="color: var(--slate-500); font-size: 0.9rem;">List a new item in your store's inventory. Submissions will be reviewed by admin before appearing in marketplace.</p>
        </div>
        <a href="{{ route('seller.products.index') }}" style="color: var(--slate-500); text-decoration: none; font-weight: 600; font-size: 0.875rem;">← Back to Inventory</a>
    </div>

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
        <form action="{{ route('seller.products.store') }}" method="POST" enctype="multipart/form-data" id="productForm">
            @csrf

            <!-- 1. PRODUCT INFORMATION -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📌</span> Product Information
                </h3>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 600;">Product Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Premium Cotton Oversized T-Shirt" required>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">Category (Assigned to Your Store)</label>
                        <input type="text" class="form-control" value="{{ $sellerCategory }}" readonly style="background-color: var(--slate-100); cursor: not-allowed; font-weight: 700; color: var(--slate-700);">
                        <input type="hidden" name="category" value="{{ $sellerCategory }}">
                        <span class="text-muted-small" style="margin-top: 0.25rem; display: block; font-size: 0.75rem; color: var(--slate-400);">
                            🔒 Products are fixed to your registered business line.
                        </span>
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--slate-100); margin: 2rem 0;">

            <!-- 2. PRODUCT IMAGES (MULTIPLE UPLOAD) -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>🖼️</span> Product Images
                </h3>
                <p style="color: var(--slate-500); font-size: 0.85rem; margin-bottom: 1rem;">
                    Upload multiple images of your item. Click an image thumbnail to set it as the primary cover photo. Supported formats: JPG, PNG, WEBP (Max 3MB each).
                </p>

                <div style="border: 2px dashed var(--slate-300); border-radius: 10px; padding: 1.5rem; text-align: center; background: var(--slate-50); cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('multiImageInput').click();">
                    <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">📁</span>
                    <strong style="color: var(--ez-primary); font-size: 0.95rem;">+ Add Product Images</strong>
                    <p style="color: var(--slate-400); font-size: 0.8rem; margin: 0.25rem 0 0;">Drag & drop or click to browse files from device</p>
                    <input type="file" name="images[]" id="multiImageInput" multiple accept="image/jpeg,image/png,image/jpg,image/webp" style="display: none;" onchange="handleImageSelection(this)">
                </div>

                <!-- Preview Grid -->
                <div id="imagePreviewContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 1rem; margin-top: 1rem;"></div>
                <input type="hidden" name="primary_image_index" id="primaryImageIndex" value="0">
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
                        <input type="number" step="0.01" min="0.01" name="price" id="basePriceInput" class="form-control" value="{{ old('price') }}" placeholder="1000.00" required oninput="calculateDiscountPreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">Initial Stock Units *</label>
                        <input type="number" min="0" name="stock" class="form-control" value="{{ old('stock', 10) }}" placeholder="10" required>
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--slate-100); margin: 2rem 0;">

            <!-- 4. PRODUCT DISCOUNT -->
            <div style="margin-bottom: 2rem; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 700; color: var(--slate-900); font-size: 1rem;">
                        <input type="checkbox" name="enable_discount" id="enableDiscountToggle" value="1" {{ old('enable_discount') ? 'checked' : '' }} onchange="toggleDiscountSection()">
                        <span>🏷️ Enable Product Discount</span>
                    </label>
                    <span style="font-size: 0.75rem; color: var(--slate-500); background: white; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--slate-200);">Optional</span>
                </div>
                <p style="color: var(--slate-500); font-size: 0.825rem; margin-bottom: 1rem;">
                    Offer a promotional markdown. The original price remains unchanged in the database while buyers see the discounted price and savings tag.
                </p>

                <div id="discountFieldsPanel" style="display: {{ old('enable_discount') ? 'block' : 'none' }}; border-top: 1px dashed var(--slate-300); padding-top: 1rem;">
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Type</label>
                            <select name="discount_type" id="discountTypeSelect" class="form-control" onchange="calculateDiscountPreview()">
                                <option value="percent" {{ old('discount_type') === 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                                <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>Fixed Amount (₱)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Value</label>
                            <input type="number" step="0.01" min="0.01" name="discount_value" id="discountValueInput" class="form-control" value="{{ old('discount_value') }}" placeholder="e.g. 20" oninput="calculateDiscountPreview()">
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
            <div style="margin-bottom: 2rem; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 700; color: var(--slate-900); font-size: 1rem;">
                        <input type="checkbox" name="enable_voucher" id="enableVoucherToggle" value="1" {{ old('enable_voucher') ? 'checked' : '' }} onchange="toggleVoucherSection()">
                        <span>🎟️ Create Product Voucher</span>
                    </label>
                    <span style="font-size: 0.75rem; color: var(--slate-500); background: white; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--slate-200);">Optional</span>
                </div>
                <p style="color: var(--slate-500); font-size: 0.825rem; margin-bottom: 1rem;">
                    Generate a promotional voucher coupon applicable exclusively to this product.
                </p>

                <div id="voucherFieldsPanel" style="display: {{ old('enable_voucher') ? 'block' : 'none' }}; border-top: 1px dashed var(--slate-300); padding-top: 1rem;">
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Voucher Code *</label>
                            <input type="text" name="voucher_code" class="form-control" style="text-transform: uppercase;" value="{{ old('voucher_code') }}" placeholder="e.g. SAVE20">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Type</label>
                            <select name="voucher_type" class="form-control">
                                <option value="percent" {{ old('voucher_type') === 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                                <option value="fixed" {{ old('voucher_type') === 'fixed' ? 'selected' : '' }}>Fixed Amount (₱)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Discount Amount *</label>
                            <input type="number" step="0.01" min="0.01" name="voucher_value" class="form-control" value="{{ old('voucher_value') }}" placeholder="e.g. 20">
                        </div>
                    </div>

                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Minimum Purchase (₱)</label>
                            <input type="number" step="0.01" min="0" name="voucher_min_spend" class="form-control" value="{{ old('voucher_min_spend', 0) }}" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Maximum Discount Cap (₱)</label>
                            <input type="number" step="0.01" min="0" name="voucher_max_discount" class="form-control" value="{{ old('voucher_max_discount') }}" placeholder="e.g. 100.00 (optional)">
                        </div>
                    </div>

                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Start Date</label>
                            <input type="date" name="voucher_start_date" class="form-control" value="{{ old('voucher_start_date') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Expiration Date</label>
                            <input type="date" name="voucher_expires_at" class="form-control" value="{{ old('voucher_expires_at') }}">
                        </div>
                    </div>

                    <div style="margin-top: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: var(--slate-700);">
                            <input type="checkbox" name="voucher_is_active" value="1" {{ old('voucher_is_active', 1) ? 'checked' : '' }}>
                            <span>Activate voucher immediately upon product listing</span>
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
                            Add custom variation types (e.g. Color, Size, Storage, RAM, Material) with independent options, option images, price adjustments, and stock.
                        </p>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--slate-500); background: white; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--slate-200);">Optional</span>
                </div>

                <!-- Variations Container -->
                <div id="variationGroupsContainer" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 1.25rem;"></div>

                <button type="button" onclick="addVariationGroup()" style="margin-top: 1rem; background: white; border: 1px dashed var(--ez-primary); color: var(--ez-primary); padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                    + Add Variation Type (e.g. Color, Size)
                </button>
            </div>

            <!-- 7. DESCRIPTION -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📝</span> Product Description
                </h3>
                <div class="form-group">
                    <textarea name="description" class="form-control" rows="5" placeholder="Detail the features, specifications, and care instructions for your product...">{{ old('description') }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1rem; font-weight: 700; border-radius: 8px; cursor: pointer;">
                Submit Product for Approval
            </button>
        </form>
    </div>
</div>

<script>
    // ----------------------------------------------------
    // Multiple Image Upload & Live Preview Management
    // ----------------------------------------------------
    let selectedImageFiles = [];

    function handleImageSelection(input) {
        if (!input.files || input.files.length === 0) return;
        
        // Append new files
        for (let i = 0; i < input.files.length; i++) {
            selectedImageFiles.push(input.files[i]);
        }
        
        renderImagePreviews();
    }

    function renderImagePreviews() {
        const container = document.getElementById('imagePreviewContainer');
        const primaryInput = document.getElementById('primaryImageIndex');
        container.innerHTML = '';

        let currentPrimary = parseInt(primaryInput.value) || 0;
        if (currentPrimary >= selectedImageFiles.length) {
            currentPrimary = 0;
            primaryInput.value = 0;
        }

        selectedImageFiles.forEach((file, index) => {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'position: relative; border-radius: 8px; overflow: hidden; border: 2px solid ' + (index === currentPrimary ? 'var(--ez-primary)' : 'var(--slate-200)') + '; background: white;';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.cssText = 'width: 100%; height: 100px; object-fit: cover; display: block; cursor: pointer;';
            img.title = 'Click to set as primary cover photo';
            img.onclick = () => setPrimaryImage(index);

            // Primary badge or set primary button
            const badge = document.createElement('div');
            badge.style.cssText = 'position: absolute; bottom: 0; left: 0; right: 0; font-size: 0.65rem; font-weight: 700; text-align: center; padding: 2px 0; cursor: pointer; ' + 
                (index === currentPrimary ? 'background: var(--ez-primary); color: white;' : 'background: rgba(0,0,0,0.6); color: white;');
            badge.textContent = (index === currentPrimary ? '★ PRIMARY' : 'Set Primary');
            badge.onclick = () => setPrimaryImage(index);

            // Remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerHTML = '&times;';
            removeBtn.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; font-size: 14px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;';
            removeBtn.onclick = (e) => {
                e.stopPropagation();
                removeImageFile(index);
            };

            wrapper.appendChild(img);
            wrapper.appendChild(badge);
            wrapper.appendChild(removeBtn);
            container.appendChild(wrapper);
        });

        // Sync files with input using DataTransfer
        syncDataTransfer();
    }

    function setPrimaryImage(index) {
        document.getElementById('primaryImageIndex').value = index;
        renderImagePreviews();
    }

    function removeImageFile(index) {
        selectedImageFiles.splice(index, 1);
        let primaryIdx = parseInt(document.getElementById('primaryImageIndex').value) || 0;
        if (primaryIdx >= selectedImageFiles.length) {
            document.getElementById('primaryImageIndex').value = Math.max(0, selectedImageFiles.length - 1);
        }
        renderImagePreviews();
    }

    function syncDataTransfer() {
        const dt = new DataTransfer();
        selectedImageFiles.forEach(f => dt.items.add(f));
        document.getElementById('multiImageInput').files = dt.files;
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

    // ----------------------------------------------------
    // Voucher Toggle
    // ----------------------------------------------------
    function toggleVoucherSection() {
        const enabled = document.getElementById('enableVoucherToggle').checked;
        document.getElementById('voucherFieldsPanel').style.display = enabled ? 'block' : 'none';
    }

    // ----------------------------------------------------
    // Dynamic Variations Builder
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
                <button type="button" onclick="removeVariationGroup(${groupId})" style="background: none; border: none; color: #dc2626; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                    ✕ Remove Type
                </button>
            </div>

            <div id="optionsContainer_${groupId}" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>

            <button type="button" onclick="addOptionToGroup(${groupId})" style="margin-top: 0.75rem; background: var(--slate-100); border: 1px solid var(--slate-300); color: var(--slate-700); padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                + Add Option
            </button>
        `;

        container.appendChild(groupCard);
        // Add one initial option
        addOptionToGroup(groupId);
    }

    function removeVariationGroup(groupId) {
        const el = document.getElementById(`varGroup_${groupId}`);
        if (el) el.remove();
    }

    function addOptionToGroup(groupId, optValue = '', optPrice = '', optStock = 10, optSku = '') {
        const optId = globalOptionCounter++;
        const container = document.getElementById(`optionsContainer_${groupId}`);
        const groupType = document.querySelector(`#varGroup_${groupId} .var-group-type`)?.value || 'Variation';

        const row = document.createElement('div');
        row.id = `optionRow_${optId}`;
        row.style.cssText = 'background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 6px; padding: 0.75rem; display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1.5fr auto; gap: 0.5rem; align-items: center;';

        row.innerHTML = `
            <input type="hidden" name="variations[${optId}][type]" class="opt-hidden-type" value="${groupType}">
            
            <div>
                <label style="font-size: 0.7rem; color: var(--slate-500); display: block;">Option Value *</label>
                <input type="text" name="variations[${optId}][value]" class="form-control" placeholder="e.g. Red, XL, 128GB" value="${optValue}" required style="font-size: 0.85rem; padding: 0.4rem 0.6rem;">
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
</script>
@endsection