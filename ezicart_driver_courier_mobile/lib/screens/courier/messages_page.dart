import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/chat_model.dart';
import '../../services/api_service.dart';
import 'chat_conversation_page.dart';

class MessagesPage extends StatefulWidget {
  const MessagesPage({super.key});

  @override
  State<MessagesPage> createState() => _MessagesPageState();
}

class _MessagesPageState extends State<MessagesPage> {
  bool _isLoading = true;
  String? _errorMessage;
  List<ChatThreadModel> _threads = [];
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadThreads();
  }

  Future<void> _loadThreads() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiService.get(ApiConfig.chatThreadsEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final list = data['threads'] as List? ?? [];
        _threads = list.map((t) => ChatThreadModel.fromJson(t as Map<String, dynamic>)).toList();
      } else {
        _errorMessage = 'Failed to load messages (${res.statusCode})';
      }
    } catch (e) {
      _errorMessage = 'Error loading messages: $e';
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  List<ChatThreadModel> get _filteredThreads {
    if (_searchQuery.trim().isEmpty) return _threads;
    final q = _searchQuery.toLowerCase();
    return _threads.where((t) {
      return t.partnerName.toLowerCase().contains(q) ||
          t.lastMessage.toLowerCase().contains(q) ||
          t.partnerRole.toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages & Chats'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadThreads,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.chat_bubble_outline, size: 48, color: Colors.grey),
                        const SizedBox(height: 12),
                        Text(_errorMessage!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        ElevatedButton(onPressed: _loadThreads, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadThreads,
                  child: Column(
                    children: [
                      // Search Bar
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                        child: TextField(
                          decoration: InputDecoration(
                            hintText: 'Search chats by name or message...',
                            prefixIcon: const Icon(Icons.search, size: 20),
                            isDense: true,
                            filled: true,
                            fillColor: Colors.grey.shade100,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide.none,
                            ),
                          ),
                          onChanged: (val) => setState(() => _searchQuery = val),
                        ),
                      ),

                      // Thread List
                      Expanded(
                        child: _filteredThreads.isEmpty
                            ? Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(24),
                                  child: Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(Icons.mark_chat_unread_outlined, size: 48, color: Colors.grey.shade400),
                                      const SizedBox(height: 12),
                                      Text(
                                        _searchQuery.isNotEmpty
                                            ? 'No conversations matching "$_searchQuery".'
                                            : 'No active conversations yet.\nChats with buyers and sorting centers will appear here.',
                                        textAlign: TextAlign.center,
                                        style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                                      ),
                                    ],
                                  ),
                                ),
                              )
                            : ListView.separated(
                                padding: const EdgeInsets.symmetric(vertical: 8),
                                itemCount: _filteredThreads.length,
                                separatorBuilder: (_, __) => const Divider(height: 1, indent: 72),
                                itemBuilder: (ctx, i) {
                                  final thread = _filteredThreads[i];
                                  final partnerAvatar = ApiConfig.resolveImageUrl(thread.avatarUrl);

                                  return ListTile(
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                                    leading: Stack(
                                      children: [
                                        CircleAvatar(
                                          radius: 24,
                                          backgroundColor: AppTheme.primary.withValues(alpha: 0.15),
                                          backgroundImage: partnerAvatar != null ? NetworkImage(partnerAvatar) : null,
                                          child: partnerAvatar == null
                                              ? Text(
                                                  thread.partnerName.isNotEmpty
                                                      ? thread.partnerName[0].toUpperCase()
                                                      : 'U',
                                                  style: const TextStyle(
                                                    color: AppTheme.primary,
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 18,
                                                  ),
                                                )
                                              : null,
                                        ),
                                        if (thread.isOnline)
                                          Positioned(
                                            bottom: 0,
                                            right: 0,
                                            child: Container(
                                              width: 12,
                                              height: 12,
                                              decoration: BoxDecoration(
                                                color: AppTheme.success,
                                                shape: BoxShape.circle,
                                                border: Border.all(color: Colors.white, width: 2),
                                              ),
                                            ),
                                          ),
                                      ],
                                    ),
                                    title: Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            thread.partnerName,
                                            style: TextStyle(
                                              fontWeight: thread.unreadCount > 0 ? FontWeight.bold : FontWeight.w600,
                                              fontSize: 15,
                                            ),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                        if (thread.lastMessageAt.isNotEmpty)
                                          Text(
                                            thread.lastMessageAt,
                                            style: TextStyle(
                                              fontSize: 11,
                                              color: thread.unreadCount > 0 ? AppTheme.primary : Colors.grey.shade500,
                                              fontWeight: thread.unreadCount > 0 ? FontWeight.bold : FontWeight.normal,
                                            ),
                                          ),
                                      ],
                                    ),
                                    subtitle: Row(
                                      children: [
                                        if (thread.partnerRole.isNotEmpty) ...[
                                          Container(
                                            margin: const EdgeInsets.only(right: 6, top: 3),
                                            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                            decoration: BoxDecoration(
                                              color: Colors.grey.shade200,
                                              borderRadius: BorderRadius.circular(4),
                                            ),
                                            child: Text(
                                              thread.partnerRole.toUpperCase(),
                                              style: TextStyle(
                                                fontSize: 9,
                                                fontWeight: FontWeight.bold,
                                                color: Colors.grey.shade700,
                                              ),
                                            ),
                                          ),
                                        ],
                                        Expanded(
                                          child: Text(
                                            thread.lastMessage.isNotEmpty ? thread.lastMessage : 'No messages yet',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: TextStyle(
                                              fontSize: 13,
                                              color: thread.unreadCount > 0 ? Colors.black87 : Colors.grey.shade600,
                                              fontWeight: thread.unreadCount > 0 ? FontWeight.bold : FontWeight.normal,
                                            ),
                                          ),
                                        ),
                                        if (thread.unreadCount > 0)
                                          Container(
                                            margin: const EdgeInsets.only(left: 6),
                                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: AppTheme.primary,
                                              borderRadius: BorderRadius.circular(10),
                                            ),
                                            child: Text(
                                              thread.unreadCount.toString(),
                                              style: const TextStyle(
                                                color: Colors.white,
                                                fontSize: 10,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ),
                                      ],
                                    ),
                                    onTap: () {
                                      Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                          builder: (_) => ChatConversationPage(
                                            conversationId: thread.id,
                                            partnerName: thread.partnerName,
                                            partnerRole: thread.partnerRole,
                                            isPartnerOnline: thread.isOnline,
                                          ),
                                        ),
                                      ).then((_) => _loadThreads());
                                    },
                                  );
                                },
                              ),
                      ),
                    ],
                  ),
                ),
    );
  }
}
