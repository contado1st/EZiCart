import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import 'addresses_page.dart';
import 'change_password_page.dart';
import 'settings_page.dart';
import 'work_availability_page.dart';

class RiderProfilePage extends StatefulWidget {
  final bool isEmbedded;
  const RiderProfilePage({super.key, this.isEmbedded = false});

  @override
  State<RiderProfilePage> createState() => _RiderProfilePageState();
}

class _RiderProfilePageState extends State<RiderProfilePage> {
  bool _isLoading = true;
  String? _errorMessage;
  Map<String, dynamic>? _courierData;

  @override
  void initState() {
    super.initState();
    _loadProfile();
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
    final picked = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 800,
      imageQuality: 85,
    );
    if (picked == null || !mounted) return;

    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('Uploading avatar...')));

    try {
      final res = await ApiService.multipartPost(
        url: ApiConfig.updateAppearanceEndpoint,
        fields: {},
        fileField: 'avatar',
        filePath: picked.path,
      );

      final body = jsonDecode(res.body);
      if (res.statusCode == 200 &&
          (body['success'] == true || body['status'] == 'success')) {
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
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: AppTheme.danger,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: widget.isEmbedded
          ? null
          : AppBar(
              title: const Text('My Rider Profile'),
              actions: [
                IconButton(
                  icon: const Icon(Icons.settings_outlined),
                  tooltip: 'Account Settings',
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const CourierSettingsPage(),
                      ),
                    ).then((_) => _loadProfile());
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: _loadProfile,
                ),
              ],
            ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppTheme.primary),
            )
          : _errorMessage != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      size: 48,
                      color: AppTheme.danger,
                    ),
                    const SizedBox(height: 12),
                    Text(_errorMessage!, textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: _loadProfile,
                      child: const Text('Retry'),
                    ),
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
                    _buildAddressCard(),
                    const SizedBox(height: 16),
                    _buildVehicleCard(),
                    const SizedBox(height: 16),
                    _buildLogisticsAndServiceCard(),
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
                      backgroundImage: avatarUrl != null
                          ? NetworkImage(avatarUrl)
                          : null,
                      child: avatarUrl == null
                          ? Text(
                              name.isNotEmpty ? name[0].toUpperCase() : 'C',
                              style: const TextStyle(
                                fontSize: 28,
                                fontWeight: FontWeight.bold,
                                color: AppTheme.primary,
                              ),
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
                          child: const Icon(
                            Icons.camera_alt,
                            size: 14,
                            color: Colors.white,
                          ),
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
                      Text(
                        name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        email,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade600,
                        ),
                      ),
                      Text(
                        contact,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade600,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 6,
                        runSpacing: 4,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: AppTheme.primary.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              'BADGE: $badgeCode',
                              style: const TextStyle(
                                color: AppTheme.primary,
                                fontWeight: FontWeight.bold,
                                fontSize: 11,
                              ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: availColor.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              workAvail.toString().toUpperCase(),
                              style: TextStyle(
                                color: availColor,
                                fontWeight: FontWeight.bold,
                                fontSize: 11,
                              ),
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
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.primary,
                      side: const BorderSide(color: AppTheme.primary),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 9,
                      ),
                    ),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const CourierSettingsPage(),
                        ),
                      ).then((_) => _loadProfile());
                    },
                    icon: const Icon(Icons.edit_note, size: 18),
                    label: const Text(
                      'Edit Profile & Contact Details',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Expanded(
                  child: Text(
                    'Duty & Work Status:',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                TextButton.icon(
                  style: TextButton.styleFrom(
                    visualDensity: VisualDensity.compact,
                    padding: const EdgeInsets.symmetric(horizontal: 6),
                  ),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const WorkAvailabilityPage(),
                      ),
                    ).then((_) => _loadProfile());
                  },
                  icon: const Icon(Icons.tune, size: 16),
                  label: const Text('Change Status'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAddressCard() {
    final address = _courierData?['address'] as Map?;
    final addressText =
        [
              address?['street_address'],
              address?['barangay'],
              address?['municipality'],
              address?['province'],
            ]
            .where((part) => part != null && part.toString().trim().isNotEmpty)
            .join(', ');

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
                const Icon(Icons.location_on_outlined, color: AppTheme.primary),
                const SizedBox(width: 8),
                const Expanded(
                  child: Text(
                    'Address & Location Book',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const AddressesPage()),
                    ).then((_) => _loadProfile());
                  },
                  child: const Text(
                    'Manage',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              addressText.isNotEmpty
                  ? addressText
                  : 'Official address recorded with logistics administration.',
              style: TextStyle(
                fontSize: 13,
                color: addressText.isNotEmpty ? AppTheme.ink : AppTheme.muted,
              ),
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
                const Expanded(
                  child: Text(
                    'Vehicle & Load Rating',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: isVerified
                        ? AppTheme.success.withValues(alpha: 0.12)
                        : Colors.orange.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        isVerified ? Icons.verified : Icons.warning_amber,
                        size: 12,
                        color: isVerified ? AppTheme.success : Colors.orange,
                      ),
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
              isHubTransfer
                  ? 'Cross-Hub Transfer Only'
                  : 'Door-to-Door & Hub Delivery',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLogisticsAndServiceCard() {
    final logisticsOwner = _courierData?['logistics_owner'] as Map?;
    final ownerName = logisticsOwner != null
        ? logisticsOwner['name']?.toString()
        : 'Independent Courier';
    final ownerContact = logisticsOwner != null
        ? logisticsOwner['contact_no']?.toString()
        : null;
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
                Expanded(
                  child: Text(
                    'Logistics Operator & Service Areas',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _buildInfoRow('Assigned Logistics Owner', ownerName ?? 'None'),
            if (ownerContact != null)
              _buildInfoRow('Operator Hotline', ownerContact),
            const SizedBox(height: 12),
            const Text(
              'Covered Service Areas',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 6),
            if (serviceAreas.isEmpty)
              const Text(
                'No designated service areas restricted. Flexible coverage.',
                style: TextStyle(fontSize: 12, color: Colors.grey),
              )
            else
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: serviceAreas.map((area) {
                  final name =
                      area['name'] ??
                      '${area['municipality']}, ${area['province']}';
                  return Chip(
                    label: Text(
                      name.toString(),
                      style: const TextStyle(fontSize: 11),
                    ),
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

  Widget _buildSecurityCard() {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(
        children: [
          ListTile(
            leading: const CircleAvatar(
              backgroundColor: AppTheme.primary,
              child: Icon(Icons.settings_outlined, color: Colors.white, size: 20),
            ),
            title: const Text(
              'Account Settings & Details',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            subtitle: const Text('Edit legal name, contact number, vehicle & base address'),
            trailing: const Icon(Icons.arrow_forward_ios, size: 14),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const CourierSettingsPage()),
              ).then((_) => _loadProfile());
            },
          ),
          const Divider(height: 1),
          ListTile(
            leading: const CircleAvatar(
              backgroundColor: Colors.blueGrey,
              child: Icon(Icons.lock_outline, color: Colors.white, size: 20),
            ),
            title: const Text(
              'Security & Password',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            subtitle: const Text('Update your login password and credentials'),
            trailing: const Icon(Icons.arrow_forward_ios, size: 14),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ChangePasswordPage()),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
