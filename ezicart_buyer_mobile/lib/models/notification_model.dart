import '../utils/safe_convert.dart';

class NotificationModel {
  final String id;
  final String category;
  final String title;
  final String message;
  final bool isRead;
  final String? readAt;
  final String createdAt;
  final int? orderId;
  final int? productId;
  final int? conversationId;
  final Map<String, dynamic> data;

  NotificationModel({
    required this.id,
    required this.category,
    required this.title,
    required this.message,
    required this.isRead,
    this.readAt,
    required this.createdAt,
    this.orderId,
    this.productId,
    this.conversationId,
    this.data = const {},
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    final rawData = json['data'] is Map<String, dynamic>
        ? json['data'] as Map<String, dynamic>
        : <String, dynamic>{};

    return NotificationModel(
      id: json['id']?.toString() ?? '',
      category: json['category']?.toString() ?? 'account',
      title: json['title']?.toString() ?? 'Notification',
      message: json['message']?.toString() ?? '',
      isRead: json['is_read'] == true,
      readAt: json['read_at'] as String?,
      createdAt: json['created_at']?.toString() ?? '',
      orderId: parseNullableInt(json['order_id'] ?? rawData['order_id']),
      productId: parseNullableInt(json['product_id'] ?? rawData['product_id']),
      conversationId: parseNullableInt(json['conversation_id'] ?? rawData['conversation_id']),
      data: rawData,
    );
  }

  NotificationModel copyWith({bool? isRead}) {
    return NotificationModel(
      id: id,
      category: category,
      title: title,
      message: message,
      isRead: isRead ?? this.isRead,
      readAt: readAt,
      createdAt: createdAt,
      orderId: orderId,
      productId: productId,
      conversationId: conversationId,
      data: data,
    );
  }
}

