import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/address_model.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  void _showChangePasswordDialog() {
    final currentCtrl = TextEditingController();
    final newCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Change Password'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: currentCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Current Password'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: newCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'New Password (min 8 chars)'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: confirmCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Confirm New Password'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              if (newCtrl.text != confirmCtrl.text) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Passwords do not match.')),
                );
                return;
              }
              final auth = context.read<AuthProvider>();
              final nav = Navigator.of(context);
              final scaffold = ScaffoldMessenger.of(context);
              final success = await auth.updatePassword(currentCtrl.text, newCtrl.text);
              if (!mounted) return;
              nav.pop();
              scaffold.showSnackBar(
                SnackBar(
                  content: Text(success ? 'Password updated successfully!' : (auth.errorMessage ?? 'Update failed.')),
                  backgroundColor: success ? Colors.green : Colors.red,
                ),
              );
            },
            child: const Text('Update Password'),
          ),
        ],
      ),
    );
  }

  void _showDomainSettings() {
    final domainCtrl = TextEditingController(text: ApiConfig.baseUrl);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Backend API Server', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Configure the Laravel backend API URL:',
                style: TextStyle(fontSize: 13, color: AppTheme.muted),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: domainCtrl,
                decoration: const InputDecoration(
                  labelText: 'API Base URL',
                  hintText: 'http://127.0.0.1:8000/api',
                ),
              ),
              const SizedBox(height: 12),
              const Text('Quick Presets:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.muted)),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  ActionChip(
                    label: const Text('127.0.0.1:8000 (Local PC/Web)', style: TextStyle(fontSize: 11)),
                    onPressed: () => domainCtrl.text = 'http://127.0.0.1:8000/api',
                  ),
                  ActionChip(
                    label: const Text('10.0.2.2:8000 (Android Emulator)', style: TextStyle(fontSize: 11)),
                    onPressed: () => domainCtrl.text = 'http://10.0.2.2:8000/api',
                  ),
                  ActionChip(
                    label: const Text('Live Server (https://shop.ezicart.trade/api/v1)', style: TextStyle(fontSize: 11)),
                    onPressed: () => domainCtrl.text = 'https://shop.ezicart.trade/api/v1',
                  ),
                ],
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              final nav = Navigator.of(context);
              await ApiConfig.resetBaseUrl();
              if (!mounted) return;
              nav.pop();
              setState(() {});
            },
            child: const Text('Reset Default'),
          ),
          ElevatedButton(
            onPressed: () async {
              final nav = Navigator.of(context);
              if (domainCtrl.text.trim().isNotEmpty) {
                await ApiConfig.setBaseUrl(domainCtrl.text.trim());
              }
              if (!mounted) return;
              nav.pop();
              setState(() {});
            },
            child: const Text('Save Domain'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Profile'),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // User Avatar & Name Card
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
                side: const BorderSide(color: AppTheme.line),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 32,
                      backgroundColor: AppTheme.primarySoft,
                      child: Text(
                        user?.name.isNotEmpty == true ? user!.name[0].toUpperCase() : 'B',
                        style: const TextStyle(
                          fontSize: 24,
                          color: AppTheme.primary,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user?.name ?? 'Buyer Account',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            user?.email ?? '',
                            style: const TextStyle(color: AppTheme.muted, fontSize: 13),
                          ),
                          const SizedBox(height: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppTheme.primarySoft,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: const Text(
                              'VERIFIED BUYER',
                              style: TextStyle(
                                color: AppTheme.primary,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Profile info items
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
                side: const BorderSide(color: AppTheme.line),
              ),
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.phone_outlined, color: AppTheme.primary),
                    title: const Text('Mobile Contact', style: TextStyle(fontSize: 13, color: AppTheme.muted)),
                    subtitle: Text(user?.contactNo ?? 'Not set', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.location_on_outlined, color: AppTheme.primary),
                    title: const Text('Primary Address', style: TextStyle(fontSize: 13, color: AppTheme.muted)),
                    subtitle: Text(user?.fullAddress ?? 'No registered address', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Settings & Tools
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
                side: const BorderSide(color: AppTheme.line),
              ),
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.bookmark_border, color: AppTheme.ink),
                    title: const Text('Saved Delivery Addresses'),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const AddressBookPage()),
                      );
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.lock_outline, color: AppTheme.ink),
                    title: const Text('Change Password'),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: _showChangePasswordDialog,
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.cloud_outlined, color: AppTheme.ink),
                    title: const Text('Production API Domain'),
                    subtitle: Text(ApiConfig.baseUrl, style: const TextStyle(fontSize: 11, color: AppTheme.muted)),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: _showDomainSettings,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red.shade700,
                minimumSize: const Size(double.infinity, 46),
              ),
              onPressed: () {
                showDialog(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    title: const Text('Sign Out'),
                    content: const Text('Are you sure you want to sign out from this device?'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
                        onPressed: () {
                          Navigator.pop(ctx);
                          context.read<AuthProvider>().logout();
                        },
                        child: const Text('Sign Out'),
                      ),
                    ],
                  ),
                );
              },
              child: const Text('Sign Out'),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }
}

