import '../utils/safe_convert.dart';

class ChatAttachmentModel {
  final int id;
  final String name;
  final String mimeType;
  final int size;
  final String url;
  final String? previewUrl;
  final String? downloadUrl;
  final bool isImage;
  final bool isUnsent;

  ChatAttachmentModel({
    required this.id,
    required this.name,
    required this.mimeType,
    required this.size,
    required this.url,
    this.previewUrl,
    this.downloadUrl,
    this.isImage = false,
    this.isUnsent = false,
  });

  factory ChatAttachmentModel.fromJson(Map<String, dynamic> json) {
    return ChatAttachmentModel(
      id: parseInt(json['id']),
      name: json['name']?.toString() ?? 'attachment',
      mimeType: json['mime_type']?.toString() ?? 'application/octet-stream',
      size: parseInt(json['size']),
      url: json['url']?.toString() ?? '',
      previewUrl: json['preview_url']?.toString(),
      downloadUrl: json['download_url']?.toString(),
      isImage: json['is_image'] == true,
      isUnsent: json['is_unsent'] == true,
    );
  }

  String get formattedSize {
    if (size < 1024) return '$size B';
    if (size < 1024 * 1024) return '${(size / 1024).toStringAsFixed(1)} KB';
    return '${(size / (1024 * 1024)).toStringAsFixed(1)} MB';
  }
}

class ChatThreadModel {
  final int id;
  final int partnerId;
  final String partnerName;
  final String partnerRole;
  final String? avatarUrl;
  final bool isOnline;
  final String? lastActiveAt;
  final String? lastActiveText;
  final String? shopStatus;
  final String lastMessage;
  final String lastMessageAt;
  final int unreadCount;

  ChatThreadModel({
    required this.id,
    required this.partnerId,
    required this.partnerName,
    required this.partnerRole,
    this.avatarUrl,
    this.isOnline = false,
    this.lastActiveAt,
    this.lastActiveText,
    this.shopStatus,
    required this.lastMessage,
    required this.lastMessageAt,
    this.unreadCount = 0,
  });

  factory ChatThreadModel.fromJson(Map<String, dynamic> json) {
    final partner = json['partner'] is Map ? json['partner'] as Map : {};
    return ChatThreadModel(
      id: parseInt(json['id']),
      partnerId: parseInt(partner['id']),
      partnerName: partner['name']?.toString() ?? 'Contact',
      partnerRole: partner['role']?.toString() ?? '',
      avatarUrl: partner['avatar_url']?.toString(),
      isOnline: partner['is_online'] == true,
      lastActiveAt: partner['last_active_at']?.toString(),
      lastActiveText: partner['last_active_text']?.toString(),
      shopStatus: partner['shop_status']?.toString(),
      lastMessage: json['last_message']?.toString() ?? '',
      lastMessageAt: json['last_message_at']?.toString() ?? '',
      unreadCount: parseInt(json['unread_count'], 0),
    );
  }
}

class ChatMessageModel {
  final int id;
  final int conversationId;
  final int senderId;
  final int recipientId;
  final String? body;
  final int? orderId;
  final String? orderNumber;
  final int? productId;
  final String? productName;
  final String? unsentAt;
  final String? readAt;
  final String createdAt;
  final bool isMine;
  final List<ChatAttachmentModel> attachments;

  ChatMessageModel({
    required this.id,
    required this.conversationId,
    required this.senderId,
    required this.recipientId,
    this.body,
    this.orderId,
    this.orderNumber,
    this.productId,
    this.productName,
    this.unsentAt,
    this.readAt,
    required this.createdAt,
    required this.isMine,
    this.attachments = const [],
  });

  bool get isUnsent => unsentAt != null;

  factory ChatMessageModel.fromJson(Map<String, dynamic> json, int currentUserId) {
    final attRaw = json['attachments'] as List<dynamic>? ?? [];
    final senderId = parseInt(json['sender_id']);

    return ChatMessageModel(
      id: parseInt(json['id']),
      conversationId: parseInt(json['conversation_id']),
      senderId: senderId,
      recipientId: parseInt(json['recipient_id']),
      body: json['body']?.toString(),
      orderId: parseNullableInt(json['order_id']),
      orderNumber: json['order_number']?.toString(),
      productId: parseNullableInt(json['product_id']),
      productName: json['product_name']?.toString(),
      unsentAt: json['unsent_at']?.toString(),
      readAt: json['read_at']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
      isMine: senderId == currentUserId,
      attachments: attRaw.map((a) => ChatAttachmentModel.fromJson(a as Map<String, dynamic>)).toList(),
    );
  }
}
