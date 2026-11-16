import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/chat_model.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';

class ChatConversationPage extends StatefulWidget {
  final int conversationId;
  final String partnerName;
  final String? partnerRole;
  final bool? isPartnerOnline;
  final int? orderId;
  final String? orderNumber;

  const ChatConversationPage({
    super.key,
    required this.conversationId,
    required this.partnerName,
    this.partnerRole,
    this.isPartnerOnline,
    this.orderId,
    this.orderNumber,
  });

  @override
  State<ChatConversationPage> createState() => _ChatConversationPageState();
}

class _ChatConversationPageState extends State<ChatConversationPage> {
  final _messageCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  Timer? _pollingTimer;

  bool _isLoading = true;
  bool _isSending = false;
  String? _errorMessage;
  List<ChatMessageModel> _messages = [];
  int? _currentUserId;

  @override
  void initState() {
    super.initState();
    _currentUserId = context.read<AuthProvider>().user?.id;
    _loadMessages();

    // Real-time synchronization polling every 4 seconds
    _pollingTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (mounted && !_isSending) {
        _silentPoll();
      }
    });
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    _messageCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadMessages() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiService.get(ApiConfig.chatConversationEndpoint(widget.conversationId));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final list = data['messages'] as List? ?? [];
        final userId = _currentUserId ?? 0;
        _messages = list.map((m) => ChatMessageModel.fromJson(m as Map<String, dynamic>, userId)).toList();
        _scrollToBottom();
      } else {
        _errorMessage = 'Failed to load conversation (${res.statusCode})';
      }
    } catch (e) {
      _errorMessage = 'Error loading messages: $e';
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _silentPoll() async {
    try {
      final res = await ApiService.get(ApiConfig.chatConversationEndpoint(widget.conversationId));
      if (res.statusCode == 200 && mounted) {
        final data = jsonDecode(res.body);
        final list = data['messages'] as List? ?? [];
        final userId = _currentUserId ?? 0;
        final updated = list.map((m) => ChatMessageModel.fromJson(m as Map<String, dynamic>, userId)).toList();
        if (updated.length != _messages.length || (updated.isNotEmpty && updated.last.id != (_messages.isNotEmpty ? _messages.last.id : null))) {
          setState(() => _messages = updated);
          _scrollToBottom();
        }
      }
    } catch (_) {}
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _sendMessage() async {
    final text = _messageCtrl.text.trim();
    if (text.isEmpty || _isSending) return;

    _messageCtrl.clear();
    setState(() => _isSending = true);

    try {
      final body = <String, dynamic>{
        'body': text,
      };
      if (widget.orderId != null) {
        body['order_id'] = widget.orderId;
      }

      final res = await ApiService.post(
        ApiConfig.chatSendMessageEndpoint(widget.conversationId),
        body,
      );

      if (res.statusCode == 200 || res.statusCode == 201) {
        final data = jsonDecode(res.body);
        if (data['message'] is Map) {
          final userId = _currentUserId ?? 0;
          final sentMsg = ChatMessageModel.fromJson(data['message'] as Map<String, dynamic>, userId);
          setState(() {
            _messages.add(sentMsg);
          });
          _scrollToBottom();
        } else {
          _silentPoll();
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Failed to deliver message.')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _pickAndSendImage(ImageSource source) async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: source, maxWidth: 1200, imageQuality: 85);
    if (picked == null) return;

    setState(() => _isSending = true);
    try {
      final fields = <String, String>{};
      if (widget.orderId != null) {
        fields['order_id'] = widget.orderId.toString();
      }

      final res = await ApiService.multipartPost(
        url: ApiConfig.chatSendMessageEndpoint(widget.conversationId),
        fields: fields,
        fileField: 'attachment',
        filePath: picked.path,
      );

      if (res.statusCode == 200 || res.statusCode == 201) {
        _silentPoll();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Failed to send image attachment.')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error sending photo: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _unsendMessage(ChatMessageModel msg) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Unsend Message?'),
        content: const Text('This will remove the message for both you and the recipient.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: AppTheme.danger),
            child: const Text('Unsend'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      final res = await ApiService.delete(
        ApiConfig.chatUnsendMessageEndpoint(widget.conversationId, msg.id),
      );

      if (res.statusCode == 200) {
        _silentPoll();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Could not unsend message.')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: AppTheme.primary.withValues(alpha: 0.2),
              child: Text(
                widget.partnerName.isNotEmpty ? widget.partnerName[0].toUpperCase() : 'C',
                style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.partnerName,
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                    overflow: TextOverflow.ellipsis,
                  ),
                  Row(
                    children: [
                      if (widget.isPartnerOnline == true) ...[
                        Container(
                          width: 8,
                          height: 8,
                          decoration: const BoxDecoration(
                            color: AppTheme.success,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 4),
                        const Text('Online', style: TextStyle(fontSize: 11, color: AppTheme.success)),
                        const SizedBox(width: 6),
                      ],
                      if (widget.partnerRole != null && widget.partnerRole!.isNotEmpty)
                        Text(
                          widget.partnerRole!.toUpperCase(),
                          style: TextStyle(fontSize: 10, color: Colors.grey.shade600, fontWeight: FontWeight.w600),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          // Order link header if applicable
          if (widget.orderNumber != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              color: AppTheme.primary.withValues(alpha: 0.08),
              child: Row(
                children: [
                  const Icon(Icons.local_shipping_outlined, size: 16, color: AppTheme.primary),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Regarding Order #${widget.orderNumber}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.primary),
                    ),
                  ),
                ],
              ),
            ),

          // Messages list
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
                : _errorMessage != null
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(_errorMessage!),
                            const SizedBox(height: 8),
                            ElevatedButton(onPressed: _loadMessages, child: const Text('Retry')),
                          ],
                        ),
                      )
                    : _messages.isEmpty
                        ? const Center(
                            child: Text(
                              'No messages yet. Send a greeting to start the conversation!',
                              style: TextStyle(color: Colors.grey, fontSize: 13),
                            ),
                          )
                        : ListView.builder(
                            controller: _scrollCtrl,
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            itemCount: _messages.length,
                            itemBuilder: (ctx, i) {
                              final msg = _messages[i];
                              return _buildMessageBubble(msg);
                            },
                          ),
          ),

          // Input field row
          _buildInputBar(),
        ],
      ),
    );
  }

  Widget _buildMessageBubble(ChatMessageModel msg) {
    final isMine = msg.isMine;

    if (msg.isUnsent) {
      return Align(
        alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 4),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: Colors.grey.shade200,
            borderRadius: BorderRadius.circular(8),
          ),
          child: const Text(
            'This message was unsent',
            style: TextStyle(fontSize: 11, fontStyle: FontStyle.italic, color: Colors.grey),
          ),
        ),
      );
    }

    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: GestureDetector(
        onLongPress: isMine ? () => _unsendMessage(msg) : null,
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 4),
          constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.76),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: isMine ? AppTheme.primary : Colors.grey.shade100,
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(16),
              topRight: const Radius.circular(16),
              bottomLeft: Radius.circular(isMine ? 16 : 4),
              bottomRight: Radius.circular(isMine ? 4 : 16),
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 4,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              // Attachments if any
              if (msg.attachments.isNotEmpty) ...[
                ...msg.attachments.map((att) {
                  final imgUrl = ApiConfig.resolveImageUrl(att.url) ?? att.url;
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: Image.network(
                        imgUrl,
                        height: 160,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Container(
                          height: 60,
                          color: Colors.grey.shade300,
                          alignment: Alignment.center,
                          child: const Icon(Icons.broken_image, color: Colors.grey),
                        ),
                      ),
                    ),
                  );
                }),
              ],

              // Body text
              if (msg.body != null && msg.body!.isNotEmpty)
                Text(
                  msg.body!,
                  style: TextStyle(
                    color: isMine ? Colors.white : Colors.black87,
                    fontSize: 14,
                  ),
                ),

              const SizedBox(height: 4),

              // Timestamp & status
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    msg.createdAt,
                    style: TextStyle(
                      fontSize: 10,
                      color: isMine ? Colors.white70 : Colors.grey.shade600,
                    ),
                  ),
                  if (isMine) ...[
                    const SizedBox(width: 4),
                    Icon(
                      msg.readAt != null ? Icons.done_all : Icons.done,
                      size: 13,
                      color: msg.readAt != null ? Colors.lightBlueAccent : Colors.white70,
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildInputBar() {
    return Container(
      padding: EdgeInsets.only(
        left: 8,
        right: 8,
        top: 8,
        bottom: MediaQuery.of(context).padding.bottom + 8,
      ),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 6,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.camera_alt_outlined, color: AppTheme.primary),
            onPressed: _isSending ? null : () => _pickAndSendImage(ImageSource.camera),
          ),
          IconButton(
            icon: const Icon(Icons.photo_outlined, color: AppTheme.primary),
            onPressed: _isSending ? null : () => _pickAndSendImage(ImageSource.gallery),
          ),
          Expanded(
            child: TextField(
              controller: _messageCtrl,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                hintText: 'Type a message...',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide(color: Colors.grey.shade300),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              ),
              onSubmitted: (_) => _sendMessage(),
            ),
          ),
          const SizedBox(width: 6),
          CircleAvatar(
            backgroundColor: AppTheme.primary,
            radius: 20,
            child: _isSending
                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : IconButton(
                    icon: const Icon(Icons.send, size: 18, color: Colors.white),
                    onPressed: _sendMessage,
                  ),
          ),
        ],
      ),
    );
  }
}
