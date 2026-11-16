import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import 'change_password_page.dart';
import 'work_availability_page.dart';

class CourierSettingsPage extends StatefulWidget {
  const CourierSettingsPage({super.key});

  @override
  State<CourierSettingsPage> createState() => _CourierSettingsPageState();
}

class _CourierSettingsPageState extends State<CourierSettingsPage> {
  final _formKey = GlobalKey<FormState>();

  // Profile controllers
  final _firstNameCtrl = TextEditingController();
  final _middleNameCtrl = TextEditingController();
  final _middleInitialCtrl = TextEditingController();
  final _lastNameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _contactCtrl = TextEditingController();

  // Address controllers
  final _regionCtrl = TextEditingController();
  final _provinceCtrl = TextEditingController();
  final _municipalityCtrl = TextEditingController();
  final _barangayCtrl = TextEditingController();
  final _streetCtrl = TextEditingController();
  final _postalCtrl = TextEditingController();

  // Vehicle & licence controllers
  String? _selectedVehicleType;
  final _plateCtrl = TextEditingController();
  String? _selectedVehicleCategory;
  final _vehicleDescCtrl = TextEditingController();
  final _licenseNoCtrl = TextEditingController();
  final _licenseExpiryCtrl = TextEditingController();

  bool _isLoading = true;
  bool _isSaving = false;

  static const List<String> _vehicleTypes = [
    'Motorcycle',
    'Bicycle',
    'E-Bike / Scooter',
    'Sedan',
    'Hatchback',
    'MPV / SUV',
    'Van',
    'Light Truck',
    'Heavy Cargo Truck',
  ];

  static const Map<String, String> _vehicleCategories = {
    '2-wheels': 'Two-Wheeled (Motorcycle / Bike)',
    '4-wheels': 'Four-Wheeled (Sedan / MPV / Van)',
    'light-truck': 'Light Commercial Truck',
    'heavy-truck': 'Heavy Multi-Axle Truck',
  };

