import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/address_model.dart';
import '../../services/api_service.dart';
import '../common/psgc_address_form.dart';

class AddressesPage extends StatefulWidget {
  final bool selectMode;

  const AddressesPage({super.key, this.selectMode = false});

  @override
  State<AddressesPage> createState() => _AddressesPageState();
}

class _AddressesPageState extends State<AddressesPage> {
  List<AddressModel> _addresses = [];
  bool _isLoading = true;

  static final RegExp _phMobileRegex = RegExp(r'^(09\d{9}|\+639\d{9})$');
  static final RegExp _postalCodeRegex = RegExp(r'^\d{4}$');

  @override
  void initState() {
    super.initState();
    _fetchAddresses();
  }

  Future<void> _fetchAddresses() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.addressesEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((a) => AddressModel.fromJson(a as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _addresses = list;
              _isLoading = false;
            });
          }
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  void _showAddEditSheet({AddressModel? address}) {
    if (address == null && _addresses.length >= 10) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Address book limit reached (maximum 10 addresses).'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    final labelCtrl = TextEditingController(text: address?.label ?? 'Home');
    final nameCtrl = TextEditingController(text: address?.recipientName ?? '');
    final contactCtrl = TextEditingController(text: address?.recipientContact ?? '');
    bool isDefault = address?.isDefault ?? (_addresses.isEmpty);

    AddressFormData formData = AddressFormData(
      region: address?.region ?? '',
      province: address?.province ?? '',
      municipality: address?.municipality ?? '',
      barangay: address?.barangay ?? '',
      houseNumber: address?.houseNumber,
      streetAddress: address?.streetAddress,
      postalCode: address?.postalCode,
      isManual: false,
    );

    bool isSaving = false;
    String? sheetError;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            top: 20,
            left: 20,
            right: 20,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      address == null ? 'Add Delivery Address' : 'Edit Delivery Address',
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: isSaving ? null : () => Navigator.pop(ctx),
                    ),
                  ],
                ),
                if (sheetError != null) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.shade200),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.error_outline, color: Colors.red.shade700, size: 18),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            sheetError!,
                            style: TextStyle(fontSize: 12, color: Colors.red.shade900),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 12),

                // Label & Recipient Name
                Row(
                  children: [
                    Expanded(
                      flex: 1,
                      child: TextField(
                        controller: labelCtrl,
                        maxLength: 50,
                        decoration: const InputDecoration(
                          labelText: 'Label',
                          hintText: 'e.g. Home, Office',
                          counterText: '',
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 2,
                      child: TextField(
                        controller: nameCtrl,
                        maxLength: 255,
                        decoration: const InputDecoration(
                          labelText: 'Recipient Name *',
                          hintText: 'Full name',
                          counterText: '',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Recipient Mobile Number
                TextField(
                  controller: contactCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Recipient Mobile Number *',
                    hintText: '09XXXXXXXXX or +639XXXXXXXXX',
                    prefixIcon: Icon(Icons.phone_android_outlined, size: 20),
                  ),
                ),
                const SizedBox(height: 16),

                // PSGC Address Form (Region, Province, Municipality, Barangay, Street, House, Zip)
                PsgcAddressForm(
                  initialData: formData,
                  onChanged: (data) => formData = data,
                ),
                const SizedBox(height: 12),

                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: isDefault,
                  onChanged: isSaving
                      ? null
                      : (v) => setSheetState(() => isDefault = v ?? false),
                  title: const Text('Set as default delivery address', style: TextStyle(fontSize: 13)),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                const SizedBox(height: 20),

                ElevatedButton(
                  onPressed: isSaving
                      ? null
                      : () async {
                          // Clean & normalize contact
                          String contact = contactCtrl.text.trim();
                          if (contact.length == 10 && contact.startsWith('9')) {
                            contact = '0$contact';
                          }

                          // 1. Recipient Name validation
                          final name = nameCtrl.text.trim();
                          if (name.length < 2) {
                            setSheetState(() => sheetError = 'Recipient name must be at least 2 characters.');
                            return;
                          }

                          // 2. Contact validation (Philippine format)
                          if (!_phMobileRegex.hasMatch(contact)) {
                            setSheetState(() => sheetError = 'Invalid Philippine mobile number. Must start with 09 (11 digits) or +639 (13 characters).');
                            return;
                          }

                          // 3. Location validation
                          if (formData.region.trim().isEmpty ||
                              formData.province.trim().isEmpty ||
                              formData.municipality.trim().isEmpty ||
                              formData.barangay.trim().isEmpty) {
                            setSheetState(() => sheetError = 'Please select or complete region, province, city/municipality, and barangay.');
                            return;
                          }

                          // 4. Postal Code validation (must be 4 digits if present)
                          final postal = formData.postalCode?.trim();
                          if (postal != null && postal.isNotEmpty && !_postalCodeRegex.hasMatch(postal)) {
                            setSheetState(() => sheetError = 'Postal ZIP code must be exactly 4 digits.');
                            return;
                          }

                          // 5. Label
                          final label = labelCtrl.text.trim().isNotEmpty ? labelCtrl.text.trim() : 'Home';

                          // Clear sheet error and show saving indicator
                          setSheetState(() {
                            sheetError = null;
                            isSaving = true;
                          });

                          // Prepare payload - send null for empty optional fields to prevent 422
                          final scaffoldMessenger = ScaffoldMessenger.of(context);
                          final payload = <String, dynamic>{
                            'label': label,
                            'recipient_name': name,
                            'recipient_contact': contact,
                            'region': formData.region.trim(),
                            'province': formData.province.trim(),
                            'municipality': formData.municipality.trim(),
                            'barangay': formData.barangay.trim(),
                            'street_address': (formData.streetAddress != null && formData.streetAddress!.trim().isNotEmpty)
                                ? formData.streetAddress!.trim()
                                : null,
                            'house_number': (formData.houseNumber != null && formData.houseNumber!.trim().isNotEmpty)
                                ? formData.houseNumber!.trim()
                                : null,
                            'postal_code': (postal != null && postal.isNotEmpty) ? postal : null,
                            'is_default': isDefault,
                          };

                          try {
                            final res = address == null
                                ? await ApiService.post(ApiConfig.addressesEndpoint, payload)
                                : await ApiService.put(ApiConfig.addressDetailEndpoint(address.id), payload);

                            final body = jsonDecode(res.body);

                            if ((res.statusCode == 200 || res.statusCode == 201) && body['success'] == true) {
                              if (ctx.mounted) {
                                Navigator.pop(ctx);
                              }
                              scaffoldMessenger.showSnackBar(
                                SnackBar(
                                  content: Text(body['message'] ?? 'Delivery address saved successfully!'),
                                  backgroundColor: Colors.green,
                                ),
                              );
                              if (mounted) {
                                await _fetchAddresses();
                              }
                            } else {
                              String errorMsg = body['message'] ?? 'Failed to save address.';
                              if (body['errors'] is Map) {
                                final errMap = body['errors'] as Map<String, dynamic>;
                                final messages = errMap.values.expand((v) => v is List ? v : [v.toString()]).join('\n');
                                if (messages.isNotEmpty) errorMsg = messages;
                              }
                              setSheetState(() {
                                isSaving = false;
                                sheetError = errorMsg;
                              });
                            }
                          } catch (e) {
                            setSheetState(() {
                              isSaving = false;
                              sheetError = 'Network error: ${e.toString()}';
                            });
                          }
                        },
                  child: isSaving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : Text(address == null ? 'Save Delivery Address' : 'Update Delivery Address'),
                ),
                const SizedBox(height: 24),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _makeDefault(int id) async {
    try {
      final res = await ApiService.patch(ApiConfig.addressDefaultEndpoint(id), {});
      final body = jsonDecode(res.body);
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(body['message'] ?? 'Default delivery address updated.'), backgroundColor: Colors.green),
        );
        _fetchAddresses();
      }
    } catch (_) {}
  }

  Future<void> _deleteAddress(AddressModel addr) async {
    if (addr.isDefault && _addresses.length > 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('The default address cannot be deleted. Please set another address as default first.'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Address'),
        content: Text('Are you sure you want to remove "${addr.label}" from your address book?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirm == true) {
      try {
        final res = await ApiService.delete(ApiConfig.addressDetailEndpoint(addr.id));
        final body = jsonDecode(res.body);
        if (res.statusCode == 200 && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(body['message'] ?? 'Address removed from your address book.'), backgroundColor: Colors.green),
          );
          _fetchAddresses();
        } else if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(body['message'] ?? 'Failed to delete address.'),
              backgroundColor: Colors.red,
            ),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.selectMode ? 'Select Delivery Address' : 'Address Book'),
      ),
      floatingActionButton: _addresses.length < 10
          ? FloatingActionButton(
              backgroundColor: AppTheme.primary,
              onPressed: () => _showAddEditSheet(),
              child: const Icon(Icons.add, color: Colors.white),
            )
          : null,
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _addresses.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.location_off_outlined, size: 54, color: Colors.grey.shade400),
                        const SizedBox(height: 12),
                        const Text('No saved addresses yet', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        const SizedBox(height: 4),
                        const Text('Add your delivery address for fast checkout.', style: TextStyle(color: AppTheme.muted, fontSize: 13)),
                        const SizedBox(height: 20),
                        ElevatedButton.icon(
                          onPressed: () => _showAddEditSheet(),
                          icon: const Icon(Icons.add, size: 18),
                          label: const Text('Add Address'),
                        ),
                      ],
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: _fetchAddresses,
                    child: ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _addresses.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) {
                        final addr = _addresses[i];

                        return Card(
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                            side: BorderSide(
                              color: addr.isDefault ? AppTheme.primary : AppTheme.line,
                              width: addr.isDefault ? 1.5 : 1,
                            ),
                          ),
                          child: InkWell(
                            onTap: widget.selectMode ? () => Navigator.of(context).pop(addr) : null,
                            borderRadius: BorderRadius.circular(12),
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Text(addr.label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                      if (addr.isDefault) ...[
                                        const SizedBox(width: 8),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: AppTheme.primarySoft,
                                            borderRadius: BorderRadius.circular(4),
                                          ),
                                          child: const Text('DEFAULT', style: TextStyle(color: AppTheme.primary, fontSize: 9, fontWeight: FontWeight.bold)),
                                        ),
                                      ],
                                      const Spacer(),
                                      IconButton(
                                        icon: const Icon(Icons.edit_outlined, size: 18),
                                        onPressed: () => _showAddEditSheet(address: addr),
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.delete_outline, size: 18, color: Colors.red),
                                        onPressed: () => _deleteAddress(addr),
                                      ),
                                    ],
                                  ),
                                  const Divider(height: 12),
                                  Text('${addr.recipientName} • ${addr.recipientContact}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                                  const SizedBox(height: 4),
                                  Text(addr.fullAddress, style: const TextStyle(color: AppTheme.muted, fontSize: 13)),
                                  if (!addr.isDefault && !widget.selectMode) ...[
                                    const SizedBox(height: 8),
                                    Align(
                                      alignment: Alignment.centerRight,
                                      child: TextButton(
                                        onPressed: () => _makeDefault(addr.id),
                                        child: const Text('Set as Default', style: TextStyle(fontSize: 12, color: AppTheme.primary)),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
      ),
    );
  }
}
