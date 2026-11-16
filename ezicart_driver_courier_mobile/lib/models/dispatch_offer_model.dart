import '../utils/safe_convert.dart';

class DispatchOfferModel {
  final int id;
  final int? cycleId;
  final int orderId;
  final String? orderNumber;
  final String? orderStatus;
  final String status;
  final bool isActive;
  final bool isExpired;
  final int weightGrams;
  final String weightKg;
  final int payoutCents;
  final String payoutPesos;
  final String? expiresAt;
  final String? responseReason;
  final String? hubName;
  final String? sellerName;
  final String? sellerAddress;
  final String? deliveryArea;
  final String createdAt;

  DispatchOfferModel({
    required this.id,
    this.cycleId,
    required this.orderId,
    this.orderNumber,
    this.orderStatus,
    required this.status,
    required this.isActive,
    required this.isExpired,
    required this.weightGrams,
    required this.weightKg,
    required this.payoutCents,
    required this.payoutPesos,
    this.expiresAt,
    this.responseReason,
    this.hubName,
    this.sellerName,
    this.sellerAddress,
    this.deliveryArea,
    required this.createdAt,
  });

  factory DispatchOfferModel.fromJson(Map<String, dynamic> json) {
    return DispatchOfferModel(
      id: parseInt(json['id']),
      cycleId: parseNullableInt(json['cycle_id']),
      orderId: parseInt(json['order_id']),
      orderNumber: json['order_number']?.toString(),
      orderStatus: json['order_status']?.toString(),
      status: json['status']?.toString() ?? 'PENDING',
      isActive: json['is_active'] == true,
      isExpired: json['is_expired'] == true,
      weightGrams: parseInt(json['weight_grams']),
      weightKg: json['weight_kg']?.toString() ?? '${(parseInt(json['weight_grams']) / 1000).toStringAsFixed(2)} kg',
      payoutCents: parseInt(json['payout_cents']),
      payoutPesos: json['payout_pesos']?.toString() ?? (parseInt(json['payout_cents']) / 100).toStringAsFixed(2),
      expiresAt: json['expires_at']?.toString(),
      responseReason: json['response_reason']?.toString(),
      hubName: json['hub_name']?.toString(),
      sellerName: json['seller_name']?.toString(),
      sellerAddress: json['seller_address']?.toString(),
      deliveryArea: json['delivery_area']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class DispatchLocationPointModel {
  final double latitude;
  final double longitude;
  final double accuracyMeters;
  final String? capturedAt;

  DispatchLocationPointModel({
    required this.latitude,
    required this.longitude,
    required this.accuracyMeters,
    this.capturedAt,
  });

  factory DispatchLocationPointModel.fromJson(Map<String, dynamic> json) {
    return DispatchLocationPointModel(
      latitude: parseDouble(json['latitude']),
      longitude: parseDouble(json['longitude']),
      accuracyMeters: parseDouble(json['accuracy_meters']),
      capturedAt: json['captured_at']?.toString(),
    );
  }
}
