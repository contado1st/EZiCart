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
    final partner = json['partner'] as Map<String, dynamic>?;
    return ChatThreadModel(
      id: parseInt(json['id']),
      partnerId: parseInt(partner?['id']),
      partnerName: partner?['name']?.toString() ?? 'Seller',
      partnerRole: partner?['role']?.toString() ?? 'seller',
      avatarUrl: partner?['avatar_url']?.toString(),
      isOnline: partner?['is_online'] == true,
      lastActiveAt: partner?['last_active_at']?.toString(),
      lastActiveText: partner?['last_active_text']?.toString(),
      shopStatus: partner?['shop_status']?.toString(),
      lastMessage: json['last_message']?.toString() ?? '',
      lastMessageAt: json['last_message_at']?.toString() ?? '',
      unreadCount: parseInt(json['unread_count']),
    );
  }
}

class ChatMessageModel {
  final int id;
  final int senderId;
  final bool isMe;
  final bool isUnsent;
  final String body;
  final String createdAt;
  final Map<String, dynamic>? productContext;
  final List<ChatAttachmentModel> attachments;

  ChatMessageModel({
    required this.id,
    required this.senderId,
    required this.isMe,
    this.isUnsent = false,
    required this.body,
    required this.createdAt,
    this.productContext,
    this.attachments = const [],
  });

  factory ChatMessageModel.fromJson(Map<String, dynamic> json) {
    List<ChatAttachmentModel> atts = [];
    if (json['attachments'] is List) {
      atts = (json['attachments'] as List)
          .map((a) => ChatAttachmentModel.fromJson(a as Map<String, dynamic>))
          .toList();
    }

    return ChatMessageModel(
      id: parseInt(json['id']),
      senderId: parseInt(json['sender_id']),
      isMe: json['is_me'] == true,
      isUnsent: json['is_unsent'] == true,
      body: json['body']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
      productContext: json['product_context'] as Map<String, dynamic>?,
      attachments: atts,
    );
  }
}
