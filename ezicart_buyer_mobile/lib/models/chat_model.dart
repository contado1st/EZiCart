import '../utils/safe_convert.dart';

class ChatThreadModel {
  final int id;
  final int partnerId;
  final String partnerName;
  final String partnerRole;
  final String lastMessage;
  final String lastMessageAt;
  final int unreadCount;

  ChatThreadModel({
    required this.id,
    required this.partnerId,
    required this.partnerName,
    required this.partnerRole,
    required this.lastMessage,
    required this.lastMessageAt,
    this.unreadCount = 0,
  });

  factory ChatThreadModel.fromJson(Map<String, dynamic> json) {
    return ChatThreadModel(
      id: parseInt(json['id']),
      partnerId: parseInt(json['partner']?['id']),
      partnerName: json['partner']?['name']?.toString() ?? 'Seller',
      partnerRole: json['partner']?['role']?.toString() ?? 'seller',
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
  final String body;
  final String createdAt;

  ChatMessageModel({
    required this.id,
    required this.senderId,
    required this.isMe,
    required this.body,
    required this.createdAt,
  });

  factory ChatMessageModel.fromJson(Map<String, dynamic> json) {
    return ChatMessageModel(
      id: parseInt(json['id']),
      senderId: parseInt(json['sender_id']),
      isMe: json['is_me'] == true,
      body: json['body']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