class AddressBookPage extends StatefulWidget {
  const AddressBookPage({super.key});

  @override
  State<AddressBookPage> createState() => _AddressBookPageState();
}

class _AddressBookPageState extends State<AddressBookPage> {
  List<AddressModel> _addresses = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchAddresses();
  }

  Future<void> _fetchAddresses() async {
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

  void _showAddEditAddressDialog({AddressModel? address}) {
    final labelCtrl = TextEditingController(text: address?.label ?? 'Home');
    final nameCtrl = TextEditingController(text: address?.recipientName ?? '');
    final contactCtrl = TextEditingController(text: address?.recipientContact ?? '');
    final provinceCtrl = TextEditingController(text: address?.province ?? 'Laguna');
    final muniCtrl = TextEditingController(text: address?.municipality ?? 'Lumban');
    final brgyCtrl = TextEditingController(text: address?.barangay ?? 'Poblacion');
    final streetCtrl = TextEditingController(text: address?.streetAddress ?? '');
    bool isDefault = address?.isDefault ?? false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDlgState) => AlertDialog(
          title: Text(address == null ? 'Add Delivery Address' : 'Edit Address'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: labelCtrl, decoration: const InputDecoration(labelText: 'Label (e.g. Home, Work)')),
                const SizedBox(height: 8),
                TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Recipient Full Name *')),
                const SizedBox(height: 8),
                TextField(controller: contactCtrl, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Recipient Mobile *')),
                const SizedBox(height: 8),
                TextField(controller: provinceCtrl, decoration: const InputDecoration(labelText: 'Province *')),
                const SizedBox(height: 8),
                TextField(controller: muniCtrl, decoration: const InputDecoration(labelText: 'Municipality *')),
                const SizedBox(height: 8),
                TextField(controller: brgyCtrl, decoration: const InputDecoration(labelText: 'Barangay *')),
                const SizedBox(height: 8),
                TextField(controller: streetCtrl, decoration: const InputDecoration(labelText: 'Street Address')),
                const SizedBox(height: 8),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: isDefault,
                  onChanged: (v) => setDlgState(() => isDefault = v ?? false),
                  title: const Text('Set as default address', style: TextStyle(fontSize: 13)),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(
              onPressed: () async {
                if (nameCtrl.text.isEmpty || contactCtrl.text.isEmpty) return;
                Navigator.pop(ctx);

                final payload = {
                  'label': labelCtrl.text.trim(),
                  'recipient_name': nameCtrl.text.trim(),
                  'recipient_contact': contactCtrl.text.trim(),
                  'region': 'Region IV-A (CALABARZON)',
                  'province': provinceCtrl.text.trim(),
                  'municipality': muniCtrl.text.trim(),
                  'barangay': brgyCtrl.text.trim(),
                  'street_address': streetCtrl.text.trim(),
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
              child: const Text('Save Address'),
            ),
          ],
        ),
      ),
    );
  }

  void _deleteAddress(int id) async {
    try {
      await ApiService.delete(ApiConfig.addressDetailEndpoint(id));
      _fetchAddresses();
    } catch (_) {}
  }

  void _makeDefault(int id) async {
    try {
      await ApiService.patch(ApiConfig.addressDefaultEndpoint(id), {});
      _fetchAddresses();
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Address Book'),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppTheme.primary,
        onPressed: () => _showAddEditAddressDialog(),
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _addresses.isEmpty
                ? const Center(child: Text('No saved addresses yet.'))
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _addresses.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, i) {
                      final addr = _addresses[i];
                      return Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                          side: BorderSide(color: addr.isDefault ? AppTheme.primary : AppTheme.line),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
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
                                    onPressed: () => _showAddEditAddressDialog(address: addr),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.delete_outline, size: 18, color: Colors.red),
                                    onPressed: () => _deleteAddress(addr.id),
                                  ),
                                ],
                              ),
                              const Divider(height: 12),
                              Text('${addr.recipientName} (${addr.recipientContact})', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                              const SizedBox(height: 2),
                              Text(addr.fullAddress, style: const TextStyle(color: AppTheme.muted, fontSize: 12)),
                              if (!addr.isDefault) ...[
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
                      );
                    },
                  ),
      ),
    );
  }
}
