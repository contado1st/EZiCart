import '../utils/safe_convert.dart';

class ReturnPickupPartyModel {
  final int? id;
  final String name;
  final String? contactNo;
  final String address;
  final String? municipality;
  final String? barangay;

  ReturnPickupPartyModel({
    this.id,
    required this.name,
    this.contactNo,
    required this.address,
    this.municipality,
    this.barangay,
  });

  factory ReturnPickupPartyModel.fromJson(Map<String, dynamic> json) {
    return ReturnPickupPartyModel(
      id: parseNullableInt(json['id']),
      name: json['name']?.toString() ?? 'Contact',
      contactNo: json['contact_no']?.toString(),
      address: json['address']?.toString() ?? '',
      municipality: json['municipality']?.toString(),
      barangay: json['barangay']?.toString(),
    );
  }
}

class ReturnPickupModel {
  final int id;
  final String? orderNumber;
  final String? parcelCode;
  final String status;
  final String operationalStatus;
  final double totalAmount;
  final ReturnPickupPartyModel seller;
  final ReturnPickupPartyModel buyer;
  final String? disputeStatus;
  final String? disputeReason;
  final String? disputeResolution;
  final bool isAccepted;
  final String? acceptedAt;
  final bool isHandedOver;
  final bool canAccept;
  final bool canCollect;
  final bool canReturnHandover;

  ReturnPickupModel({
    required this.id,
    this.orderNumber,
    this.parcelCode,
    required this.status,
    required this.operationalStatus,
    required this.totalAmount,
    required this.seller,
    required this.buyer,
    this.disputeStatus,
    this.disputeReason,
    this.disputeResolution,
    required this.isAccepted,
    this.acceptedAt,
    required this.isHandedOver,
    required this.canAccept,
    required this.canCollect,
    required this.canReturnHandover,
  });

  factory ReturnPickupModel.fromJson(Map<String, dynamic> json) {
    return ReturnPickupModel(
      id: parseInt(json['id']),
      orderNumber: json['order_number'] as String?,
      parcelCode: json['parcel_code'] as String?,
      status: json['status']?.toString() ?? 'DELIVERED',
      operationalStatus: json['operational_status']?.toString() ?? 'Return Pending',
      totalAmount: parseDouble(json['total_amount']),
      seller: ReturnPickupPartyModel.fromJson(json['seller'] as Map<String, dynamic>? ?? {}),
      buyer: ReturnPickupPartyModel.fromJson(json['buyer'] as Map<String, dynamic>? ?? {}),
      disputeStatus: json['dispute_status']?.toString(),
      disputeReason: json['dispute_reason']?.toString(),
      disputeResolution: json['dispute_resolution']?.toString(),
      isAccepted: json['is_accepted'] == true,
      acceptedAt: json['accepted_at']?.toString(),
      isHandedOver: json['is_handed_over'] == true,
      canAccept: json['can_accept'] == true,
      canCollect: json['can_collect'] == true,
      canReturnHandover: json['can_return_handover'] == true,
    );
  }
}
