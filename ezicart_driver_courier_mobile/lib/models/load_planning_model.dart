import '../utils/safe_convert.dart';
import 'order_model.dart';

class LoadPlanningSummaryModel {
  final int count;
  final int grams;
  final String kgFormatted;
  final int? capacityGrams;
  final String capacityKgFormatted;
  final int? remainingGrams;
  final String? remainingKgFormatted;
  final bool overweight;
  final int unknownWeightCount;
  final int offerCount;
  final int offerGrams;
  final bool hubTransferOnly;
  final String? vehicleType;
  final String? plateNumber;

  LoadPlanningSummaryModel({
    required this.count,
    required this.grams,
    required this.kgFormatted,
    this.capacityGrams,
    required this.capacityKgFormatted,
    this.remainingGrams,
    this.remainingKgFormatted,
    required this.overweight,
    required this.unknownWeightCount,
    this.offerCount = 0,
    this.offerGrams = 0,
    this.hubTransferOnly = false,
    this.vehicleType,
    this.plateNumber,
  });

  double get utilizationPercentage {
    if (capacityGrams == null || capacityGrams! <= 0) return 0.0;
    final pct = (grams / capacityGrams!) * 100;
    return pct > 100.0 ? 100.0 : pct;
  }

  factory LoadPlanningSummaryModel.fromJson(Map<String, dynamic> json) {
    return LoadPlanningSummaryModel(
      count: parseInt(json['count']),
      grams: parseInt(json['grams']),
      kgFormatted: json['kg_formatted']?.toString() ?? '${(parseInt(json['grams']) / 1000).toStringAsFixed(2)} kg',
      capacityGrams: parseNullableInt(json['capacity_grams']),
      capacityKgFormatted: json['capacity_kg_formatted']?.toString() ?? 'Unset',
      remainingGrams: parseNullableInt(json['remaining_grams']),
      remainingKgFormatted: json['remaining_kg_formatted']?.toString(),
      overweight: json['overweight'] == true,
      unknownWeightCount: parseInt(json['unknown_weight_count'], 0),
      offerCount: parseInt(json['offer_count'], 0),
      offerGrams: parseInt(json['offer_grams'], 0),
      hubTransferOnly: json['hub_transfer_only'] == true,
      vehicleType: json['vehicle_type']?.toString(),
      plateNumber: json['plate_number']?.toString(),
    );
  }
}

class LoadPlanningModel {
  final LoadPlanningSummaryModel summary;
  final List<OrderModel> orders;

  LoadPlanningModel({
    required this.summary,
    required this.orders,
  });

  factory LoadPlanningModel.fromJson(Map<String, dynamic> json) {
    final rawOrders = json['orders'] as List<dynamic>? ?? [];
    return LoadPlanningModel(
      summary: LoadPlanningSummaryModel.fromJson(json['summary'] as Map<String, dynamic>? ?? {}),
      orders: rawOrders.map((o) => OrderModel.fromJson(o as Map<String, dynamic>)).toList(),
    );
  }
}
