import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import 'change_password_page.dart';
import 'work_availability_page.dart';

class RiderProfilePage extends StatefulWidget {
  const RiderProfilePage({super.key});

  @override
  State<RiderProfilePage> createState() => _RiderProfilePageState();
}

class _RiderProfilePageState extends State<RiderProfilePage> {
  bool _isLoading = true;
  String? _errorMessage;
  Map<String, dynamic>? _courierData;

  final _displayNameCtrl = TextEditingController();
  final _bioCtrl = TextEditingController();
  String _selectedTheme = 'rose';
  bool _isSavingAppearance = false;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  @override
  void dispose() {
    _displayNameCtrl.dispose();
    _bioCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadProfile() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiService.get(ApiConfig.courierProfileEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['courier'] is Map) {
          _courierData = Map<String, dynamic>.from(data['courier'] as Map);
          _displayNameCtrl.text = _courierData?['profile_display_name']?.toString() ?? '';
          _bioCtrl.text = _courierData?['profile_bio']?.toString() ?? '';
          _selectedTheme = _courierData?['profile_theme']?.toString() ?? 'rose';
        }
      } else {
        _errorMessage = 'Failed to load profile details (${res.statusCode})';
      }
    } catch (e) {
      _errorMessage = 'Error: $e';
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _pickAndUploadAvatar() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.gallery, maxWidth: 800, imageQuality: 85);
    if (picked == null || !mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Uploading avatar...')),
    );

    try {
      final res = await ApiService.multipartPost(
        url: ApiConfig.updateAppearanceEndpoint,
        fields: {
          'profile_display_name': _displayNameCtrl.text.trim(),
          'profile_bio': _bioCtrl.text.trim(),
          'profile_theme': _selectedTheme,
        },
        fileField: 'avatar',
        filePath: picked.path,
      );

      final body = jsonDecode(res.body);
      if (res.statusCode == 200 && (body['success'] == true || body['status'] == 'success')) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Avatar updated successfully!'),
              backgroundColor: AppTheme.success,
            ),
          );
          context.read<AuthProvider>().refreshUser();
          _loadProfile();
        }
      } else {
        final msg = body['message'] ?? 'Failed to update avatar.';
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(msg), backgroundColor: AppTheme.danger),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.danger),
        );
      }
    }
  }

  Future<void> _saveAppearance() async {
    setState(() => _isSavingAppearance = true);
    try {
      final res = await ApiService.post(ApiConfig.updateAppearanceEndpoint, {
        'profile_display_name': _displayNameCtrl.text.trim(),
        'profile_bio': _bioCtrl.text.trim(),
        'profile_theme': _selectedTheme,
      });

      final body = jsonDecode(res.body);
      if (res.statusCode == 200 && (body['success'] == true || body['status'] == 'success')) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Profile appearance saved!'),
              backgroundColor: AppTheme.success,
            ),
          );
          context.read<AuthProvider>().refreshUser();
          _loadProfile();
        }
      } else {
        final msg = body['message'] ?? 'Failed to update appearance.';
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(msg), backgroundColor: AppTheme.danger),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.danger),
        );
      }
    } finally {
      if (mounted) setState(() => _isSavingAppearance = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Rider Profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadProfile,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: AppTheme.danger),
                        const SizedBox(height: 12),
                        Text(_errorMessage!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        ElevatedButton(onPressed: _loadProfile, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadProfile,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _buildIdentityCard(),
                        const SizedBox(height: 16),
                        _buildVehicleCard(),
                        const SizedBox(height: 16),
                        _buildLogisticsAndServiceCard(),
                        const SizedBox(height: 16),
                        _buildAppearanceCard(),
                        const SizedBox(height: 16),
                        _buildSecurityCard(),
                        const SizedBox(height: 24),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _buildIdentityCard() {
    final name = _courierData?['name'] ?? 'Courier Rider';
    final email = _courierData?['email'] ?? '';
    final contact = _courierData?['contact_no'] ?? 'No contact number';
    final badgeCode = _courierData?['badge_code'] ?? 'N/A';
    final avatarUrl = _courierData?['avatar_url'] as String?;
    final workAvail = _courierData?['work_availability'] ?? 'available';

    Color availColor = AppTheme.success;
    if (workAvail == 'busy') availColor = Colors.orange;
    if (workAvail == 'break') availColor = Colors.amber.shade700;
    if (workAvail == 'offline') availColor = Colors.grey;

    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Row(
              children: [
                Stack(
                  children: [
                    CircleAvatar(
                      radius: 36,
                      backgroundColor: AppTheme.primary.withValues(alpha: 0.15),
                      backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl) : null,
                      child: avatarUrl == null
                          ? Text(
                              name.isNotEmpty ? name[0].toUpperCase() : 'C',
                              style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: AppTheme.primary),
                            )
                          : null,
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: InkWell(
                        onTap: _pickAndUploadAvatar,
                        child: Container(
                          padding: const EdgeInsets.all(6),
                          decoration: const BoxDecoration(
                            color: AppTheme.primary,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.camera_alt, size: 14, color: Colors.white),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 2),
                      Text(email, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      Text(contact, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 6,
                        runSpacing: 4,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: AppTheme.primary.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              'BADGE: $badgeCode',
                              style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 11),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: availColor.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              workAvail.toString().toUpperCase(),
                              style: TextStyle(color: availColor, fontWeight: FontWeight.bold, fontSize: 11),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const Divider(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Duty & Work Status:', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                TextButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const WorkAvailabilityPage()),
                    ).then((_) => _loadProfile());
                  },
                  icon: const Icon(Icons.tune, size: 16),
                  label: const Text('Change Duty Status'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildVehicleCard() {
    final vehicleType = _courierData?['vehicle_type'] ?? 'Motorcycle';
    final plateNumber = _courierData?['plate_number'] ?? 'Unregistered';
    final vehicleCategory = _courierData?['vehicle_category'] ?? 'Standard';
    final vehicleCapacityKg = _courierData?['vehicle_capacity_kg'] ?? 'Unset';
    final isVerified = _courierData?['vehicle_capacity_verified_at'] != null;
    final isHubTransfer = _courierData?['hub_transfer_only'] == true;

    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.two_wheeler, color: AppTheme.primary),
                const SizedBox(width: 8),
                const Text(
                  'Vehicle & Load Rating',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: isVerified ? AppTheme.success.withValues(alpha: 0.12) : Colors.orange.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(isVerified ? Icons.verified : Icons.warning_amber, size: 12, color: isVerified ? AppTheme.success : Colors.orange),
                      const SizedBox(width: 4),
                      Text(
                        isVerified ? 'VERIFIED' : 'UNVERIFIED',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: isVerified ? AppTheme.success : Colors.orange,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _buildInfoRow('Vehicle Type', vehicleType),
            _buildInfoRow('Plate Number', plateNumber),
            _buildInfoRow('Vehicle Category', vehicleCategory),
            _buildInfoRow('Cargo Weight Capacity', vehicleCapacityKg),
            _buildInfoRow(
              'Delivery Scope',
              isHubTransfer ? 'Cross-Hub Transfer Only' : 'Door-to-Door & Hub Delivery',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLogisticsAndServiceCard() {
    final logisticsOwner = _courierData?['logistics_owner'] as Map?;
    final ownerName = logisticsOwner != null ? logisticsOwner['name']?.toString() : 'Independent Courier';
    final ownerContact = logisticsOwner != null ? logisticsOwner['contact_no']?.toString() : null;
    final serviceAreas = _courierData?['service_areas'] as List? ?? [];

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
                Icon(Icons.business, color: AppTheme.primary),
                SizedBox(width: 8),
                Text(
                  'Logistics Operator & Service Areas',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _buildInfoRow('Assigned Logistics Owner', ownerName ?? 'None'),
            if (ownerContact != null) _buildInfoRow('Operator Hotline', ownerContact),
            const SizedBox(height: 12),
            const Text('Covered Service Areas', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            if (serviceAreas.isEmpty)
              const Text('No designated service areas restricted. Flexible coverage.', style: TextStyle(fontSize: 12, color: Colors.grey))
            else
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: serviceAreas.map((area) {
                  final name = area['name'] ?? '${area['municipality']}, ${area['province']}';
                  return Chip(
                    label: Text(name.toString(), style: const TextStyle(fontSize: 11)),
                    backgroundColor: Colors.grey.shade100,
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    padding: EdgeInsets.zero,
                  );
                }).toList(),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildAppearanceCard() {
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
                Icon(Icons.palette_outlined, color: AppTheme.primary),
                SizedBox(width: 8),
                Text(
                  'Profile Appearance & Bio',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const Text('Display Name', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            TextField(
              controller: _displayNameCtrl,
              decoration: InputDecoration(
                hintText: 'e.g. Kuya Alex Delivery',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              ),
            ),
            const SizedBox(height: 12),
            const Text('Bio / Rider Note', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            TextField(
              controller: _bioCtrl,
              maxLines: 2,
              decoration: InputDecoration(
                hintText: 'Short rider bio or greeting',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              ),
            ),
            const SizedBox(height: 14),
            const Text('Color Theme', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              children: [
                _buildThemeChip('rose', 'Rose', const Color(0xFFD63363)),
                _buildThemeChip('indigo', 'Indigo', Colors.indigo),
                _buildThemeChip('emerald', 'Emerald', Colors.teal),
                _buildThemeChip('amber', 'Amber', Colors.amber.shade800),
              ],
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _isSavingAppearance ? null : _saveAppearance,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: _isSavingAppearance
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Save Appearance'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildThemeChip(String id, String label, Color color) {
    final isSelected = _selectedTheme == id;
    return ChoiceChip(
      label: Text(label, style: TextStyle(color: isSelected ? Colors.white : Colors.black87, fontSize: 12)),
      selected: isSelected,
      selectedColor: color,
      backgroundColor: color.withValues(alpha: 0.15),
      onSelected: (val) {
        if (val) setState(() => _selectedTheme = id);
      },
    );
  }

  Widget _buildSecurityCard() {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: ListTile(
        leading: const CircleAvatar(
          backgroundColor: Colors.blueGrey,
          child: Icon(Icons.lock_outline, color: Colors.white),
        ),
        title: const Text('Security & Password', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
        subtitle: const Text('Update your login password and credentials'),
        trailing: const Icon(Icons.arrow_forward_ios, size: 14),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const ChangePasswordPage()),
          );
        },
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
          Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
