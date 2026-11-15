import '../utils/safe_convert.dart';
import 'dispute_model.dart';

class OrderItemModel {
  final int id;
  final int? productId;
  final String name;
  final String? imagePath;
  final int quantity;
  final double price;
  final double total;
  final String? variationInfo;

  OrderItemModel({
    required this.id,
    this.productId,
    required this.name,
    this.imagePath,
    required this.quantity,
    required this.price,
    required this.total,
    this.variationInfo,
  });

  factory OrderItemModel.fromJson(Map<String, dynamic> json) {
    final qty = parseInt(json['quantity'], 1);
    final prc = parseDouble(json['price'] ?? json['unit_price']);
    return OrderItemModel(
      id: parseInt(json['id']),
      productId: parseNullableInt(json['product_id']),
      name: json['name']?.toString() ?? json['product_name']?.toString() ?? json['product']?['name']?.toString() ?? 'Item',
      imagePath: json['image_path'] as String? ?? json['purchased_image_path'] as String? ?? json['product']?['image_path'] as String?,
      quantity: qty,
      price: prc,
      total: parseDouble(json['total'] ?? json['item_total'], prc * qty),
      variationInfo: json['variation_info'] as String?,
    );
  }
}

class OrderTrackingTimelineItem {
  final String status;
  final String description;
  final String? location;
  final String timestamp;

  OrderTrackingTimelineItem({
    required this.status,
    required this.description,
    this.location,
    required this.timestamp,
  });

  factory OrderTrackingTimelineItem.fromJson(Map<String, dynamic> json) {
    return OrderTrackingTimelineItem(
      status: json['status']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      location: json['location'] as String?,
      timestamp: json['timestamp']?.toString() ?? '',
    );
  }
}

class OrderModel {
  final int id;
  final String? orderNumber;
  final String? parcelCode;
  final String status;
  final String statusLabel;
  final String createdAt;
  final double totalAmount;
  final double subtotal;
  final double shippingFee;
  final double discount;
  final String paymentMethod;
  final bool isAwaitingWalletPayment;
  final DateTime? walletPaymentDueAt;
  final int? walletPaidCents;
  final int? walletTransactionId;
  final String? deliveryCode;
  final String? deliveryQrPayload;
  final String? recipientName;
  final String? recipientContact;
  final String? deliveryAddress;
  final String? notes;
  final String sellerName;
  final int? sellerId;
  final String? courierName;
  final String? courierContact;
  final String? courierPlate;
  final List<OrderItemModel> items;
  final List<OrderTrackingTimelineItem> trackingTimeline;
  final DisputeModel? dispute;
  final String? incidentStatus;
  final bool fulfillmentOnHold;
  final Map<String, dynamic>? activeIncident;
  final Map<String, dynamic>? orderProgress;
  final Map<String, dynamic>? returnPickup;
  final bool hasPendingCancellation;

