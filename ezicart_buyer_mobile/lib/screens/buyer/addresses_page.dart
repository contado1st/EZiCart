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
          setState(() {
            _addresses = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _showAddEditSheet({AddressModel? address}) {
    final labelCtrl = TextEditingController(text: address?.label ?? 'Home');
    final nameCtrl = TextEditingController(text: address?.recipientName ?? '');
    final contactCtrl = TextEditingController(text: address?.recipientContact ?? '');
    bool isDefault = address?.isDefault ?? false;

    AddressFormData formData = AddressFormData(
      region: address?.region ?? '',
      province: address?.province ?? '',
      municipality: address?.municipality ?? '',
      barangay: address?.barangay ?? '',
      houseNumber: address?.houseNumber,
      streetAddress: address?.streetAddress,
      postalCode: address?.postalCode,
      isManual: address == null,
    );

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
                      onPressed: () => Navigator.pop(ctx),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Label & Recipient
                Row(
                  children: [
                    Expanded(
                      flex: 1,
                      child: TextField(
                        controller: labelCtrl,
                        decoration: const InputDecoration(labelText: 'Label (e.g. Home)'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 2,
                      child: TextField(
                        controller: nameCtrl,
                        decoration: const InputDecoration(labelText: 'Recipient Name *'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                TextField(
                  controller: contactCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Recipient Mobile Number *',
                    hintText: '09XXXXXXXXX',
                    prefixIcon: Icon(Icons.phone_android_outlined, size: 20),
                  ),
                ),
                const SizedBox(height: 16),

                // PSGC Address Section
                PsgcAddressForm(
                  initialData: formData,
                  onChanged: (data) => formData = data,
                ),
                const SizedBox(height: 12),

                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: isDefault,
                  onChanged: (v) => setSheetState(() => isDefault = v ?? false),
                  title: const Text('Set as default delivery address', style: TextStyle(fontSize: 13)),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                const SizedBox(height: 20),

                ElevatedButton(
                  onPressed: () async {
                    if (nameCtrl.text.trim().isEmpty || contactCtrl.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Please enter recipient name and contact number.')),
                      );
                      return;
                    }
                    if (formData.region.isEmpty || formData.province.isEmpty || formData.municipality.isEmpty || formData.barangay.isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Please complete the region, province, city, and barangay location details.')),
                      );
                      return;
                    }

                    Navigator.pop(ctx);

                    final payload = {
                      'label': labelCtrl.text.trim().isNotEmpty ? labelCtrl.text.trim() : 'Delivery',
                      'recipient_name': nameCtrl.text.trim(),
                      'recipient_contact': contactCtrl.text.trim(),
                      'region': formData.region,
                      'province': formData.province,
                      'municipality': formData.municipality,
                      'barangay': formData.barangay,
                      'street_address': formData.streetAddress,
                      'house_number': formData.houseNumber,
                      'postal_code': formData.postalCode,
                      'is_default': isDefault,
                    };

                    try {
                      if (address == null) {
                        await ApiService.post(ApiConfig.addressesEndpoint, payload);
                      } else {
                        await ApiService.put(ApiConfig.addressDetailEndpoint(address.id), payload);
                      }
                      _fetchAddresses();
                    } catch (_) {}
                  },
                  child: const Text('Save Delivery Address'),
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
      await ApiService.patch(ApiConfig.addressDefaultEndpoint(id), {});
      _fetchAddresses();
    } catch (_) {}
  }

  Future<void> _deleteAddress(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Address'),
        content: const Text('Are you sure you want to remove this address from your address book?'),
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
        final res = await ApiService.delete(ApiConfig.addressDetailEndpoint(id));
        final body = jsonDecode(res.body);
        if (res.statusCode == 200) {
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
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppTheme.primary,
        onPressed: () => _showAddEditSheet(),
        child: const Icon(Icons.add, color: Colors.white),
      ),
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
                                        onPressed: () => _deleteAddress(addr.id),
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

