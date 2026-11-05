import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/chat_model.dart';
import '../../services/api_service.dart';

class MessagesPage extends StatefulWidget {
  const MessagesPage({super.key});

  @override
  State<MessagesPage> createState() => _MessagesPageState();
}

class _MessagesPageState extends State<MessagesPage> {
  List<ChatThreadModel> _threads = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchThreads();
  }

  Future<void> _fetchThreads() async {
    try {
      final res = await ApiService.get(ApiConfig.chatThreadsEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((t) => ChatThreadModel.fromJson(t as Map<String, dynamic>))
              .toList();
          setState(() {
            _threads = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_threads.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Messages')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.chat_bubble_outline, size: 56, color: Colors.grey.shade400),
              const SizedBox(height: 12),
              const Text(
                'No message conversations yet',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 6),
              const Text(
                'Messages from sellers or order inquiries will appear here.',
                style: TextStyle(color: AppTheme.muted, fontSize: 13),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
      ),
      body: RefreshIndicator(
        onRefresh: _fetchThreads,
        child: ListView.separated(
          padding: const EdgeInsets.symmetric(vertical: 8),
          itemCount: _threads.length,
          separatorBuilder: (_, __) => const Divider(height: 1, indent: 72),
          itemBuilder: (context, i) {
            final t = _threads[i];
            return ListTile(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ChatConversationScreen(
                      conversationId: t.id,
                      partnerName: t.partnerName,
                    ),
                  ),
                ).then((_) => _fetchThreads());
              },
              leading: CircleAvatar(
                backgroundColor: AppTheme.primarySoft,
                child: Text(
                  t.partnerName.isNotEmpty ? t.partnerName[0].toUpperCase() : 'S',
                  style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary),
                ),
              ),
              title: Row(
                children: [
                  Expanded(
                    child: Text(
                      t.partnerName,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                    ),
                  ),
                  Text(
                    t.partnerRole.toUpperCase(),
                    style: const TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              subtitle: Text(
                t.lastMessage.isNotEmpty ? t.lastMessage : 'Tap to start conversation',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: t.unreadCount > 0 ? AppTheme.ink : AppTheme.muted,
                  fontWeight: t.unreadCount > 0 ? FontWeight.bold : FontWeight.normal,
                  fontSize: 13,
                ),
              ),
              trailing: t.unreadCount > 0
                  ? Container(
                      padding: const EdgeInsets.all(6),
                      decoration: const BoxDecoration(
                        color: AppTheme.primary,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        '${t.unreadCount}',
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    )
                  : const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
            );
          },
        ),
      ),
    );
  }
}

class ChatConversationScreen extends StatefulWidget {
  final int conversationId;
  final String partnerName;
  final int? orderId;

  const ChatConversationScreen({
    super.key,
    required this.conversationId,
    required this.partnerName,
    this.orderId,
  });

  @override
  State<ChatConversationScreen> createState() => _ChatConversationScreenState();
}

class _ChatConversationScreenState extends State<ChatConversationScreen> {
  final _msgCtrl = TextEditingController();
  final ScrollController _scrollCtrl = ScrollController();
  List<ChatMessageModel> _messages = [];
  bool _isLoading = true;
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _loadMessages();
  }

  @override
  void dispose() {
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadMessages() async {
    try {
      final res = await ApiService.get(ApiConfig.chatConversationEndpoint(widget.conversationId));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final rawMessages = body['data']?['messages'];
        if (rawMessages is List) {
          final list = rawMessages
              .map((m) => ChatMessageModel.fromJson(m as Map<String, dynamic>))
              .toList();
          setState(() {
            _messages = list;
            _isLoading = false;
          });
          _scrollToBottom();
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.jumpTo(_scrollCtrl.position.maxScrollExtent);
      }
    });
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _isSending) return;

    setState(() => _isSending = true);
    _msgCtrl.clear();

    try {
      final res = await ApiService.post(
        ApiConfig.chatSendMessageEndpoint(widget.conversationId),
        {
          'body': text,
          if (widget.orderId != null) 'order_id': widget.orderId,
        },
      );

      if (res.statusCode == 201) {
        final body = jsonDecode(res.body);
        final newMsg = ChatMessageModel.fromJson(body['data']);
        setState(() {
          _messages.add(newMsg);
          _isSending = false;
        });
        _scrollToBottom();
      } else {
        setState(() => _isSending = false);
      }
    } catch (_) {
      setState(() => _isSending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.partnerName, style: const TextStyle(fontSize: 16)),
            if (widget.orderId != null)
              Text('Ref: Order #${widget.orderId}', style: const TextStyle(fontSize: 11, color: AppTheme.muted)),
          ],
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _messages.isEmpty
                      ? const Center(
                          child: Text(
                            'Say hello to start the conversation!',
                            style: TextStyle(color: AppTheme.muted),
                          ),
                        )
                      : ListView.builder(
                          controller: _scrollCtrl,
                          padding: const EdgeInsets.all(16),
                          itemCount: _messages.length,
                          itemBuilder: (context, i) {
                            final m = _messages[i];
                            return Align(
                              alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
                              child: Container(
                                margin: const EdgeInsets.symmetric(vertical: 4),
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
                                decoration: BoxDecoration(
                                  color: m.isMe ? AppTheme.primary : AppTheme.surface,
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(
                                    color: m.isMe ? AppTheme.primary : AppTheme.line,
                                  ),
                                ),
                                child: Text(
                                  m.body,
                                  style: TextStyle(
                                    color: m.isMe ? Colors.white : AppTheme.ink,
                                    fontSize: 14,
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: const BoxDecoration(
                color: AppTheme.surface,
                border: Border(top: BorderSide(color: AppTheme.line)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _msgCtrl,
                      decoration: const InputDecoration(
                        hintText: 'Type your message...',
                        border: InputBorder.none,
                        enabledBorder: InputBorder.none,
                        focusedBorder: InputBorder.none,
                        contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      ),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  IconButton(
                    icon: _isSending
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.send, color: AppTheme.primary),
                    onPressed: _send,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

