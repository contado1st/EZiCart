import '../utils/safe_convert.dart';

class AllocationModel {
  final int id;
  final String orderNumber;
  final String component;
  final double amountPesos;
  final String? createdAt;

  AllocationModel({
    required this.id,
    required this.orderNumber,
    required this.component,
    required this.amountPesos,
    this.createdAt,
  });

  factory AllocationModel.fromJson(Map<String, dynamic> json) {
    final cents = parseInt(json['amount_cents']);
    return AllocationModel(
      id: parseInt(json['id']),
      orderNumber: json['order_number']?.toString() ?? '',
      component: json['component']?.toString().replaceAll('_', ' ') ?? '',
      amountPesos: cents > 0 ? (cents / 100.0) : parseDouble(json['amount_pesos']),
      createdAt: json['created_at']?.toString(),
    );
  }
}
