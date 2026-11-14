import '../utils/safe_convert.dart';

class UserModel {
  final int id;
  final String firstName;
  final String? middleName;
  final String lastName;
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
  final int? vehicleCapacityGrams;
  final bool hubTransferOnly;
  final String? municipality;
  final String? province;

  UserModel({
    required this.id,
    required this.firstName,
    this.middleName,
    required this.lastName,
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
    this.vehicleCapacityGrams,
    this.hubTransferOnly = false,
    this.municipality,
    this.province,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final first = json['first_name']?.toString() ?? '';
    final last = json['last_name']?.toString() ?? '';
    final computedName = json['name']?.toString() ?? '$first $last'.trim();

    return UserModel(
      id: parseInt(json['id']),
      firstName: first,
      middleName: json['middle_name']?.toString(),
      lastName: last,
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
      vehicleCapacityGrams: parseNullableInt(json['vehicle_capacity_grams']),
      hubTransferOnly: json['hub_transfer_only'] == true || json['hub_transfer_only'] == 1,
      municipality: json['municipality']?.toString(),
      province: json['province']?.toString(),
    );
  }

  String get fullName => name;
  bool get isApproved => status.toLowerCase() == 'approved';
}
