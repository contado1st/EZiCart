import '../utils/safe_convert.dart';

class AddressModel {
  final int id;
  final String label;
  final String recipientName;
  final String recipientContact;
  final String region;
  final String province;
  final String municipality;
  final String barangay;
  final String? streetAddress;
  final String? houseNumber;
  final String? postalCode;
  final bool isDefault;

  AddressModel({
    required this.id,
    required this.label,
    required this.recipientName,
    required this.recipientContact,
    required this.region,
    required this.province,
    required this.municipality,
    required this.barangay,
    this.streetAddress,
    this.houseNumber,
    this.postalCode,
    this.isDefault = false,
  });

  factory AddressModel.fromJson(Map<String, dynamic> json) {
    return AddressModel(
      id: parseInt(json['id']),
      label: json['label']?.toString() ?? 'Home',
      recipientName: json['recipient_name']?.toString() ?? '',
      recipientContact: json['recipient_contact']?.toString() ?? '',
      region: json['region']?.toString() ?? '',
      province: json['province']?.toString() ?? '',
      municipality: json['municipality']?.toString() ?? '',
      barangay: json['barangay']?.toString() ?? '',
      streetAddress: json['street_address']?.toString(),
      houseNumber: json['house_number']?.toString(),
      postalCode: json['postal_code']?.toString(),
      isDefault: json['is_default'] == true || json['is_default'] == 1 || json['is_default'] == '1',
    );
  }

  String get fullAddress {
    final parts = [
      houseNumber,
      streetAddress,
      barangay,
      municipality,
      province,
    ].where((p) => p != null && p.trim().isNotEmpty).toList();
    return parts.join(', ');
  }

  Map<String, dynamic> toJson() => {
    'label': label,
    'recipient_name': recipientName,
    'recipient_contact': recipientContact,
    'region': region,
    'province': province,
    'municipality': municipality,
    'barangay': barangay,
    'street_address': streetAddress,
    'house_number': houseNumber,
    'postal_code': postalCode,
    'is_default': isDefault,
  };
}