  @override
  void initState() {
    super.initState();
    _initFields();
  }

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _middleNameCtrl.dispose();
    _middleInitialCtrl.dispose();
    _lastNameCtrl.dispose();
    _emailCtrl.dispose();
    _contactCtrl.dispose();
    _regionCtrl.dispose();
    _provinceCtrl.dispose();
    _municipalityCtrl.dispose();
    _barangayCtrl.dispose();
    _streetCtrl.dispose();
    _postalCtrl.dispose();
    _plateCtrl.dispose();
    _vehicleDescCtrl.dispose();
    _licenseNoCtrl.dispose();
    _licenseExpiryCtrl.dispose();
    super.dispose();
  }

  Future<void> _initFields() async {
    setState(() {
      _isLoading = true;
    });

    final auth = context.read<AuthProvider>();
    final user = auth.user;

    if (user != null) {
      _populateFromUser(user);
    }

    try {
      final res = await ApiService.get(ApiConfig.courierProfileEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['courier'] is Map) {
          final c = body['courier'] as Map<String, dynamic>;
          _populateFromCourierMap(c);
        }
      }
    } catch (_) {
      // Fallback to auth.user values
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  void _populateFromUser(dynamic user) {
    _firstNameCtrl.text = user.firstName ?? '';
    _middleNameCtrl.text = user.middleName ?? '';
    _middleInitialCtrl.text = user.middleInitial ?? '';
    _lastNameCtrl.text = user.lastName ?? '';
    _emailCtrl.text = user.email ?? '';
    _contactCtrl.text = user.contactNo ?? '';

    _regionCtrl.text = user.region ?? '';
    _provinceCtrl.text = user.province ?? '';
    _municipalityCtrl.text = user.municipality ?? '';
    _barangayCtrl.text = user.barangay ?? '';
    _streetCtrl.text = user.streetAddress ?? '';
    _postalCtrl.text = user.postalCode ?? '';

    _plateCtrl.text = user.plateNumber ?? '';
    _vehicleDescCtrl.text = user.vehicleDescription ?? '';
    _licenseNoCtrl.text = user.licenseNumber ?? '';
    _licenseExpiryCtrl.text = user.licenseExpiresAt ?? '';

    if (user.vehicleType != null && user.vehicleType.toString().isNotEmpty) {
      final v = user.vehicleType.toString();
      _selectedVehicleType = _vehicleTypes.contains(v) ? v : null;
    }

    if (user.vehicleCategory != null && user.vehicleCategory.toString().isNotEmpty) {
      final c = user.vehicleCategory.toString();
      _selectedVehicleCategory = _vehicleCategories.containsKey(c) ? c : null;
    }
  }

  void _populateFromCourierMap(Map<String, dynamic> c) {
    if (_firstNameCtrl.text.isEmpty && c['first_name'] != null) {
      _firstNameCtrl.text = c['first_name'].toString();
    }
    if (_middleNameCtrl.text.isEmpty && c['middle_name'] != null) {
      _middleNameCtrl.text = c['middle_name'].toString();
    }
    if (_middleInitialCtrl.text.isEmpty && c['middle_initial'] != null) {
      _middleInitialCtrl.text = c['middle_initial'].toString();
    }
    if (_lastNameCtrl.text.isEmpty && c['last_name'] != null) {
      _lastNameCtrl.text = c['last_name'].toString();
    }
    if (c['email'] != null) {
      _emailCtrl.text = c['email'].toString();
    }
    if (c['contact_no'] != null) {
      _contactCtrl.text = c['contact_no'].toString();
    }

    if (c['region'] != null) _regionCtrl.text = c['region'].toString();
    if (c['province'] != null) _provinceCtrl.text = c['province'].toString();
    if (c['municipality'] != null) _municipalityCtrl.text = c['municipality'].toString();
    if (c['barangay'] != null) _barangayCtrl.text = c['barangay'].toString();
    if (c['street_address'] != null) _streetCtrl.text = c['street_address'].toString();
    if (c['postal_code'] != null) _postalCtrl.text = c['postal_code'].toString();

    if (c['plate_number'] != null) _plateCtrl.text = c['plate_number'].toString();
    if (c['vehicle_description'] != null) _vehicleDescCtrl.text = c['vehicle_description'].toString();
    if (c['license_number'] != null) _licenseNoCtrl.text = c['license_number'].toString();
    if (c['license_expires_at'] != null) _licenseExpiryCtrl.text = c['license_expires_at'].toString();

    if (c['vehicle_type'] != null) {
      final vt = c['vehicle_type'].toString();
      if (_vehicleTypes.contains(vt)) {
        _selectedVehicleType = vt;
      }
    }
    if (c['vehicle_category'] != null) {
      final vc = c['vehicle_category'].toString();
      if (_vehicleCategories.containsKey(vc)) {
        _selectedVehicleCategory = vc;
      }
    }
  }

  Future<void> _selectExpiryDate() async {
    final now = DateTime.now();
    DateTime initial = now.add(const Duration(days: 365));
    if (_licenseExpiryCtrl.text.isNotEmpty) {
      try {
        initial = DateTime.parse(_licenseExpiryCtrl.text);
      } catch (_) {}
    }

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365 * 10)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppTheme.primary,
              onPrimary: Colors.white,
              onSurface: AppTheme.ink,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      final y = picked.year.toString().padLeft(4, '0');
      final m = picked.month.toString().padLeft(2, '0');
      final d = picked.day.toString().padLeft(2, '0');
      setState(() {
        _licenseExpiryCtrl.text = '$y-$m-$d';
      });
    }
  }

  Future<void> _saveSettings() async {
    if (!_formKey.currentState!.validate()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please correct the validation errors before saving.'),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    String contact = _contactCtrl.text.trim();
    if (contact.length == 10 && contact.startsWith('9')) {
      contact = '0$contact';
    }
    if (!RegExp(r'^(09\d{9}|\+639\d{9})$').hasMatch(contact)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter a valid Philippine mobile number (09XXXXXXXXX).'),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    setState(() {
      _isSaving = true;
    });

    final payload = <String, dynamic>{
      'first_name': _firstNameCtrl.text.trim(),
      'last_name': _lastNameCtrl.text.trim(),
      'contact_no': contact,
      if (_middleNameCtrl.text.trim().isNotEmpty)
        'middle_name': _middleNameCtrl.text.trim(),
      if (_middleInitialCtrl.text.trim().isNotEmpty)
        'middle_initial': _middleInitialCtrl.text.trim(),
      if (_regionCtrl.text.trim().isNotEmpty)
        'region': _regionCtrl.text.trim(),
      if (_provinceCtrl.text.trim().isNotEmpty)
        'province': _provinceCtrl.text.trim(),
      if (_municipalityCtrl.text.trim().isNotEmpty)
        'municipality': _municipalityCtrl.text.trim(),
      if (_barangayCtrl.text.trim().isNotEmpty)
        'barangay': _barangayCtrl.text.trim(),
      if (_streetCtrl.text.trim().isNotEmpty)
        'street_address': _streetCtrl.text.trim(),
      if (_postalCtrl.text.trim().isNotEmpty)
        'postal_code': _postalCtrl.text.trim(),
      if (_selectedVehicleType != null)
        'vehicle_type': _selectedVehicleType,
      if (_plateCtrl.text.trim().isNotEmpty)
        'plate_number': _plateCtrl.text.trim().toUpperCase(),
      if (_selectedVehicleCategory != null)
        'vehicle_category': _selectedVehicleCategory,
      if (_vehicleDescCtrl.text.trim().isNotEmpty)
        'vehicle_description': _vehicleDescCtrl.text.trim(),
      if (_licenseNoCtrl.text.trim().isNotEmpty)
        'license_number': _licenseNoCtrl.text.trim().toUpperCase(),
      if (_licenseExpiryCtrl.text.trim().isNotEmpty)
        'license_expires_at': _licenseExpiryCtrl.text.trim(),
    };

    final auth = context.read<AuthProvider>();
    final success = await auth.updateProfile(payload);

    if (!mounted) return;

    setState(() => _isSaving = false);

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Profile and contact details updated successfully!'),
          backgroundColor: AppTheme.success,
        ),
      );
      Navigator.pop(context, true);
    } else {
      final msg = auth.errorMessage ?? 'Failed to update profile settings.';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(msg),
          backgroundColor: AppTheme.danger,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Account Settings'),
        actions: [
          if (!_isLoading)
            TextButton.icon(
              onPressed: _isSaving ? null : _saveSettings,
              icon: _isSaving
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.check, color: Colors.white, size: 18),
              label: Text(
                _isSaving ? 'Saving...' : 'Save',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
              ),
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _initFields,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _buildHeaderBanner(),
                      const SizedBox(height: 16),
                      _buildProfileCard(),
                      const SizedBox(height: 16),
                      _buildVehicleCard(),
                      const SizedBox(height: 16),
                      _buildAddressCard(),
                      const SizedBox(height: 16),
                      _buildSecurityCard(),
                      const SizedBox(height: 24),
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        onPressed: _isSaving ? null : _saveSettings,
                        icon: _isSaving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                              )
                            : const Icon(Icons.save_outlined),
                        label: Text(
                          _isSaving ? 'Saving Changes...' : 'Save Profile & Contact Details',
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const SizedBox(height: 32),
                    ],
                  ),
                ),
              ),
            ),
    );
  }

  Widget _buildHeaderBanner() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, color: AppTheme.primary, size: 22),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Profile and Contact Details',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.primary,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Keep your contact, vehicle, and address details updated. Official profile values synchronize directly with the web system and logistics operations.',
                  style: TextStyle(fontSize: 12, color: AppTheme.muted, height: 1.3),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProfileCard() {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.badge_outlined, color: AppTheme.primary),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Official Personal Details',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _firstNameCtrl,
                    decoration: const InputDecoration(
                      labelText: 'First Name *',
                      prefixIcon: Icon(Icons.person_outline, size: 18),
                    ),
                    textCapitalization: TextCapitalization.words,
                    validator: (v) {
                      if (v == null || v.trim().length < 2) {
                        return 'Min 2 characters';
                      }
                      return null;
                    },
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 1,
                  child: TextFormField(
                    controller: _middleInitialCtrl,
                    decoration: const InputDecoration(
                      labelText: 'M.I.',
                      counterText: '',
                    ),
                    maxLength: 2,
                    textCapitalization: TextCapitalization.characters,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _lastNameCtrl,
              decoration: const InputDecoration(
                labelText: 'Last Name *',
                prefixIcon: Icon(Icons.person_outline, size: 18),
              ),
              textCapitalization: TextCapitalization.words,
              validator: (v) {
                if (v == null || v.trim().length < 2) {
                  return 'Min 2 characters';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _emailCtrl,
              enabled: false,
              decoration: InputDecoration(
                labelText: 'Account Login Email (Read-only)',
                prefixIcon: const Icon(Icons.lock_outline, size: 18),
                fillColor: Colors.grey.shade100,
                filled: true,
                helperText: 'Email is locked to your account credentials',
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _contactCtrl,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Contact Mobile Number *',
                hintText: '09XXXXXXXXX or +639XXXXXXXXX',
                prefixIcon: Icon(Icons.phone_android_outlined, size: 18),
              ),
              validator: (v) {
                if (v == null || v.trim().isEmpty) return 'Contact number is required';
                String clean = v.trim();
                if (clean.length == 10 && clean.startsWith('9')) clean = '0$clean';
                if (!RegExp(r'^(09\d{9}|\+639\d{9})$').hasMatch(clean)) {
                  return 'Enter valid PH number (09XXXXXXXXX)';
                }
                return null;
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildVehicleCard() {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.two_wheeler, color: AppTheme.primary),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Vehicle and Licence Details',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _selectedVehicleType,
              decoration: const InputDecoration(
                labelText: 'Vehicle Type',
                prefixIcon: Icon(Icons.directions_car_outlined, size: 18),
              ),
              items: _vehicleTypes.map((type) {
                return DropdownMenuItem(value: type, child: Text(type));
              }).toList(),
              onChanged: (v) => setState(() => _selectedVehicleType = v),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _plateCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Plate / MV File Number',
                      hintText: 'e.g. ABC 1234',
                      prefixIcon: Icon(Icons.confirmation_number_outlined, size: 18),
                    ),
                    textCapitalization: TextCapitalization.characters,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 3,
                  child: DropdownButtonFormField<String>(
                    initialValue: _selectedVehicleCategory,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Category',
                    ),
                    items: _vehicleCategories.entries.map((e) {
                      return DropdownMenuItem(
                        value: e.key,
                        child: Text(e.key, overflow: TextOverflow.ellipsis),
                      );
                    }).toList(),
                    onChanged: (v) => setState(() => _selectedVehicleCategory = v),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _vehicleDescCtrl,
              maxLength: 120,
              decoration: const InputDecoration(
                labelText: 'Vehicle Model & Color Description',
                hintText: 'e.g. Honda Click 125i (Red/Black)',
                prefixIcon: Icon(Icons.info_outline, size: 18),
                counterText: '',
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _licenseNoCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Licence Number',
                      hintText: 'A00-00-000000',
                      prefixIcon: Icon(Icons.card_membership_outlined, size: 18),
                    ),
                    textCapitalization: TextCapitalization.characters,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _licenseExpiryCtrl,
                    readOnly: true,
                    onTap: _selectExpiryDate,
                    decoration: const InputDecoration(
                      labelText: 'Expiry Date',
                      hintText: 'YYYY-MM-DD',
                      prefixIcon: Icon(Icons.calendar_today_outlined, size: 18),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAddressCard() {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.location_on_outlined, color: AppTheme.primary),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Operational Base & Address',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _provinceCtrl,
                    decoration: const InputDecoration(labelText: 'Province'),
                    textCapitalization: TextCapitalization.words,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextFormField(
                    controller: _municipalityCtrl,
                    decoration: const InputDecoration(labelText: 'Municipality / City'),
                    textCapitalization: TextCapitalization.words,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: _barangayCtrl,
                    decoration: const InputDecoration(labelText: 'Barangay'),
                    textCapitalization: TextCapitalization.words,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 1,
                  child: TextFormField(
                    controller: _postalCtrl,
                    keyboardType: TextInputType.number,
                    maxLength: 4,
                    decoration: const InputDecoration(labelText: 'Postal Code', counterText: ''),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _streetCtrl,
              decoration: const InputDecoration(
                labelText: 'Street Address & Landmark',
                hintText: 'House / Unit #, Building, Street, Landmark',
                prefixIcon: Icon(Icons.home_outlined, size: 18),
              ),
              textCapitalization: TextCapitalization.words,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSecurityCard() {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(
        children: [
          ListTile(
            leading: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppTheme.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.lock_reset, color: AppTheme.primary, size: 20),
            ),
            title: const Text('Change Password', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
            subtitle: const Text('Update your password securely with email code verification', style: TextStyle(fontSize: 12)),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ChangePasswordPage()),
              );
            },
          ),
          const Divider(height: 1),
          ListTile(
            leading: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppTheme.success.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.toggle_on, color: AppTheme.success, size: 20),
            ),
            title: const Text('Work Availability Status', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
            subtitle: const Text('Set whether you are available, on break, busy, or offline', style: TextStyle(fontSize: 12)),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const WorkAvailabilityPage()),
              );
            },
          ),
        ],
      ),
    );
  }
}
