import '../utils/safe_convert.dart';

class OrderItemModel {
  final int id;
  final int? productId;
  final String productName;
  final int quantity;
  final double unitPrice;

  OrderItemModel({
    required this.id,
    this.productId,
    required this.productName,
    required this.quantity,
    required this.unitPrice,
  });

  factory OrderItemModel.fromJson(Map<String, dynamic> json) {
    return OrderItemModel(
      id: parseInt(json['id']),
      productId: parseNullableInt(json['product_id']),
      productName: json['product_name']?.toString() ?? 'Item',
      quantity: parseInt(json['quantity'], 1),
      unitPrice: parseDouble(json['unit_price']),
    );
  }
}

class TrackingEventModel {
  final int id;
  final String eventType;
  final String eventLabel;
  final String status;
  final String? location;
  final String? notes;
  final String createdAt;
  final String? actorName;

  TrackingEventModel({
    required this.id,
    required this.eventType,
    required this.eventLabel,
    required this.status,
    this.location,
    this.notes,
    required this.createdAt,
    this.actorName,
  });

  factory TrackingEventModel.fromJson(Map<String, dynamic> json) {
    return TrackingEventModel(
      id: parseInt(json['id']),
      eventType: json['event_type']?.toString() ?? '',
      eventLabel: json['event_label']?.toString() ?? (json['event_type']?.toString().replaceAll('_', ' ') ?? ''),
      status: json['status']?.toString() ?? '',
      location: json['location']?.toString(),
      notes: json['notes']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
      actorName: json['actor_name']?.toString(),
    );
  }
}

class DeliveryAttemptModel {
  final int id;
  final int attemptNo;
  final String outcome;
  final String? reason;
  final String? notes;
  final String? attemptedAt;

  DeliveryAttemptModel({
    required this.id,
    required this.attemptNo,
    required this.outcome,
    this.reason,
    this.notes,
    this.attemptedAt,
  });

  factory DeliveryAttemptModel.fromJson(Map<String, dynamic> json) {
    return DeliveryAttemptModel(
      id: parseInt(json['id']),
      attemptNo: parseInt(json['attempt_no'], 1),
      outcome: json['outcome']?.toString() ?? '',
      reason: json['reason']?.toString(),
      notes: json['notes']?.toString(),
      attemptedAt: json['attempted_at']?.toString(),
    );
  }
}

class OrderModel {
  final int id;
  final String orderNumber;
  final String status;
  final String statusLabel;
  final String paymentMethod;
  final double totalAmount;
  final String? notes;
  final String? parcelCode;
  final int? weightGrams;

  final String recipientName;
  final String? recipientContact;
  final String? deliveryArea;
  final String deliveryAddress;

  final String? pickupClaimedAt;
  final String? pickupArrivedAt;
  final String? sellerHandoverAt;
  final String? pickedUpAt;
  final String? hubReleasedAt;
  final String? assignedAt;
  final String? outForDeliveryAt;
  final String? deliveredAt;
  final String? failedAt;
  final String? deliveryFailureReason;
  final String? deliveryNotes;
  final double? codCollectedAmount;
  final String? returnHandedToSellerAt;

  final String sellerName;
  final String? sellerContact;
  final String sellerAddress;
  final String? buyerName;
  final String? buyerContact;

  final List<OrderItemModel> items;
  final List<TrackingEventModel> trackingEvents;
  final List<DeliveryAttemptModel> deliveryAttempts;

  OrderModel({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.statusLabel,
    required this.paymentMethod,
    required this.totalAmount,
    this.notes,
    this.parcelCode,
    this.weightGrams,
    required this.recipientName,
    this.recipientContact,
    this.deliveryArea,
    required this.deliveryAddress,
    this.pickupClaimedAt,
    this.pickupArrivedAt,
    this.sellerHandoverAt,
    this.pickedUpAt,
    this.hubReleasedAt,
    this.assignedAt,
    this.outForDeliveryAt,
    this.deliveredAt,
    this.failedAt,
    this.deliveryFailureReason,
    this.deliveryNotes,
    this.codCollectedAmount,
    this.returnHandedToSellerAt,
    required this.sellerName,
    this.sellerContact,
    required this.sellerAddress,
    this.buyerName,
    this.buyerContact,
    this.items = const [],
    this.trackingEvents = const [],
    this.deliveryAttempts = const [],
  });

  bool get isCod => paymentMethod.toUpperCase() == 'COD';

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    final sellerMap = json['seller'] is Map ? json['seller'] as Map : {};
    final buyerMap = json['buyer'] is Map ? json['buyer'] as Map : {};

    var itemList = <OrderItemModel>[];
    if (json['items'] is List) {
      itemList = (json['items'] as List)
          .map((i) => OrderItemModel.fromJson(i as Map<String, dynamic>))
          .toList();
    }

    var eventList = <TrackingEventModel>[];
    if (json['tracking_events'] is List) {
      eventList = (json['tracking_events'] as List)
          .map((e) => TrackingEventModel.fromJson(e as Map<String, dynamic>))
          .toList();
    }

    var attemptList = <DeliveryAttemptModel>[];
    if (json['delivery_attempts'] is List) {
      attemptList = (json['delivery_attempts'] as List)
          .map((a) => DeliveryAttemptModel.fromJson(a as Map<String, dynamic>))
          .toList();
    }

    return OrderModel(
      id: parseInt(json['id']),
      orderNumber: json['order_number']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      statusLabel: json['status_label']?.toString() ?? (json['status']?.toString().replaceAll('_', ' ') ?? ''),
      paymentMethod: json['payment_method']?.toString() ?? 'COD',
      totalAmount: parseDouble(json['total_amount']),
      notes: json['notes']?.toString(),
      parcelCode: json['parcel_code']?.toString(),
      weightGrams: parseNullableInt(json['weight_grams']),
      recipientName: json['recipient_name']?.toString() ?? 'Recipient',
      recipientContact: json['recipient_contact']?.toString(),
      deliveryArea: json['delivery_area']?.toString(),
      deliveryAddress: json['delivery_address']?.toString() ?? '',
      pickupClaimedAt: json['pickup_claimed_at']?.toString(),
      pickupArrivedAt: json['pickup_arrived_at']?.toString(),
      sellerHandoverAt: json['seller_handover_at']?.toString(),
      pickedUpAt: json['picked_up_at']?.toString(),
      hubReleasedAt: json['hub_released_at']?.toString(),
      assignedAt: json['assigned_at']?.toString(),
      outForDeliveryAt: json['out_for_delivery_at']?.toString(),
      deliveredAt: json['delivered_at']?.toString(),
      failedAt: json['failed_at']?.toString(),
      deliveryFailureReason: json['delivery_failure_reason']?.toString(),
      deliveryNotes: json['delivery_notes']?.toString(),
      codCollectedAmount: parseNullableDouble(json['cod_collected_amount']),
      returnHandedToSellerAt: json['return_handed_to_seller_at']?.toString(),
      sellerName: sellerMap['name']?.toString() ?? 'Seller',
      sellerContact: sellerMap['contact_no']?.toString(),
      sellerAddress: sellerMap['address']?.toString() ?? '',
      buyerName: buyerMap['name']?.toString(),
      buyerContact: buyerMap['contact_no']?.toString(),
      items: itemList,
      trackingEvents: eventList,
      deliveryAttempts: attemptList,
    );
  }
}
