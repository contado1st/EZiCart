import '../utils/safe_convert.dart';

class UserModel {
  final int id;
  final String firstName;
  final String? middleName;
  final String? middleInitial;
  final String lastName;
  final String? suffix;
  final String name;
  final String email;
  final String? contactNo;
  final String role;
  final String status;
  final String? badgeCode;
  final String? vehicleType;
  final String? plateNumber;
  final String? vehicleCategory;
  final String? vehicleDescription;
  final String? licenseNumber;
  final String? licenseExpiresAt;
  final int? vehicleCapacityGrams;
  final bool hubTransferOnly;
  final String? municipality;
  final String? province;
  final String? region;
  final String? barangay;
  final String? streetAddress;
  final String? houseNumber;
  final String? postalCode;
  final String workAvailability;
  final int workAvailabilityVersion;
  final String? workAvailabilityChangedAt;
  final int? logisticsOwnerId;
  final String? profileDisplayName;
  final String? profileBio;
  final String? profileTheme;
  final String? avatarUrl;
  final bool hasAvatar;

  UserModel({
    required this.id,
    required this.firstName,
    this.middleName,
    this.middleInitial,
    required this.lastName,
    this.suffix,
    required this.name,
    required this.email,
    this.contactNo,
    required this.role,
    required this.status,
    this.badgeCode,
    this.vehicleType,
    this.plateNumber,
    this.vehicleCategory,
    this.vehicleDescription,
    this.licenseNumber,
    this.licenseExpiresAt,
    this.vehicleCapacityGrams,
    this.hubTransferOnly = false,
    this.municipality,
    this.province,
    this.region,
    this.barangay,
    this.streetAddress,
    this.houseNumber,
    this.postalCode,
    this.workAvailability = 'available',
    this.workAvailabilityVersion = 0,
    this.workAvailabilityChangedAt,
    this.logisticsOwnerId,
    this.profileDisplayName,
    this.profileBio,
    this.profileTheme,
    this.avatarUrl,
    this.hasAvatar = false,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final first = json['first_name']?.toString() ?? '';
    final last = json['last_name']?.toString() ?? '';
    final computedName = json['name']?.toString() ?? '$first $last'.trim();

    return UserModel(
      id: parseInt(json['id']),
      firstName: first,
      middleName: json['middle_name']?.toString(),
      middleInitial: json['middle_initial']?.toString(),
      lastName: last,
      suffix: json['suffix']?.toString(),
      name: computedName.isNotEmpty ? computedName : 'Courier',
      email: json['email']?.toString() ?? '',
      contactNo: json['contact_no']?.toString(),
      role: json['role']?.toString() ?? 'courier',
      status: json['status']?.toString() ?? 'approved',
      badgeCode: json['badge_code']?.toString(),
      vehicleType: json['vehicle_type']?.toString(),
      plateNumber: json['plate_number']?.toString(),
      vehicleCategory: json['vehicle_category']?.toString(),
      vehicleDescription: json['vehicle_description']?.toString(),
      licenseNumber: json['license_number']?.toString(),
      licenseExpiresAt: json['license_expires_at']?.toString(),
      vehicleCapacityGrams: parseNullableInt(json['vehicle_capacity_grams']),
      hubTransferOnly: json['hub_transfer_only'] == true || json['hub_transfer_only'] == 1,
      municipality: json['municipality']?.toString(),
      province: json['province']?.toString(),
      region: json['region']?.toString(),
      barangay: json['barangay']?.toString(),
      streetAddress: json['street_address']?.toString(),
      houseNumber: json['house_number']?.toString(),
      postalCode: json['postal_code']?.toString(),
      workAvailability: json['work_availability']?.toString() ?? 'available',
      workAvailabilityVersion: parseInt(json['work_availability_version'], 0),
      workAvailabilityChangedAt: json['work_availability_changed_at']?.toString(),
      logisticsOwnerId: parseNullableInt(json['logistics_owner_id']),
      profileDisplayName: json['profile_display_name']?.toString(),
      profileBio: json['profile_bio']?.toString(),
      profileTheme: json['profile_theme']?.toString(),
      avatarUrl: json['avatar_url']?.toString(),
      hasAvatar: json['has_avatar'] == true,
    );
  }

  String get fullName => name;
  bool get isApproved => status.toLowerCase() == 'approved';
  bool get isAvailable => workAvailability.toLowerCase() == 'available';

  String get vehicleCapacityKgFormatted {
    if (vehicleCapacityGrams == null || vehicleCapacityGrams! <= 0) return 'Not recorded';
    return '${(vehicleCapacityGrams! / 1000).toStringAsFixed(2)} kg';
  }
}
