// ignore_for_file: deprecated_member_use
import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../models/psgc_location_model.dart';
import '../../services/psgc_service.dart';

class AddressFormData {
  String region;
  String province;
  String municipality;
  String barangay;
  String? houseNumber;
  String? streetAddress;
  String? postalCode;
  bool isManual;

  AddressFormData({
    this.region = '',
    this.province = '',
    this.municipality = '',
    this.barangay = '',
    this.houseNumber,
    this.streetAddress,
    this.postalCode,
    this.isManual = false,
  });

  bool get hasLocation =>
      region.isNotEmpty &&
      province.isNotEmpty &&
      municipality.isNotEmpty &&
      barangay.isNotEmpty;
}

class PsgcAddressForm extends StatefulWidget {
  final AddressFormData initialData;
  final ValueChanged<AddressFormData> onChanged;
  final bool isOptional;

  const PsgcAddressForm({
    super.key,
    required this.initialData,
    required this.onChanged,
    this.isOptional = false,
  });

  @override
  State<PsgcAddressForm> createState() => _PsgcAddressFormState();
}

class _PsgcAddressFormState extends State<PsgcAddressForm> {
  bool _isManual = false;

  // PSGC Dropdown data
  List<PsgcLocationModel> _regions = [];
  List<PsgcLocationModel> _provinces = [];
  List<PsgcLocationModel> _localities = [];
  List<PsgcLocationModel> _barangays = [];

  PsgcLocationModel? _selectedRegion;
  PsgcLocationModel? _selectedProvince;
  PsgcLocationModel? _selectedLocality;
  PsgcLocationModel? _selectedBarangay;

  bool _loadingRegions = false;
  bool _loadingProvinces = false;
  bool _loadingLocalities = false;
  bool _loadingBarangays = false;

  // Controllers for manual mode & address lines
  late final TextEditingController _manualRegionCtrl;
  late final TextEditingController _manualProvinceCtrl;
  late final TextEditingController _manualMuniCtrl;
  late final TextEditingController _manualBrgyCtrl;
  late final TextEditingController _houseCtrl;
  late final TextEditingController _streetCtrl;
  late final TextEditingController _postalCtrl;

  @override
  void initState() {
    super.initState();
    _isManual = widget.initialData.isManual;

    _manualRegionCtrl = TextEditingController(text: widget.initialData.region);
    _manualProvinceCtrl = TextEditingController(text: widget.initialData.province);
    _manualMuniCtrl = TextEditingController(text: widget.initialData.municipality);
    _manualBrgyCtrl = TextEditingController(text: widget.initialData.barangay);
    _houseCtrl = TextEditingController(text: widget.initialData.houseNumber ?? '');
    _streetCtrl = TextEditingController(text: widget.initialData.streetAddress ?? '');
    _postalCtrl = TextEditingController(text: widget.initialData.postalCode ?? '');

    _loadInitialRegions();
  }

  @override
  void dispose() {
    _manualRegionCtrl.dispose();
    _manualProvinceCtrl.dispose();
    _manualMuniCtrl.dispose();
    _manualBrgyCtrl.dispose();
    _houseCtrl.dispose();
    _streetCtrl.dispose();
    _postalCtrl.dispose();
    super.dispose();
  }

  void _notifyChange() {
    final data = AddressFormData(
      region: _isManual ? _manualRegionCtrl.text.trim() : (_selectedRegion?.name ?? ''),
      province: _isManual ? _manualProvinceCtrl.text.trim() : (_selectedProvince?.name ?? ''),
      municipality: _isManual ? _manualMuniCtrl.text.trim() : (_selectedLocality?.name ?? ''),
      barangay: _isManual ? _manualBrgyCtrl.text.trim() : (_selectedBarangay?.name ?? ''),
      houseNumber: _houseCtrl.text.trim().isNotEmpty ? _houseCtrl.text.trim() : null,
      streetAddress: _streetCtrl.text.trim().isNotEmpty ? _streetCtrl.text.trim() : null,
      postalCode: _postalCtrl.text.trim().isNotEmpty ? _postalCtrl.text.trim() : null,
      isManual: _isManual,
    );
    widget.onChanged(data);
  }

  Future<void> _loadInitialRegions() async {
    setState(() => _loadingRegions = true);
    final list = await PsgcService.fetchRegions();
    if (!mounted) return;

    setState(() {
      _regions = list;
      _loadingRegions = false;

      // Match initial region if specified by name
      if (widget.initialData.region.isNotEmpty && !_isManual) {
        final match = list.where((r) => r.name.toLowerCase() == widget.initialData.region.toLowerCase());
        if (match.isNotEmpty) {
          _selectedRegion = match.first;
          _onRegionChanged(_selectedRegion, restoreInitial: true);
        }
      }
    });
  }

