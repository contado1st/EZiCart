import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/chat_model.dart';
import '../../services/api_service.dart';

class MessagesPage extends StatefulWidget {
  const MessagesPage({super.key});

  @override
  State<MessagesPage> createState() => MessagesPageState();
}

class MessagesPageState extends State<MessagesPage> {
  List<ChatThreadModel> _threads = [];
  bool _isLoading = true;
  String _searchQuery = '';
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _fetchThreads();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  void fetchThreads() {
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
          if (mounted) {
            setState(() {
              _threads = list;
              _isLoading = false;
            });
            return;
          }
        }
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  String _formatThreadDate(String raw) {
    if (raw.isEmpty) return '';
    try {
      final dt = DateTime.parse(raw).toLocal();
      final now = DateTime.now();
      final diff = now.difference(dt);
      if (diff.inDays == 0 && dt.day == now.day) {
        return DateFormat('h:mm a').format(dt);
      } else if (diff.inDays <= 1 || (now.day - dt.day == 1 && diff.inHours < 48)) {
        return 'Yesterday';
      } else if (diff.inDays < 7) {
        return DateFormat('EEE').format(dt);
      } else {
        return DateFormat('MMM d').format(dt);
      }
    } catch (_) {
      return raw;
    }
  }

  Color _getRoleColor(String role) {
    switch (role.toLowerCase()) {
      case 'seller':
        return AppTheme.primary;
      case 'courier':
      case 'rider':
        return const Color(0xFFD97706); // Amber
      case 'admin':
      case 'support':
        return const Color(0xFF2563EB); // Blue
      default:
        return const Color(0xFF64748B);
    }
  }

  List<ChatThreadModel> get _filteredThreads {
    if (_searchQuery.trim().isEmpty) return _threads;
    final q = _searchQuery.trim().toLowerCase();
    return _threads.where((t) {
      return t.partnerName.toLowerCase().contains(q) ||
          t.lastMessage.toLowerCase().contains(q) ||
          t.partnerRole.toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(
        title: const Text(
          'Messages',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: AppTheme.ink),
            tooltip: 'Refresh',
            onPressed: () {
              setState(() => _isLoading = true);
              _fetchThreads();
            },
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Search Bar Header
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 12),
              child: Container(
                height: 42,
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: TextField(
                  controller: _searchCtrl,
                  onChanged: (val) => setState(() => _searchQuery = val),
                  decoration: InputDecoration(
                    hintText: 'Search chats, merchants, riders...',
                    hintStyle: const TextStyle(fontSize: 13, color: AppTheme.muted),
                    prefixIcon: const Icon(Icons.search, size: 20, color: AppTheme.muted),
                    suffixIcon: _searchQuery.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, size: 16, color: AppTheme.muted),
                            onPressed: () {
                              _searchCtrl.clear();
                              setState(() => _searchQuery = '');
                            },
                          )
                        : null,
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  ),
                ),
              ),
            ),
            const Divider(height: 1, color: Color(0xFFEAECEF)),

