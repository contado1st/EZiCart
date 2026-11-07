import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import 'addresses_page.dart';
import 'disputes_list_page.dart';
import 'for_you_page.dart';
import 'notifications_page.dart';
import 'stock_watchlist_page.dart';
import 'vouchers_page.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  bool _promotionsEnabled = true;

  @override
  void initState() {
    super.initState();
    _loadPreferences();
  }

  Future<void> _loadPreferences() async {
    try {
      final res = await ApiService.get(ApiConfig.notificationPreferencesEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['seller_promotions_enabled'] is bool && mounted) {
          setState(() => _promotionsEnabled = body['seller_promotions_enabled']);
        }
      }
    } catch (_) {}
  }

  Future<void> _updatePromotions(bool value) async {
    setState(() => _promotionsEnabled = value);
    try {
      await ApiService.patch(
        ApiConfig.notificationPreferencesEndpoint,
        {'seller_promotions_enabled': value},
      );
    } catch (_) {}
  }
  void _showEditProfileDialog() {
    final auth = context.read<AuthProvider>();
    final user = auth.user;

    final firstCtrl = TextEditingController(text: user?.firstName ?? '');
    final lastCtrl = TextEditingController(text: user?.lastName ?? '');
    final contactCtrl = TextEditingController(text: user?.contactNo ?? '');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit Profile Details'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: firstCtrl,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'First Name *'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: lastCtrl,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Last Name *'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: contactCtrl,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Contact Number *', hintText: '09XXXXXXXXX'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              if (firstCtrl.text.trim().isEmpty || lastCtrl.text.trim().isEmpty || contactCtrl.text.trim().isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Please fill out all required fields.')),
                );
                return;
              }

              final nav = Navigator.of(context);
              final scaffold = ScaffoldMessenger.of(context);

              final success = await auth.updateProfile({
                'first_name': firstCtrl.text.trim(),
                'last_name': lastCtrl.text.trim(),
                'contact_no': contactCtrl.text.trim(),
              });

              if (!mounted) return;
              nav.pop();
              scaffold.showSnackBar(
                SnackBar(
                  content: Text(success ? 'Profile updated successfully!' : (auth.errorMessage ?? 'Update failed.')),
                  backgroundColor: success ? Colors.green : Colors.red,
                ),
              );
            },
            child: const Text('Save Changes'),
          ),
        ],
      ),
    );
  }

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
              const SizedBox(height: 8),
              const Text(
                'Use 10.0.2.2 for Android emulator or 127.0.0.1 for desktop/web.',
                style: TextStyle(fontSize: 11, color: AppTheme.muted),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              await ApiConfig.resetBaseUrl();
              if (ctx.mounted) Navigator.pop(ctx);
              setState(() {});
            },
            child: const Text('Reset Default'),
          ),
          ElevatedButton(
            onPressed: () async {
              await ApiConfig.setBaseUrl(domainCtrl.text);
              if (ctx.mounted) Navigator.pop(ctx);
              setState(() {});
            },
            child: const Text('Save & Apply'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // User header banner
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: const BorderSide(color: AppTheme.line),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 30,
                      backgroundColor: AppTheme.primary,
                      child: Text(
                        (user?.firstName.isNotEmpty == true) ? user!.firstName[0].toUpperCase() : 'B',
                        style: const TextStyle(fontSize: 24, color: Colors.white, fontWeight: FontWeight.bold),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user?.fullName ?? 'Buyer Account',
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
                    IconButton(
                      icon: const Icon(Icons.edit_outlined, size: 20, color: AppTheme.primary),
                      tooltip: 'Edit Profile',
                      onPressed: _showEditProfileDialog,
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

            // Buyer features section
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
                side: const BorderSide(color: AppTheme.line),
              ),
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.notifications_outlined, color: Colors.purple),
                    title: const Text('Notifications Center'),
                    subtitle: const Text('Order updates, messages, promos, and restocks', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const NotificationsPage()),
                      );
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.confirmation_number_outlined, color: Colors.indigo),
                    title: const Text('Vouchers & Promos'),
                    subtitle: const Text('Claim platform discounts and seller shop vouchers', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const VouchersPage()),
                      );
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.auto_awesome_outlined, color: Colors.teal),
                    title: const Text('Personalized Recommendations'),
                    subtitle: const Text('View curated "For You" picks based on your activity', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const ForYouPage()),
                      );
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.notifications_active_outlined, color: Colors.amber),
                    title: const Text('Stock Alerts Watchlist'),
                    subtitle: const Text('Track availability and restock notifications for watched items', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const StockWatchlistPage()),
                      );
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.shield_outlined, color: Colors.deepOrange),
                    title: const Text('Return & Dispute Cases'),
                    subtitle: const Text('Track mediated claims and resolution statuses', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const DisputesListPage()),
                      );
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Settings & Security
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
                    subtitle: const Text('Manage official locations with PSGC API integration', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const AddressesPage()),
                      );
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                  SwitchListTile(
                    secondary: const Icon(Icons.campaign_outlined, color: AppTheme.ink),
                    title: const Text('Store Broadcasts & Offers'),
                    subtitle: const Text('Receive promotional campaign updates from followed merchants', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    value: _promotionsEnabled,
                    activeTrackColor: AppTheme.primary,
                    onChanged: _updatePromotions,
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