  Future<void> _onRegionChanged(PsgcLocationModel? region, {bool restoreInitial = false}) async {
    setState(() {
      _selectedRegion = region;
      _selectedProvince = null;
      _selectedLocality = null;
      _selectedBarangay = null;
      _provinces = [];
      _localities = [];
      _barangays = [];
      _loadingProvinces = true;
    });
    _notifyChange();

    if (region == null) {
      setState(() => _loadingProvinces = false);
      return;
    }

    final provinces = await PsgcService.fetchProvinces(region.code);
    if (!mounted) return;

    setState(() {
      _provinces = provinces;
      _loadingProvinces = false;

      if (restoreInitial && widget.initialData.province.isNotEmpty) {
        final match = provinces.where((p) => p.name.toLowerCase() == widget.initialData.province.toLowerCase());
        if (match.isNotEmpty) {
          _selectedProvince = match.first;
          _onProvinceChanged(_selectedProvince, restoreInitial: true);
        }
      }
    });
  }

  Future<void> _onProvinceChanged(PsgcLocationModel? province, {bool restoreInitial = false}) async {
    setState(() {
      _selectedProvince = province;
      _selectedLocality = null;
      _selectedBarangay = null;
      _localities = [];
      _barangays = [];
      _loadingLocalities = true;
    });
    _notifyChange();

    if (province == null) {
      setState(() => _loadingLocalities = false);
      return;
    }

    final isNcr = province.code == 'NCR' || _selectedRegion?.code == '1300000000';
    final localities = await PsgcService.fetchLocalities(
      isNcr ? (_selectedRegion?.code ?? province.code) : province.code,
      isRegion: isNcr,
    );
    if (!mounted) return;

    setState(() {
      _localities = localities;
      _loadingLocalities = false;

      if (restoreInitial && widget.initialData.municipality.isNotEmpty) {
        final match = localities.where((l) => l.name.toLowerCase() == widget.initialData.municipality.toLowerCase());
        if (match.isNotEmpty) {
          _selectedLocality = match.first;
          _onLocalityChanged(_selectedLocality, restoreInitial: true);
        }
      }
    });
  }