  OrderModel({
    required this.id,
    this.orderNumber,
    this.parcelCode,
    required this.status,
    required this.statusLabel,
    required this.createdAt,
    required this.totalAmount,
    this.subtotal = 0.0,
    this.shippingFee = 0.0,
    this.discount = 0.0,
    this.paymentMethod = 'COD',
    this.isAwaitingWalletPayment = false,
    this.walletPaymentDueAt,
    this.walletPaidCents,
    this.walletTransactionId,
    this.deliveryCode,
    this.deliveryQrPayload,
    this.recipientName,
    this.recipientContact,
    this.deliveryAddress,
    this.notes,
    required this.sellerName,
    this.sellerId,
    this.courierName,
    this.courierContact,
    this.courierPlate,
    required this.items,
    this.trackingTimeline = const [],
    this.dispute,
    this.incidentStatus,
    this.fulfillmentOnHold = false,
    this.activeIncident,
    this.orderProgress,
    this.returnPickup,
    this.hasPendingCancellation = false,
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    var rawItems = json['items'] as List<dynamic>? ?? [];
    var itemsList = rawItems.map((e) => OrderItemModel.fromJson(e as Map<String, dynamic>)).toList();

    var rawTimeline = json['tracking_timeline'] as List<dynamic>? ?? [];
    var timelineList = rawTimeline.map((e) => OrderTrackingTimelineItem.fromJson(e as Map<String, dynamic>)).toList();

    double computedTotal = parseDouble(json['total_amount'], 
        json['total_cents'] != null ? parseDouble(json['total_cents']) / 100 : 0.0);

    if (computedTotal == 0.0 && itemsList.isNotEmpty) {
      computedTotal = itemsList.fold(0.0, (sum, i) => sum + i.total);
    }

    final rawStatus = json['status']?.toString() ?? 'PLACED';

    return OrderModel(
      id: parseInt(json['id']),
      orderNumber: json['order_number'] as String?,
      parcelCode: json['parcel_code'] as String?,
      status: rawStatus,
      statusLabel: json['status_label']?.toString() ?? rawStatus.replaceAll('_', ' '),
      createdAt: json['created_at']?.toString() ?? '',
      totalAmount: computedTotal,
      subtotal: parseDouble(json['subtotal'],
          json['subtotal_cents'] != null ? parseDouble(json['subtotal_cents']) / 100 : computedTotal),
      shippingFee: parseDouble(json['shipping_fee'],
          json['shipping_cents'] != null ? parseDouble(json['shipping_cents']) / 100 : 0.0),
      discount: parseDouble(json['discount'],
          json['discount_cents'] != null ? parseDouble(json['discount_cents']) / 100 : 0.0),
      paymentMethod: json['payment_method']?.toString() ?? 'COD',
      isAwaitingWalletPayment: json['is_awaiting_wallet_payment'] == true ||
          (json['payment_method'] == 'WALLET' &&
              rawStatus == 'PLACED' &&
              json['wallet_payment_due_at'] != null &&
              json['wallet_transaction_id'] == null),
      walletPaymentDueAt: json['wallet_payment_due_at'] != null
          ? DateTime.tryParse(json['wallet_payment_due_at'].toString())
          : null,
      walletPaidCents: parseNullableInt(json['wallet_paid_cents']),
      walletTransactionId: parseNullableInt(json['wallet_transaction_id']),
      deliveryCode: json['delivery_code'] as String?,
      deliveryQrPayload: json['delivery_qr_payload'] as String?,
      recipientName: json['recipient_name'] as String?,
      recipientContact: json['recipient_contact'] as String?,
      deliveryAddress: json['delivery_address'] as String?,
      notes: json['notes'] as String?,
      sellerName: json['seller']?['name']?.toString() ??
          json['seller']?['business_name']?.toString() ??
          'Merchant',
      sellerId: parseNullableInt(json['seller']?['id']),
      courierName: json['courier']?['name'] as String?,
      courierContact: json['courier']?['contact'] as String?,
      courierPlate: json['courier']?['plate_number'] as String?,
      items: itemsList,
      trackingTimeline: timelineList,
      dispute: json['dispute'] is Map ? DisputeModel.fromJson(json['dispute'] as Map<String, dynamic>) : null,
      incidentStatus: json['incident_status'] as String? ?? json['active_incident']?['kind'] as String?,
      fulfillmentOnHold: json['fulfillment_on_hold'] == true || json['active_incident'] != null,
      activeIncident: json['active_incident'] is Map ? json['active_incident'] as Map<String, dynamic> : null,
      orderProgress: json['order_progress'] is Map ? json['order_progress'] as Map<String, dynamic> : null,
      returnPickup: json['return_pickup'] is Map ? json['return_pickup'] as Map<String, dynamic> : null,
      hasPendingCancellation: json['pending_cancellation'] != null || json['has_pending_cancellation'] == true,
    );
  }

  bool get canPayWithWallet => paymentMethod == 'WALLET' && isAwaitingWalletPayment;
}
