@php
    $prefix = $prefix ?? 'address';
    $address = $address ?? null;
    $postalRequired = $postalRequired ?? false;
    $addressMode = old('address_mode', 'psgc');
    $selected = [
        'region' => old('region', $address?->region ?? ''),
        'province' => old('province', $address?->province ?? ''),
        'municipality' => old('municipality', $address?->municipality ?? ''),
        'barangay' => old('barangay', $address?->barangay ?? ''),
    ];
@endphp

<div class="psgc-address-fields" data-psgc-address data-options-url="{{ route('psgc.addresses') }}" data-address-mode="{{ $addressMode }}" data-selected-region="{{ $selected['region'] }}" data-selected-province="{{ $selected['province'] }}" data-selected-municipality="{{ $selected['municipality'] }}" data-selected-barangay="{{ $selected['barangay'] }}">
    <input type="hidden" name="region" value="{{ $selected['region'] }}" data-address-value="region">
    <input type="hidden" name="province" value="{{ $selected['province'] }}" data-address-value="province">
    <input type="hidden" name="municipality" value="{{ $selected['municipality'] }}" data-address-value="municipality">
    <input type="hidden" name="barangay" value="{{ $selected['barangay'] }}" data-address-value="barangay">

    <fieldset class="address-entry-choice" aria-describedby="{{ $prefix }}-address-choice-help">
        <legend>How would you like to enter the location?</legend>
        <label for="{{ $prefix }}-mode-psgc"><input id="{{ $prefix }}-mode-psgc" type="radio" name="address_mode" value="psgc" data-address-mode-choice @checked($addressMode === 'psgc') required> Choose from location lists</label>
        <label for="{{ $prefix }}-mode-manual"><input id="{{ $prefix }}-mode-manual" type="radio" name="address_mode" value="manual" data-address-mode-choice @checked($addressMode === 'manual') required> Enter address manually</label>
    </fieldset>
    <p class="auth-help" id="{{ $prefix }}-address-choice-help">Manual entry is available immediately while lists load. Use the official area names for accurate delivery routing.</p>

    <div data-psgc-address-panel>
    <div class="auth-grid">
        <div class="auth-field">
            <label for="{{ $prefix }}-region">Region</label>
            <select id="{{ $prefix }}-region" name="region_code" data-address-select="region" required>
                <option value="">Loading regions…</option>
            </select>
            @error('region_code')<span class="auth-field-error">{{ $message }}</span>@enderror
            @error('region')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="{{ $prefix }}-province">Province</label>
            <select id="{{ $prefix }}-province" name="province_code" data-address-select="province" disabled required>
                <option value="">Choose a region first</option>
            </select>
            @error('province_code')<span class="auth-field-error">{{ $message }}</span>@enderror
            @error('province')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="{{ $prefix }}-municipality">City / Municipality</label>
            <select id="{{ $prefix }}-municipality" name="municipality_code" data-address-select="municipality" disabled required>
                <option value="">Choose a province first</option>
            </select>
            @error('municipality_code')<span class="auth-field-error">{{ $message }}</span>@enderror
            @error('municipality')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="{{ $prefix }}-barangay">Barangay</label>
            <select id="{{ $prefix }}-barangay" name="barangay_code" data-address-select="barangay" disabled required>
                <option value="">Choose a city / municipality first</option>
            </select>
            @error('barangay_code')<span class="auth-field-error">{{ $message }}</span>@enderror
            @error('barangay')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
    </div>
    </div>

    <div class="auth-grid" data-manual-address-panel hidden>
        @foreach (['region' => 'Region', 'province' => 'Province', 'municipality' => 'City / Municipality', 'barangay' => 'Barangay'] as $name => $label)
            <div class="auth-field">
                <label for="{{ $prefix }}-manual-{{ $name }}">{{ $label }}</label>
                <input id="{{ $prefix }}-manual-{{ $name }}" name="manual_{{ $name }}" value="{{ old('manual_'.$name, $selected[$name]) }}" maxlength="120" autocomplete="address-level{{ $loop->iteration + 1 }}" data-manual-address-field disabled>
                @error('manual_'.$name)<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
        @endforeach
    </div>

    <div class="auth-grid" data-address-lines>
        <div class="auth-field">
            <label for="{{ $prefix }}-house-number">House / building number</label>
            <input id="{{ $prefix }}-house-number" name="house_number" value="{{ old('house_number', $address?->house_number ?? '') }}" maxlength="100" autocomplete="address-line1">
            <small class="auth-help">Enter a house number or building name, such as 12B or Sunrise Tower.</small>
            @error('house_number')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="{{ $prefix }}-street">Street / subdivision</label>
            <input id="{{ $prefix }}-street" name="street_address" value="{{ old('street_address', $address?->street_address ?? '') }}" maxlength="255" autocomplete="address-line2">
            <small class="auth-help">Enter the street, subdivision, or purok. Leave blank if there isn’t one.</small>
            @error('street_address')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="{{ $prefix }}-postal">ZIP code{{ $postalRequired ? '' : ' (optional)' }}</label>
            <input id="{{ $prefix }}-postal" name="postal_code" value="{{ old('postal_code', $address?->postal_code ?? '') }}" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" data-address-digits-only @if ($postalRequired) required @endif>
            <small class="auth-help">Enter exactly 4 numbers, such as 1000.</small>
            @error('postal_code')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
    </div>
    <p class="auth-help" data-address-status role="status">Select your region to load locations.</p>
</div>