  Future<void> _onLocalityChanged(PsgcLocationModel? locality, {bool restoreInitial = false}) async {
    setState(() {
      _selectedLocality = locality;
      _selectedBarangay = null;
      _barangays = [];
      _loadingBarangays = true;
    });
    _notifyChange();

    if (locality == null) {
      setState(() => _loadingBarangays = false);
      return;
    }

    final barangays = await PsgcService.fetchBarangays(locality.code);
    if (!mounted) return;

    setState(() {
      _barangays = barangays;
      _loadingBarangays = false;

      if (restoreInitial && widget.initialData.barangay.isNotEmpty) {
        final match = barangays.where((b) => b.name.toLowerCase() == widget.initialData.barangay.toLowerCase());
        if (match.isNotEmpty) {
          _selectedBarangay = match.first;
          _notifyChange();
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Mode Choice Toggle
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.grey.shade50,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppTheme.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'How would you like to enter location?',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.ink),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        setState(() => _isManual = false);
                        _notifyChange();
                      },
                      borderRadius: BorderRadius.circular(8),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
                        decoration: BoxDecoration(
                          color: !_isManual ? AppTheme.primarySoft : Colors.transparent,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: !_isManual ? AppTheme.primary : AppTheme.line,
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              !_isManual ? Icons.radio_button_checked : Icons.radio_button_off,
                              size: 16,
                              color: !_isManual ? AppTheme.primary : AppTheme.muted,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'PSGC Lists',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: !_isManual ? FontWeight.bold : FontWeight.normal,
                                color: !_isManual ? AppTheme.primary : AppTheme.ink,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        setState(() => _isManual = true);
                        _notifyChange();
                      },
                      borderRadius: BorderRadius.circular(8),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
                        decoration: BoxDecoration(
                          color: _isManual ? AppTheme.primarySoft : Colors.transparent,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: _isManual ? AppTheme.primary : AppTheme.line,
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              _isManual ? Icons.radio_button_checked : Icons.radio_button_off,
                              size: 16,
                              color: _isManual ? AppTheme.primary : AppTheme.muted,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'Enter Manually',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: _isManual ? FontWeight.bold : FontWeight.normal,
                                color: _isManual ? AppTheme.primary : AppTheme.ink,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                !_isManual
                    ? 'Official Philippine Standard Geographic Code (PSGC) API ensures accurate delivery routing.'
                    : 'Manual entry allows free-form location text.',
                style: const TextStyle(fontSize: 11, color: AppTheme.muted),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // PSGC API Cascading Dropdowns
        if (!_isManual) ...[
          // Region
          DropdownButtonFormField<PsgcLocationModel>(
            value: _selectedRegion,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'Region *',
              prefixIcon: const Icon(Icons.map_outlined, size: 20),
              suffixIcon: _loadingRegions
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: Padding(
                        padding: EdgeInsets.all(12),
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : null,
            ),
            hint: const Text('Choose Region'),
            items: _regions.map((r) {
              return DropdownMenuItem(value: r, child: Text(r.name, overflow: TextOverflow.ellipsis));
            }).toList(),
            onChanged: _onRegionChanged,
            validator: (v) {
              if (widget.isOptional) return null;
              if (v == null) return 'Please select a region';
              return null;
            },
          ),
          const SizedBox(height: 12),

          // Province
          DropdownButtonFormField<PsgcLocationModel>(
            value: _selectedProvince,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'Province *',
              prefixIcon: const Icon(Icons.location_city_outlined, size: 20),
              suffixIcon: _loadingProvinces
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: Padding(
                        padding: EdgeInsets.all(12),
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : null,
            ),
            hint: Text(_selectedRegion == null ? 'Select region first' : 'Choose Province'),
            items: _provinces.map((p) {
              return DropdownMenuItem(value: p, child: Text(p.name, overflow: TextOverflow.ellipsis));
            }).toList(),
            onChanged: _selectedRegion == null ? null : _onProvinceChanged,
            validator: (v) {
              if (widget.isOptional) return null;
              if (v == null) return 'Please select a province';
              return null;
            },
          ),
          const SizedBox(height: 12),

          // City / Municipality
          DropdownButtonFormField<PsgcLocationModel>(
            value: _selectedLocality,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'City / Municipality *',
              prefixIcon: const Icon(Icons.apartment_outlined, size: 20),
              suffixIcon: _loadingLocalities
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: Padding(
                        padding: EdgeInsets.all(12),
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : null,
            ),
            hint: Text(_selectedProvince == null ? 'Select province first' : 'Choose City / Municipality'),
            items: _localities.map((l) {
              return DropdownMenuItem(value: l, child: Text(l.name, overflow: TextOverflow.ellipsis));
            }).toList(),
            onChanged: _selectedProvince == null ? null : _onLocalityChanged,
            validator: (v) {
              if (widget.isOptional) return null;
              if (v == null) return 'Please select a city or municipality';
              return null;
            },
          ),
          const SizedBox(height: 12),

          // Barangay
          DropdownButtonFormField<PsgcLocationModel>(
            value: _selectedBarangay,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'Barangay *',
              prefixIcon: const Icon(Icons.pin_drop_outlined, size: 20),
              suffixIcon: _loadingBarangays
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: Padding(
                        padding: EdgeInsets.all(12),
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : null,
            ),
            hint: Text(_selectedLocality == null ? 'Select city/municipality first' : 'Choose Barangay'),
            items: _barangays.map((b) {
              return DropdownMenuItem(value: b, child: Text(b.name, overflow: TextOverflow.ellipsis));
            }).toList(),
            onChanged: _selectedLocality == null
                ? null
                : (b) {
                    setState(() => _selectedBarangay = b);
                    _notifyChange();
                  },
            validator: (v) {
              if (widget.isOptional) return null;
              if (v == null) return 'Please select a barangay';
              return null;
            },
          ),
        ] else ...[
          // Manual input fields
          TextFormField(
            controller: _manualRegionCtrl,
            autocorrect: false,
            enableSuggestions: false,
            decoration: const InputDecoration(labelText: 'Region *'),
            onChanged: (_) => _notifyChange(),
            validator: (v) => (!widget.isOptional && (v == null || v.trim().isEmpty)) ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _manualProvinceCtrl,
            autocorrect: false,
            enableSuggestions: false,
            decoration: const InputDecoration(labelText: 'Province *'),
            onChanged: (_) => _notifyChange(),
            validator: (v) => (!widget.isOptional && (v == null || v.trim().isEmpty)) ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _manualMuniCtrl,
            autocorrect: false,
            enableSuggestions: false,
            decoration: const InputDecoration(labelText: 'City / Municipality *'),
            onChanged: (_) => _notifyChange(),
            validator: (v) => (!widget.isOptional && (v == null || v.trim().isEmpty)) ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _manualBrgyCtrl,
            autocorrect: false,
            enableSuggestions: false,
            decoration: const InputDecoration(labelText: 'Barangay *'),
            onChanged: (_) => _notifyChange(),
            validator: (v) => (!widget.isOptional && (v == null || v.trim().isEmpty)) ? 'Required' : null,
          ),
        ],

        const SizedBox(height: 12),
        // Address Details
        Row(
          children: [
            Expanded(
              flex: 2,
              child: TextFormField(
                controller: _houseCtrl,
                autocorrect: false,
                enableSuggestions: false,
                decoration: const InputDecoration(
                  labelText: 'House / Bldg No.',
                  hintText: 'e.g. Unit 4B',
                ),
                onChanged: (_) => _notifyChange(),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              flex: 1,
              child: TextFormField(
                controller: _postalCtrl,
                keyboardType: TextInputType.number,
                autocorrect: false,
                enableSuggestions: false,
                maxLength: 4,
                decoration: const InputDecoration(
                  labelText: 'ZIP Code',
                  hintText: '4016',
                  counterText: '',
                ),
                onChanged: (_) => _notifyChange(),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _streetCtrl,
          autocorrect: false,
          enableSuggestions: false,
          decoration: const InputDecoration(
            labelText: 'Street / Subdivision / Purok',
            hintText: 'e.g. Rizal Street, Purok 3',
            prefixIcon: Icon(Icons.signpost_outlined, size: 20),
          ),
          onChanged: (_) => _notifyChange(),
        ),
      ],
    );
  }
}