            // Content
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _threads.isEmpty
                      ? RefreshIndicator(
                          onRefresh: _fetchThreads,
                          child: SingleChildScrollView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            child: SizedBox(
                              height: MediaQuery.of(context).size.height * 0.65,
                              child: Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(32),
                                  child: Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.all(22),
                                        decoration: BoxDecoration(
                                          color: Colors.white,
                                          shape: BoxShape.circle,
                                          boxShadow: [
                                            BoxShadow(
                                              color: Colors.black.withValues(alpha: 0.04),
                                              blurRadius: 14,
                                              offset: const Offset(0, 4),
                                            ),
                                          ],
                                        ),
                                        child: const Icon(Icons.chat_bubble_outline_rounded, size: 48, color: AppTheme.primary),
                                      ),
                                      const SizedBox(height: 16),
                                      const Text(
                                        'No conversations yet',
                                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.ink),
                                      ),
                                      const SizedBox(height: 6),
                                      const Text(
                                        'When you message sellers or delivery riders, conversations will be organized here.',
                                        textAlign: TextAlign.center,
                                        style: TextStyle(color: AppTheme.muted, fontSize: 13, height: 1.35),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ),
                        )
                      : _filteredThreads.isEmpty
                          ? Center(
                              child: Text(
                                'No conversations matching "$_searchQuery"',
                                style: const TextStyle(color: AppTheme.muted, fontSize: 13),
                              ),
                            )
                          : RefreshIndicator(
                              onRefresh: _fetchThreads,
                              child: ListView.separated(
                                physics: const AlwaysScrollableScrollPhysics(),
                                padding: const EdgeInsets.symmetric(vertical: 8),
                                itemCount: _filteredThreads.length,
                                separatorBuilder: (_, __) => const Divider(
                                  height: 1,
                                  indent: 74,
                                  color: Color(0xFFF1F3F6),
                                ),
                                itemBuilder: (context, i) {
                                  final t = _filteredThreads[i];
                                  final hasUnread = t.unreadCount > 0;
                                  final roleColor = _getRoleColor(t.partnerRole);

                                  return Material(
                                    color: hasUnread ? const Color(0xFFFFFBFB) : Colors.white,
                                    child: InkWell(
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
                                      child: Padding(
                                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                                        child: Row(
                                          children: [
                                            // Avatar with gradient
                                            Container(
                                              width: 48,
                                              height: 48,
                                              decoration: BoxDecoration(
                                                gradient: LinearGradient(
                                                  colors: [
                                                    roleColor.withValues(alpha: 0.15),
                                                    roleColor.withValues(alpha: 0.25),
                                                  ],
                                                  begin: Alignment.topLeft,
                                                  end: Alignment.bottomRight,
                                                ),
                                                shape: BoxShape.circle,
                                              ),
                                              child: Center(
                                                child: Text(
                                                  t.partnerName.isNotEmpty ? t.partnerName[0].toUpperCase() : 'S',
                                                  style: TextStyle(
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 18,
                                                    color: roleColor,
                                                  ),
                                                ),
                                              ),
                                            ),
                                            const SizedBox(width: 12),

                                            // Details
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Row(
                                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                    children: [
                                                      Expanded(
                                                        child: Row(
                                                          children: [
                                                            Flexible(
                                                              child: Text(
                                                                t.partnerName,
                                                                maxLines: 1,
                                                                overflow: TextOverflow.ellipsis,
                                                                style: TextStyle(
                                                                  fontWeight: hasUnread ? FontWeight.bold : FontWeight.w600,
                                                                  fontSize: 14.5,
                                                                  color: AppTheme.ink,
                                                                ),
                                                              ),
                                                            ),
                                                            const SizedBox(width: 6),
                                                            Container(
                                                              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                                                              decoration: BoxDecoration(
                                                                color: roleColor.withValues(alpha: 0.1),
                                                                borderRadius: BorderRadius.circular(4),
                                                              ),
                                                              child: Text(
                                                                t.partnerRole.toUpperCase(),
                                                                style: TextStyle(
                                                                  fontSize: 9,
                                                                  fontWeight: FontWeight.bold,
                                                                  color: roleColor,
                                                                  letterSpacing: 0.3,
                                                                ),
                                                              ),
                                                            ),
                                                          ],
                                                        ),
                                                      ),
                                                      const SizedBox(width: 8),
                                                      Text(
                                                        _formatThreadDate(t.lastMessageAt),
                                                        style: TextStyle(
                                                          fontSize: 11,
                                                          color: hasUnread ? AppTheme.primary : AppTheme.muted,
                                                          fontWeight: hasUnread ? FontWeight.bold : FontWeight.normal,
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                  const SizedBox(height: 4),
                                                  Row(
                                                    children: [
                                                      Expanded(
                                                        child: Text(
                                                          t.lastMessage.isNotEmpty ? t.lastMessage : 'Tap to start conversation',
                                                          maxLines: 1,
                                                          overflow: TextOverflow.ellipsis,
                                                          style: TextStyle(
                                                            color: hasUnread ? AppTheme.ink : const Color(0xFF64748B),
                                                            fontWeight: hasUnread ? FontWeight.w600 : FontWeight.normal,
                                                            fontSize: 13,
                                                          ),
                                                        ),
                                                      ),
                                                      if (hasUnread) ...[
                                                        const SizedBox(width: 8),
                                                        Container(
                                                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                                          decoration: BoxDecoration(
                                                            color: AppTheme.primary,
                                                            borderRadius: BorderRadius.circular(10),
                                                          ),
                                                          child: Text(
                                                            '${t.unreadCount}',
                                                            style: const TextStyle(
                                                              color: Colors.white,
                                                              fontSize: 10.5,
                                                              fontWeight: FontWeight.bold,
                                                            ),
                                                          ),
                                                        ),
                                                      ],
                                                    ],
                                                  ),
                                                ],
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  );
                                },
                              ),
                            ),
            ),
          ],
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
          if (mounted) {
            setState(() {
              _messages = list;
              _isLoading = false;
            });
            _scrollToBottom();
            return;
          }
        }
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.jumpTo(_scrollCtrl.position.maxScrollExtent);
      }
    });
  }

  String _formatMsgTime(String raw) {
    if (raw.isEmpty) return '';
    try {
      final dt = DateTime.parse(raw).toLocal();
      return DateFormat('h:mm a').format(dt);
    } catch (_) {
      return '';
    }
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
        if (mounted) {
          setState(() {
            _messages.add(newMsg);
            _isSending = false;
          });
          _scrollToBottom();
        }
      } else {
        if (mounted) {
          _msgCtrl.text = text;
          setState(() => _isSending = false);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Failed to send message. Please retry.')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        _msgCtrl.text = text;
        setState(() => _isSending = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not send message: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        titleSpacing: 0,
        title: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: const BoxDecoration(
                color: AppTheme.primarySoft,
                shape: BoxShape.circle,
              ),
              child: Center(
                child: Text(
                  widget.partnerName.isNotEmpty ? widget.partnerName[0].toUpperCase() : 'S',
                  style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary, fontSize: 15),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.partnerName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.ink),
                  ),
                  Text(
                    widget.orderId != null ? 'Ref: Order #${widget.orderId}' : 'EZiCart Merchant',
                    style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Chat message stream
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _messages.isEmpty
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(Icons.waving_hand_outlined, size: 40, color: Color(0xFFE26A7A)),
                                const SizedBox(height: 10),
                                Text(
                                  'Say hello to ${widget.partnerName}!',
                                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppTheme.ink),
                                ),
                                const SizedBox(height: 4),
                                const Text(
                                  'Ask questions about products, availability, or orders.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(fontSize: 12, color: AppTheme.muted),
                                ),
                              ],
                            ),
                          ),
                        )
                      : ListView.builder(
                          controller: _scrollCtrl,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          itemCount: _messages.length,
                          itemBuilder: (context, i) {
                            final m = _messages[i];
                            final timeStr = _formatMsgTime(m.createdAt);

                            return Align(
                              alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
                              child: Container(
                                margin: const EdgeInsets.symmetric(vertical: 4),
                                constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.76),
                                padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
                                decoration: BoxDecoration(
                                  gradient: m.isMe
                                      ? const LinearGradient(
                                          colors: [Color(0xFFD63363), Color(0xFFBE2854)],
                                          begin: Alignment.topLeft,
                                          end: Alignment.bottomRight,
                                        )
                                      : null,
                                  color: m.isMe ? null : Colors.white,
                                  borderRadius: BorderRadius.only(
                                    topLeft: const Radius.circular(16),
                                    topRight: const Radius.circular(16),
                                    bottomLeft: Radius.circular(m.isMe ? 16 : 4),
                                    bottomRight: Radius.circular(m.isMe ? 4 : 16),
                                  ),
                                  border: m.isMe ? null : Border.all(color: const Color(0xFFE2E8F0)),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withValues(alpha: 0.03),
                                      blurRadius: 4,
                                      offset: const Offset(0, 1),
                                    ),
                                  ],
                                ),
                                child: Column(
                                  crossAxisAlignment: m.isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      m.body,
                                      style: TextStyle(
                                        color: m.isMe ? Colors.white : AppTheme.ink,
                                        fontSize: 14,
                                        height: 1.3,
                                      ),
                                    ),
                                    if (timeStr.isNotEmpty) ...[
                                      const SizedBox(height: 3),
                                      Text(
                                        timeStr,
                                        style: TextStyle(
                                          color: m.isMe ? Colors.white.withValues(alpha: 0.75) : const Color(0xFF94A3B8),
                                          fontSize: 9.5,
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
            ),

            // Modern capsule input dock
            Container(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: Color(0xFFEAECEF))),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: TextField(
                        controller: _msgCtrl,
                        maxLength: 1000,
                        maxLines: 3,
                        minLines: 1,
                        textInputAction: TextInputAction.send,
                        onSubmitted: (_) => _send(),
                        decoration: const InputDecoration(
                          hintText: 'Type your message...',
                          hintStyle: TextStyle(fontSize: 13, color: AppTheme.muted),
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          counterText: '',
                          contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  InkWell(
                    onTap: _isSending ? null : _send,
                    borderRadius: BorderRadius.circular(22),
                    child: Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFFD63363), Color(0xFFBE2854)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFFBE2854).withValues(alpha: 0.3),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Center(
                        child: _isSending
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
                      ),
                    ),
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
