class VoucherModel {
  final int id;
  final String code;
  final String type;
  final double value;
  final double minSpend;
  final double? maxDiscount;
  final DateTime? expiresAt;
  final bool isClaimed;
  final bool isAvailable;
  final int? sellerId;
  final String sellerName;
  final String description;

  const VoucherModel({
    required this.id,
    required this.code,
    required this.type,
    required this.value,
    required this.minSpend,
    this.maxDiscount,
    this.expiresAt,
    required this.isClaimed,
    required this.isAvailable,
    this.sellerId,
    required this.sellerName,
    required this.description,
  });

  bool get isPlatformVoucher => sellerId == null;

  factory VoucherModel.fromJson(Map<String, dynamic> json) {
    return VoucherModel(
      id: (json['id'] is num) ? (json['id'] as num).toInt() : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      code: json['code']?.toString() ?? '',
      type: json['type']?.toString() ?? 'fixed',
      value: (json['value'] is num) ? (json['value'] as num).toDouble() : double.tryParse(json['value']?.toString() ?? '') ?? 0.0,
      minSpend: (json['min_spend'] is num) ? (json['min_spend'] as num).toDouble() : double.tryParse(json['min_spend']?.toString() ?? '') ?? 0.0,
      maxDiscount: (json['max_discount'] is num) ? (json['max_discount'] as num).toDouble() : double.tryParse(json['max_discount']?.toString() ?? ''),
      expiresAt: json['expires_at'] != null ? DateTime.tryParse(json['expires_at'].toString()) : null,
      isClaimed: json['is_claimed'] == true,
      isAvailable: json['is_available'] != false,
      sellerId: (json['seller_id'] is num) ? (json['seller_id'] as num).toInt() : int.tryParse(json['seller_id']?.toString() ?? ''),
      sellerName: json['seller_name']?.toString() ?? 'EZiCart Platform',
      description: json['description']?.toString() ?? '',
    );
  }
}

