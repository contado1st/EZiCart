@extends('layouts.seller')

@section('title', 'Warehouse Addresses - EZiCart Seller')

@push('styles')
    <style>
        .address-card {
            border: 1px solid var(--slate-200);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.2s ease;
        }
        .address-card.default-address {
            border: 2px solid var(--ez-primary);
            background: #fffbfa;
        }
        .address-badge-default {
            background: var(--ez-primary);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-left: 0.5rem;
        }
    </style>
@endpush

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Warehouse & Pickup Locations</h1>
        <p class="dash-subtitle">Configure pickup locations where couriers collect customer parcels for shipping.</p>
    </div>
    <div>
        <button type="button" class="dash-btn-primary" onclick="toggleAddAddressModal()">
            + Add Warehouse Location
        </button>
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success">✅ {{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="dash-alert-success" style="border-left-color: var(--dash-danger); background-color: var(--dash-danger-bg); color: var(--dash-danger);">
        <ul style="margin: 0; padding-left: 1rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="dash-panel">
    <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin-bottom: 1rem;">
        Pickup Addresses ({{ $addresses->count() }})
    </h3>

    @forelse($addresses as $addr)
        <div class="address-card {{ $addr->is_default ? 'default-address' : '' }}">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                <div>
                    <span style="font-weight: 800; font-size: 0.95rem; color: var(--slate-800);">
                        {{ $addr->label }}
                    </span>
                    @if($addr->is_default)
                        <span class="address-badge-default">Primary Dispatch ✓</span>
                    @endif
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    @if(!$addr->is_default)
                        <form action="{{ route('seller.addresses.default', $addr->id) }}" method="POST" style="margin: 0;">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="dash-btn-sm" style="background: white; border: 1px solid var(--slate-300); color: var(--slate-700);">
                                Set as Primary
                            </button>
                        </form>
                    @endif

                    <button type="button" class="dash-btn-sm" style="background: white; border: 1px solid var(--slate-300); color: var(--slate-700);" onclick="editAddress({{ json_encode($addr) }})">
                        Edit ✏️
                    </button>

                    <form action="{{ route('seller.addresses.destroy', $addr->id) }}" method="POST" onsubmit="return confirm('Delete this pickup address?');" style="margin: 0;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dash-btn-sm" style="background: white; border: 1px solid #fee2e2; color: #dc2626;">
                            Delete ✕
                        </button>
                    </form>
                </div>
            </div>

            <div style="font-size: 0.9rem; font-weight: 700; color: var(--slate-700); margin-bottom: 0.25rem;">
                {{ $addr->recipient_name }} &bull; <span style="color: var(--slate-500); font-weight: 500;">{{ $addr->phone_number }}</span>
            </div>

            <div style="font-size: 0.85rem; color: var(--slate-600); line-height: 1.4;">
                {{ $addr->street_address }}, {{ $addr->barangay }}, {{ $addr->municipality }}, {{ $addr->province }}
            </div>
        </div>
    @empty
        <div style="text-align: center; padding: 2.5rem 1rem; color: var(--slate-400);">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📍</div>
            <p style="font-size: 0.9rem;">No warehouse addresses configured. Click "+ Add Warehouse Location" above.</p>
        </div>
    @endforelse
</div>

<!-- Add / Edit Modal -->
<div id="addressModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: white; border-radius: 12px; max-width: 550px; width: 100%; padding: 1.75rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 id="modalTitle" style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--slate-800);">Add Warehouse Location</h3>
            <button type="button" onclick="closeAddressModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--slate-400);">✕</button>
        </div>

        <form id="addressForm" action="{{ route('seller.addresses.store') }}" method="POST">
            @csrf
            <div id="formMethodContainer"></div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.25rem;">Location Label *</label>
                <input type="text" name="label" id="addr_label" class="form-control" placeholder="e.g. Main Warehouse, Dispatch Hub, Storefront" value="Main Warehouse" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.25rem;">Contact Person / Store Name *</label>
                    <input type="text" name="recipient_name" id="addr_recipient" class="form-control" value="{{ auth()->user()->business_name ?? (auth()->user()->first_name . ' ' . auth()->user()->last_name) }}" required>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.25rem;">Contact Number *</label>
                    <input type="text" name="phone_number" id="addr_phone" class="form-control" value="{{ auth()->user()->contact_no }}" required>
                </div>
            </div>

            <!-- Integrated Philippine PSGC Address API -->
            <div style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.25rem;">Province *</label>
                <select id="province" name="province" class="form-control" required>
                    <option value="">Select Province</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.25rem;">Municipality / City *</label>
                    <select id="municipality" name="municipality" class="form-control" disabled required>
                        <option value="">Select City/Municipality</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.25rem;">Barangay *</label>
                    <select id="barangay" name="barangay" class="form-control" disabled required>
                        <option value="">Select Barangay</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); display: block; margin-bottom: 0.25rem;">Street Address / Building *</label>
                <input type="text" name="street_address" id="addr_street" class="form-control" placeholder="Building name, Street, Unit #" required>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--slate-700); cursor: pointer;">
                    <input type="checkbox" name="is_default" id="addr_default" value="1">
                    <span>Set as primary dispatch pickup location</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeAddressModal()" class="dash-btn-sm" style="background: var(--slate-100); border: 1px solid var(--slate-300); color: var(--slate-700); padding: 0.5rem 1rem;">
                    Cancel
                </button>
                <button type="submit" class="dash-btn-primary" style="padding: 0.5rem 1.25rem; font-size: 0.85rem;">
                    Save Address
                </button>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset('js/ph-address.js') }}"></script>
<script>
    function toggleAddAddressModal() {
        document.getElementById('modalTitle').textContent = 'Add Warehouse Location';
        document.getElementById('addressForm').action = "{{ route('seller.addresses.store') }}";
        document.getElementById('formMethodContainer').innerHTML = '';
        document.getElementById('addr_label').value = 'Main Warehouse';
        document.getElementById('addr_street').value = '';
        document.getElementById('addr_default').checked = false;
        document.getElementById('addressModal').style.display = 'flex';
    }

    function editAddress(addr) {
        document.getElementById('modalTitle').textContent = 'Edit Warehouse Location';
        document.getElementById('addressForm').action = `/seller/addresses/${addr.id}`;
        document.getElementById('formMethodContainer').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        document.getElementById('addr_label').value = addr.label;
        document.getElementById('addr_recipient').value = addr.recipient_name;
        document.getElementById('addr_phone').value = addr.phone_number;
        document.getElementById('addr_street').value = addr.street_address;
        document.getElementById('addr_default').checked = addr.is_default;

        const provSelect = document.getElementById('province');
        if (provSelect) {
            let opt = Array.from(provSelect.options).find(o => o.value === addr.province);
            if (!opt && addr.province) {
                opt = new Option(addr.province, addr.province, true, true);
                provSelect.add(opt);
            } else if (opt) {
                opt.selected = true;
            }
        }

        const citySelect = document.getElementById('municipality');
        if (citySelect) {
            citySelect.disabled = false;
            let cOpt = new Option(addr.municipality, addr.municipality, true, true);
            citySelect.add(cOpt);
        }

        const brgySelect = document.getElementById('barangay');
        if (brgySelect) {
            brgySelect.disabled = false;
            let bOpt = new Option(addr.barangay, addr.barangay, true, true);
            brgySelect.add(bOpt);
        }

        document.getElementById('addressModal').style.display = 'flex';
    }

    function closeAddressModal() {
        document.getElementById('addressModal').style.display = 'none';
    }
</script>
@endsection

