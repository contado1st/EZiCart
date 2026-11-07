import '../utils/safe_convert.dart';

class UserModel {
  final int id;
  final String firstName;
  final String? middleName;
  final String lastName;
  final String? suffix;
  final String name;
  final String email;
  final String? contactNo;
  final String? birthday;
  final int? age;
  final String role;
  final String status;
  final String? region;
  final String? province;
  final String? municipality;
  final String? barangay;
  final String? streetAddress;
  final String? houseNumber;
  final String? postalCode;

  UserModel({
    required this.id,
    required this.firstName,
    this.middleName,
    required this.lastName,
    this.suffix,
    required this.name,
    required this.email,
    this.contactNo,
    this.birthday,
    this.age,
    required this.role,
    required this.status,
    this.region,
    this.province,
    this.municipality,
    this.barangay,
    this.streetAddress,
    this.houseNumber,
    this.postalCode,
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
      suffix: json['suffix']?.toString(),
      name: computedName.isNotEmpty ? computedName : 'Buyer',
      email: json['email']?.toString() ?? '',
      contactNo: json['contact_no']?.toString(),
      birthday: json['birthday']?.toString(),
      age: parseNullableInt(json['age']),
      role: json['role']?.toString() ?? 'buyer',
      status: json['status']?.toString() ?? 'approved',
      region: json['region']?.toString(),
      province: json['province']?.toString(),
      municipality: json['municipality']?.toString(),
      barangay: json['barangay']?.toString(),
      streetAddress: json['street_address']?.toString(),
      houseNumber: json['house_number']?.toString(),
      postalCode: json['postal_code']?.toString(),
    );
  }

  String get fullName => name;

  String get fullAddress {
    final parts = [
      houseNumber,
      streetAddress,
      barangay,
      municipality,
      province,
    ].where((p) => p != null && p.trim().isNotEmpty).toList();
    return parts.isEmpty ? 'No address registered' : parts.join(', ');
  }
}
