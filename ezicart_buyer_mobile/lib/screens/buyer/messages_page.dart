import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:image_picker/image_picker.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/chat_model.dart';
import '../../models/product_model.dart';
import '../../services/api_service.dart';
import '../../utils/philippine_time.dart';
import 'product_details_page.dart';
import 'shop_page.dart';

class _LocalAttachment {
  final String name;
  final String? extension;
  final String? path;
  final Uint8List? bytes;
  final int sizeInBytes;

  _LocalAttachment({
    required this.name,
    this.extension,
    this.path,
    this.bytes,
    required this.sizeInBytes,
  });

  bool get isImage {
    final ext = cleanExtension;
    return ext == 'jpg' || ext == 'jpeg' || ext == 'png' || ext == 'webp';
  }

  String get cleanExtension {
    var ext = (extension ?? '').trim().toLowerCase().replaceAll('.', '');
    if (ext.isEmpty && name.contains('.')) {
      ext = name.split('.').last.trim().toLowerCase();
    }
    if (ext.isEmpty && path != null && path!.contains('.')) {
      ext = path!.split('.').last.trim().toLowerCase();
    }
    if (ext == 'tmp' || ext.isEmpty) {
      return 'jpg';
    }
    return ext;
  }

  String get uploadFileName {
    final ext = cleanExtension;
    if (name.contains('.')) {
      final parts = name.split('.');
      final currentExt = parts.last.trim().toLowerCase();
      if (currentExt == 'tmp' ||
          !['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']
              .contains(currentExt)) {
        return '${parts.sublist(0, parts.length - 1).join('.')}.$ext';
      }
      return name;
    }
    return '$name.$ext';
  }

  MediaType get mediaType {
    final ext = cleanExtension;
    switch (ext) {
      case 'jpg':
      case 'jpeg':
        return MediaType('image', 'jpeg');
      case 'png':
        return MediaType('image', 'png');
      case 'webp':
        return MediaType('image', 'webp');
      case 'pdf':
        return MediaType('application', 'pdf');
      case 'doc':
        return MediaType('application', 'msword');
      case 'docx':
        return MediaType('application',
            'vnd.openxmlformats-officedocument.wordprocessingml.document');
      case 'xls':
        return MediaType('application', 'vnd.ms-excel');
      case 'xlsx':
        return MediaType('application',
            'vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      case 'txt':
        return MediaType('text', 'plain');
      default:
        return MediaType('application', 'octet-stream');
    }
  }

  Future<Uint8List> readAsBytes() async {
    if (bytes != null && bytes!.isNotEmpty) return bytes!;
    if (path != null && path!.isNotEmpty) {
      final f = File(path!);
      if (await f.exists()) {
        return await f.readAsBytes();
      }
    }
    return Uint8List(0);
  }
}

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
  Timer? _threadsTimer;

  @override
  void initState() {
    super.initState();
    _fetchThreads();
    _threadsTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) _fetchThreads(silent: true);
    });
  }

  @override
  void dispose() {
    _threadsTimer?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  void fetchThreads() {
    _fetchThreads();
  }

  Future<void> _fetchThreads({bool silent = false}) async {
    if (!silent && mounted) {
      setState(() => _isLoading = true);
    }
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

    if (mounted && !silent) {
      setState(() => _isLoading = false);
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

  void _openPartnerProfile(ChatThreadModel thread) {
    if (thread.partnerRole.toLowerCase() == 'seller' || thread.partnerId > 0) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ShopPage(
            sellerId: thread.partnerId,
            initialShopName: thread.partnerName,
          ),
        ),
      );
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
            // Consistent Search Box matching homepage and search page
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Container(
                height: 44,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFDCE0E8)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.03),
                      blurRadius: 4,
                      offset: const Offset(0, 1),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    const Icon(Icons.search, size: 20, color: AppTheme.primary),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        controller: _searchCtrl,
                        textAlignVertical: TextAlignVertical.center,
                        onChanged: (val) => setState(() => _searchQuery = val),
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppTheme.ink,
                        ),
                        decoration: const InputDecoration(
                          isDense: true,
                          hintText: 'Search name or shop',
                          hintStyle: TextStyle(
                            fontSize: 13,
                            color: AppTheme.muted,
                          ),
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    ),
                    if (_searchQuery.isNotEmpty)
                      GestureDetector(
                        onTap: () {
                          _searchCtrl.clear();
                          setState(() => _searchQuery = '');
                        },
                        child: const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 4),
                          child: Icon(
                            Icons.clear,
                            size: 18,
                            color: AppTheme.muted,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),

            // Threads List with guaranteed consistent alignment across all shops
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _threads.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              width: 80,
                              height: 80,
                              decoration: const BoxDecoration(
                                color: AppTheme.primarySoft,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.chat_bubble_outline_rounded,
                                size: 42,
                                color: AppTheme.primary,
                              ),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'No conversations yet',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                                color: AppTheme.ink,
                              ),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'When you message sellers or shops, your chats and inquiries will appear here.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: AppTheme.muted,
                                fontSize: 13,
                                height: 1.35,
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                  : _filteredThreads.isEmpty
                  ? Center(
                      child: Text(
                        'No conversations matching "$_searchQuery"',
                        style: const TextStyle(
                          color: AppTheme.muted,
                          fontSize: 13,
                        ),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _fetchThreads,
                      child: ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.symmetric(
                          vertical: 8,
                          horizontal: 12,
                        ),
                        itemCount: _filteredThreads.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, i) {
                          final t = _filteredThreads[i];
                          final hasUnread = t.unreadCount > 0;
                          final roleColor = _getRoleColor(t.partnerRole);
                          final resolvedAvatar = ApiConfig.resolveImageUrl(
                            t.avatarUrl,
                          );

                          return Container(
                            decoration: BoxDecoration(
                              color: hasUnread
                                  ? const Color(0xFFFFF9FA)
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: hasUnread
                                    ? const Color(0xFFFFD1DC)
                                    : const Color(0xFFF1F3F6),
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.02),
                                  blurRadius: 4,
                                  offset: const Offset(0, 1),
                                ),
                              ],
                            ),
                            child: Material(
                              color: Colors.transparent,
                              borderRadius: BorderRadius.circular(12),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: () {
                                  Navigator.of(context)
                                      .push(
                                        MaterialPageRoute(
                                          builder: (_) =>
                                              ChatConversationScreen(
                                                conversationId: t.id,
                                                partnerName: t.partnerName,
                                                partnerId: t.partnerId,
                                                partnerRole: t.partnerRole,
                                                avatarUrl: t.avatarUrl,
                                                isOnline: t.isOnline,
                                                lastActiveAt: t.lastActiveAt,
                                                lastActiveText:
                                                    t.lastActiveText,
                                              ),
                                        ),
                                      )
                                      .then((_) => _fetchThreads());
                                },
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.center,
                                    children: [
                                      // Clickable Profile Avatar with Online Status
                                      GestureDetector(
                                        onTap: () => _openPartnerProfile(t),
                                        child: Stack(
                                          clipBehavior: Clip.none,
                                          children: [
                                            Container(
                                              width: 50,
                                              height: 50,
                                              decoration: BoxDecoration(
                                                gradient: LinearGradient(
                                                  colors: [
                                                    roleColor.withValues(
                                                      alpha: 0.15,
                                                    ),
                                                    roleColor.withValues(
                                                      alpha: 0.25,
                                                    ),
                                                  ],
                                                  begin: Alignment.topLeft,
                                                  end: Alignment.bottomRight,
                                                ),
                                                shape: BoxShape.circle,
                                              ),
                                              child: resolvedAvatar != null
                                                  ? ClipOval(
                                                      child: CachedNetworkImage(
                                                        imageUrl:
                                                            resolvedAvatar,
                                                        fit: BoxFit.cover,
                                                        errorWidget: (_, __, ___) => Center(
                                                          child: Text(
                                                            t
                                                                    .partnerName
                                                                    .isNotEmpty
                                                                ? t.partnerName[0]
                                                                      .toUpperCase()
                                                                : 'S',
                                                            style: TextStyle(
                                                              fontWeight:
                                                                  FontWeight
                                                                      .bold,
                                                              fontSize: 18,
                                                              color: roleColor,
                                                            ),
                                                          ),
                                                        ),
                                                      ),
                                                    )
                                                  : Center(
                                                      child: Text(
                                                        t.partnerName.isNotEmpty
                                                            ? t.partnerName[0]
                                                                  .toUpperCase()
                                                            : 'S',
                                                        style: TextStyle(
                                                          fontWeight:
                                                              FontWeight.bold,
                                                          fontSize: 18,
                                                          color: roleColor,
                                                        ),
                                                      ),
                                                    ),
                                            ),
                                            // Online indicator dot
                                            if (t.isOnline)
                                              Positioned(
                                                right: 1,
                                                bottom: 1,
                                                child: Container(
                                                  width: 13,
                                                  height: 13,
                                                  decoration: BoxDecoration(
                                                    color: const Color(
                                                      0xFF10B981,
                                                    ),
                                                    shape: BoxShape.circle,
                                                    border: Border.all(
                                                      color: Colors.white,
                                                      width: 2,
                                                    ),
                                                  ),
                                                ),
                                              ),
                                          ],
                                        ),
                                      ),
                                      const SizedBox(width: 12),

                                      // Two-row structure guaranteeing consistent time alignment across every shop
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            // Row 1: Name + Role pill on left, Time on far right
                                            Row(
                                              children: [
                                                Expanded(
                                                  child: Row(
                                                    children: [
                                                      Flexible(
                                                        child: Text(
                                                          t.partnerName,
                                                          maxLines: 1,
                                                          overflow: TextOverflow
                                                              .ellipsis,
                                                          style: TextStyle(
                                                            fontWeight:
                                                                hasUnread
                                                                ? FontWeight
                                                                      .bold
                                                                : FontWeight
                                                                      .w600,
                                                            fontSize: 14.5,
                                                            color: AppTheme.ink,
                                                          ),
                                                        ),
                                                      ),
                                                      const SizedBox(width: 6),
                                                      Container(
                                                        padding:
                                                            const EdgeInsets.symmetric(
                                                              horizontal: 5,
                                                              vertical: 1.5,
                                                            ),
                                                        decoration: BoxDecoration(
                                                          color: roleColor
                                                              .withValues(
                                                                alpha: 0.1,
                                                              ),
                                                          borderRadius:
                                                              BorderRadius.circular(
                                                                4,
                                                              ),
                                                        ),
                                                        child: Text(
                                                          t.partnerRole
                                                              .toUpperCase(),
                                                          style: TextStyle(
                                                            fontSize: 8.5,
                                                            fontWeight:
                                                                FontWeight.bold,
                                                            color: roleColor,
                                                            letterSpacing: 0.3,
                                                          ),
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                ),
                                                const SizedBox(width: 8),
                                                // Fixed right-aligned timestamp in Philippine Time
                                                Text(
                                                  PhilippineTime.formatChatTime(
                                                    t.lastMessageAt,
                                                  ),
                                                  style: TextStyle(
                                                    fontSize: 11,
                                                    color: hasUnread
                                                        ? AppTheme.primary
                                                        : AppTheme.muted,
                                                    fontWeight: hasUnread
                                                        ? FontWeight.bold
                                                        : FontWeight.normal,
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 5),

                                            // Row 2: Message preview on left, Unread badge on far right
                                            Row(
                                              children: [
                                                Expanded(
                                                  child: Text(
                                                    t.lastMessage.isNotEmpty
                                                        ? t.lastMessage
                                                        : 'Start a conversation...',
                                                    maxLines: 1,
                                                    overflow:
                                                        TextOverflow.ellipsis,
                                                    style: TextStyle(
                                                      fontSize: 12.5,
                                                      color: hasUnread
                                                          ? AppTheme.ink
                                                          : AppTheme.muted,
                                                      fontWeight: hasUnread
                                                          ? FontWeight.w600
                                                          : FontWeight.normal,
                                                    ),
                                                  ),
                                                ),
                                                if (hasUnread) ...[
                                                  const SizedBox(width: 8),
                                                  Container(
                                                    padding:
                                                        const EdgeInsets.symmetric(
                                                          horizontal: 7,
                                                          vertical: 2,
                                                        ),
                                                    decoration: BoxDecoration(
                                                      color: AppTheme.primary,
                                                      borderRadius:
                                                          BorderRadius.circular(
                                                            10,
                                                          ),
                                                    ),
                                                    child: Text(
                                                      '${t.unreadCount}',
                                                      style: const TextStyle(
                                                        color: Colors.white,
                                                        fontSize: 10,
                                                        fontWeight:
                                                            FontWeight.bold,
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
  final int? partnerId;
  final String? partnerRole;
  final String? avatarUrl;
  final bool? isOnline;
  final String? lastActiveAt;
  final String? lastActiveText;
  final int? orderId;
  final int? productId;

  const ChatConversationScreen({
    super.key,
    required this.conversationId,
    required this.partnerName,
    this.partnerId,
    this.partnerRole,
    this.avatarUrl,
    this.isOnline,
    this.lastActiveAt,
    this.lastActiveText,
    this.orderId,
    this.productId,
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
  bool _canSend = true;
  Timer? _pollTimer;

  // Selected attachments (up to 3, max 2MB each)
  final List<_LocalAttachment> _selectedAttachments = [];

  // Partner status updated from API
  late int _partnerId;
  late String _partnerName;
  late String _partnerRole;
  String? _avatarUrl;
  bool _isOnline = false;
  String? _lastActiveAt;
  String? _lastActiveText;
  String? _authToken;

  @override
  void initState() {
    super.initState();
    _partnerId = widget.partnerId ?? 0;
    _partnerName = widget.partnerName;
    _partnerRole = widget.partnerRole ?? 'seller';
    _avatarUrl = widget.avatarUrl;
    _isOnline = widget.isOnline ?? false;
    _lastActiveAt = widget.lastActiveAt;
    _lastActiveText = widget.lastActiveText;

    _loadAuthToken();
    _loadMessages();
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted) _loadMessages(silent: true);
    });
  }

  Future<void> _loadAuthToken() async {
    final t = await ApiService.getToken();
    if (mounted) setState(() => _authToken = t);
  }

  String _authenticatedUrl(String url) {
    if (url.isEmpty || _authToken == null || _authToken!.isEmpty) return url;
    final separator = url.contains('?') ? '&' : '?';
    return '$url${separator}token=$_authToken';
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _openShopProfile() {
    if (_partnerId > 0) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) =>
              ShopPage(sellerId: _partnerId, initialShopName: _partnerName),
        ),
      );
    }
  }

  Future<void> _loadMessages({bool silent = false}) async {
    if (!silent && mounted) {
      setState(() => _isLoading = true);
    }
    try {
      final res = await ApiService.get(
        ApiConfig.chatConversationEndpoint(widget.conversationId),
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final data = body['data'];
        final partner = data?['partner'];

        if (mounted) {
          setState(() {
            if (partner != null) {
              _partnerId = (partner['id'] is int)
                  ? partner['id']
                  : int.tryParse('${partner['id']}') ?? _partnerId;
              _partnerName = partner['name']?.toString() ?? _partnerName;
              _partnerRole = partner['role']?.toString() ?? _partnerRole;
              _avatarUrl = partner['avatar_url']?.toString() ?? _avatarUrl;
              _isOnline = partner['is_online'] == true;
              _lastActiveAt =
                  partner['last_active_at']?.toString() ?? _lastActiveAt;
              _lastActiveText =
                  partner['last_active_text']?.toString() ?? _lastActiveText;
            }
            if (data?['can_send'] != null) {
              _canSend = data['can_send'] == true;
            }
          });
        }

        final rawMessages = data?['messages'];
        if (rawMessages is List && mounted) {
          final list = rawMessages
              .map((m) => ChatMessageModel.fromJson(m as Map<String, dynamic>))
              .toList();

          final bool hasChanged = list.length != _messages.length ||
              (list.isNotEmpty && _messages.isNotEmpty && list.last.id != _messages.last.id);

          final bool wasNearBottom = _scrollCtrl.hasClients &&
              (_scrollCtrl.position.maxScrollExtent - _scrollCtrl.offset < 120);

          setState(() {
            _messages = list;
            _isLoading = false;
          });

          if (hasChanged && (wasNearBottom || !silent)) {
            _scrollToBottom();
          }
          return;
        }
      }
    } catch (_) {}

    if (mounted && !silent) {
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

  /// Interactive picker with options for Gallery, Camera, and Documents
  Future<void> _showAttachmentPickerOptions() async {
    if (_selectedAttachments.length >= 3) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('You can only attach up to 3 files per message.'),
          backgroundColor: Color(0xFFBE2854),
        ),
      );
      return;
    }

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 38,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 14),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const Text(
                  'Attach Files (Max 3, 2MB each)',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                ),
                const SizedBox(height: 12),
                ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFFFF0F4),
                    child: Icon(
                      Icons.photo_library_rounded,
                      color: AppTheme.primary,
                    ),
                  ),
                  title: const Text(
                    'Photo Gallery',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: const Text('Select photos from your gallery'),
                  onTap: () async {
                    Navigator.pop(ctx);
                    await Future.delayed(const Duration(milliseconds: 120));
                    _pickFromGallery();
                  },
                ),
                ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFEFF6FF),
                    child: Icon(
                      Icons.camera_alt_rounded,
                      color: Color(0xFF2563EB),
                    ),
                  ),
                  title: const Text(
                    'Take Photo',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: const Text('Capture a picture with your camera'),
                  onTap: () async {
                    Navigator.pop(ctx);
                    await Future.delayed(const Duration(milliseconds: 120));
                    _pickFromCamera();
                  },
                ),
                ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFF0FDF4),
                    child: Icon(
                      Icons.folder_open_rounded,
                      color: Color(0xFF16A34A),
                    ),
                  ),
                  title: const Text(
                    'Documents & Files',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: const Text(
                    'PDFs, Word docs, spreadsheets, or text files',
                  ),
                  onTap: () async {
                    Navigator.pop(ctx);
                    await Future.delayed(const Duration(milliseconds: 120));
                    _pickDocuments();
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _pickFromGallery() async {
    try {
      final picker = ImagePicker();
      List<XFile> images = [];

      try {
        images = await picker.pickMultiImage(
          maxWidth: 1920,
          maxHeight: 1920,
          imageQuality: 85,
        );
      } catch (e) {
        debugPrint('pickMultiImage failed, trying single image: $e');
        try {
          final single = await picker.pickImage(
            source: ImageSource.gallery,
            maxWidth: 1920,
            maxHeight: 1920,
            imageQuality: 85,
          );
          if (single != null) images = [single];
        } catch (e2) {
          debugPrint('pickImage failed: $e2');
        }
      }

      if (images.isNotEmpty) {
        final remaining = 3 - _selectedAttachments.length;
        int added = 0;
        for (final img in images) {
          if (added >= remaining) break;
          final bytes = await img.readAsBytes();
          final length = bytes.isNotEmpty ? bytes.length : await img.length();
          if (length > 2 * 1024 * 1024) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('File "${img.name}" exceeds the 2MB limit.'),
                  backgroundColor: const Color(0xFFBE2854),
                ),
              );
            }
            continue;
          }
          var ext = (img.name.contains('.')
                  ? img.name.split('.').last
                  : (img.path.contains('.') ? img.path.split('.').last : 'jpg'))
              .toLowerCase();
          if (ext == 'tmp' || ext.isEmpty) ext = 'jpg';
          final name =
              img.name.endsWith('.$ext') ? img.name : '${img.name}.$ext';

          _selectedAttachments.add(
            _LocalAttachment(
              name: name,
              extension: ext,
              path: img.path,
              bytes: bytes,
              sizeInBytes: length,
            ),
          );
          added++;
        }
        if (mounted) setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error selecting images: $e')),
        );
      }
    }
  }

  Future<void> _pickFromCamera() async {
    try {
      final picker = ImagePicker();
      final XFile? photo = await picker.pickImage(
        source: ImageSource.camera,
        maxWidth: 1920,
        maxHeight: 1920,
        imageQuality: 85,
      );
      if (photo != null) {
        final bytes = await photo.readAsBytes();
        final length = bytes.isNotEmpty ? bytes.length : await photo.length();
        if (length > 2 * 1024 * 1024) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Photo "${photo.name}" exceeds the 2MB limit.'),
                backgroundColor: const Color(0xFFBE2854),
              ),
            );
          }
          return;
        }
        var ext = (photo.name.contains('.')
                ? photo.name.split('.').last
                : 'jpg')
            .toLowerCase();
        if (ext == 'tmp' || ext.isEmpty) ext = 'jpg';
        final name =
            photo.name.endsWith('.$ext') ? photo.name : '${photo.name}.$ext';

        _selectedAttachments.add(
          _LocalAttachment(
            name: name,
            extension: ext,
            path: photo.path,
            bytes: bytes,
            sizeInBytes: length,
          ),
        );
        if (mounted) setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error opening camera: $e')),
        );
      }
    }
  }

  Future<void> _pickDocuments() async {
    try {
      final List<PlatformFile> files = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: [
          'jpg',
          'jpeg',
          'png',
          'webp',
          'pdf',
          'doc',
          'docx',
          'xls',
          'xlsx',
          'txt',
        ],
      );
      if (files.isNotEmpty) {
        final remaining = 3 - _selectedAttachments.length;
        int added = 0;
        final allowed = {
          'jpg',
          'jpeg',
          'png',
          'webp',
          'pdf',
          'doc',
          'docx',
          'xls',
          'xlsx',
          'txt',
        };

        for (final file in files) {
          if (added >= remaining) break;
          final ext = file.extension?.toLowerCase() ??
              (file.name.contains('.')
                  ? file.name.split('.').last.toLowerCase()
                  : '');
          if (!allowed.contains(ext)) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    'File "${file.name}" has an unsupported format. Allowed formats: JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX, TXT.',
                  ),
                  backgroundColor: const Color(0xFFBE2854),
                ),
              );
            }
            continue;
          }

          final bytes = await file.readAsBytes();
          final size =
              file.lengthSync() ?? (await file.length()) ?? bytes.length;
          if (size > 2 * 1024 * 1024) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('File "${file.name}" exceeds the 2MB limit.'),
                  backgroundColor: const Color(0xFFBE2854),
                ),
              );
            }
            continue;
          }

          final name =
              file.name.endsWith('.$ext') ? file.name : '${file.name}.$ext';

          _selectedAttachments.add(
            _LocalAttachment(
              name: name,
              extension: ext,
              path: file.path,
              bytes: bytes,
              sizeInBytes: size,
            ),
          );
          added++;
        }
        if (mounted) setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error picking file: $e')),
        );
      }
    }
  }

  void _removeAttachment(int index) {
    setState(() {
      _selectedAttachments.removeAt(index);
    });
  }

  Future<void> _unsendMessage(ChatMessageModel message) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Unsend message?'),
        content: const Text(
          'The message and its files will be removed from this conversation for everyone. People may already have seen or downloaded them.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text(
              'Unsend for everyone',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      final res = await ApiService.delete(
        ApiConfig.chatUnsendMessageEndpoint(widget.conversationId, message.id),
      );
      if (res.statusCode == 200) {
        await _loadMessages(silent: true);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Message unsent for everyone.')),
          );
        }
      } else {
        if (mounted) {
          final body = jsonDecode(res.body);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(body['message'] ?? 'Failed to unsend message.'),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Error: $e')));
      }
    }
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if ((text.isEmpty && _selectedAttachments.isEmpty) || _isSending) return;

    if (text.length > 2000) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Message cannot exceed 2000 characters.'),
          backgroundColor: Color(0xFFBE2854),
        ),
      );
      return;
    }

    setState(() => _isSending = true);

    try {
      http.Response res;

      if (_selectedAttachments.isNotEmpty) {
        final fields = <String, String>{
          if (text.isNotEmpty) 'body': text,
          if (widget.orderId != null) 'order_id': '${widget.orderId}',
          if (widget.productId != null) 'product_id': '${widget.productId}',
        };

        final files = <http.MultipartFile>[];
        for (final att in _selectedAttachments) {
          final uploadName = att.uploadFileName;
          final mediaType = att.mediaType;

          if (att.bytes != null && att.bytes!.isNotEmpty) {
            files.add(
              http.MultipartFile.fromBytes(
                'attachments[]',
                att.bytes!,
                filename: uploadName,
                contentType: mediaType,
              ),
            );
          } else if (att.path != null &&
              att.path!.isNotEmpty &&
              File(att.path!).existsSync()) {
            files.add(
              await http.MultipartFile.fromPath(
                'attachments[]',
                att.path!,
                filename: uploadName,
                contentType: mediaType,
              ),
            );
          } else {
            final b = await att.readAsBytes();
            if (b.isNotEmpty) {
              files.add(
                http.MultipartFile.fromBytes(
                  'attachments[]',
                  b,
                  filename: uploadName,
                  contentType: mediaType,
                ),
              );
            } else {
              throw Exception(
                  'Unable to read attachment "${att.name}". Please reselect the file.');
            }
          }
        }

        res = await ApiService.postMultipart(
          ApiConfig.chatSendMessageEndpoint(widget.conversationId),
          fields: fields,
          files: files,
        );
      } else {
        res = await ApiService.post(
          ApiConfig.chatSendMessageEndpoint(widget.conversationId),
          {
            'body': text,
            if (widget.orderId != null) 'order_id': widget.orderId,
            if (widget.productId != null) 'product_id': widget.productId,
          },
        );
      }

      if (res.statusCode == 200 || res.statusCode == 201) {
        final body = jsonDecode(res.body);
        final newMsg = ChatMessageModel.fromJson(body['data']);
        if (mounted) {
          setState(() {
            _messages.add(newMsg);
            _msgCtrl.clear();
            _selectedAttachments.clear();
            _isSending = false;
          });
          _scrollToBottom();
        }
      } else {
        if (mounted) {
          setState(() => _isSending = false);
          String err = 'Failed to send message.';
          try {
            final body = jsonDecode(res.body);
            if (body['errors'] is Map) {
              final errMap = body['errors'] as Map;
              final list = errMap.values
                  .expand((v) => v is List ? v : [v])
                  .toList();
              if (list.isNotEmpty) err = list.first.toString();
            } else if (body['message'] != null) {
              err = body['message'].toString();
            }
          } catch (_) {}
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(err),
              backgroundColor: const Color(0xFFBE2854),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isSending = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Could not send message: $e')));
      }
    }
  }

  void _showImagePreview(String imageUrl) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(12),
        child: Stack(
          alignment: Alignment.center,
          children: [
            InteractiveViewer(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: CachedNetworkImage(
                  imageUrl: _authenticatedUrl(imageUrl),
                  httpHeaders: _authToken != null
                      ? {'Authorization': 'Bearer $_authToken'}
                      : null,
                  fit: BoxFit.contain,
                  placeholder: (_, __) => const Center(
                    child: CircularProgressIndicator(color: Colors.white),
                  ),
                  errorWidget: (_, __, ___) => const Center(
                    child: Icon(
                      Icons.broken_image,
                      color: Colors.white,
                      size: 48,
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 10,
              right: 10,
              child: IconButton(
                icon: const Icon(Icons.close, color: Colors.white, size: 28),
                onPressed: () => Navigator.pop(context),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showDocumentInfo(ChatAttachmentModel att) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(
              att.mimeType.contains('pdf')
                  ? Icons.picture_as_pdf_rounded
                  : Icons.insert_drive_file_rounded,
              color: AppTheme.primary,
            ),
            const SizedBox(width: 8),
            const Text(
              'File Details',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              att.name,
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            Text('Size: ${att.formattedSize}'),
            const SizedBox(height: 4),
            Text('Type: ${att.mimeType}'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  Widget _buildAttachmentPreviewTray() {
    if (_selectedAttachments.isEmpty) return const SizedBox.shrink();

    return Container(
      color: const Color(0xFFF1F5F9),
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Attachments (${_selectedAttachments.length}/3) • Max 2MB each',
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.muted,
                ),
              ),
              if (_selectedAttachments.length < 3)
                GestureDetector(
                  onTap: _showAttachmentPickerOptions,
                  child: const Text(
                    '+ Add file',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.primary,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 6),
          SizedBox(
            height: 52,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _selectedAttachments.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, i) {
                final file = _selectedAttachments[i];
                final sizeKb = (file.sizeInBytes / 1024).toStringAsFixed(0);

                return Container(
                  width: 140,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFFCBD5E1)),
                  ),
                  child: Row(
                    children: [
                      if (file.isImage &&
                          file.bytes != null &&
                          file.bytes!.isNotEmpty)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: Image.memory(
                            file.bytes!,
                            width: 24,
                            height: 24,
                            fit: BoxFit.cover,
                          ),
                        )
                      else if (file.isImage &&
                          file.path != null &&
                          File(file.path!).existsSync())
                        ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: Image.file(
                            File(file.path!),
                            width: 24,
                            height: 24,
                            fit: BoxFit.cover,
                          ),
                        )
                      else
                        Icon(
                          file.extension == 'pdf'
                              ? Icons.picture_as_pdf_rounded
                              : Icons.attach_file_rounded,
                          size: 20,
                          color: file.extension == 'pdf'
                              ? Colors.red
                              : AppTheme.primary,
                        ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              file.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Text(
                              '$sizeKb KB',
                              style: const TextStyle(
                                fontSize: 9.5,
                                color: AppTheme.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      GestureDetector(
                        onTap: () => _removeAttachment(i),
                        child: const Icon(
                          Icons.close,
                          size: 16,
                          color: AppTheme.muted,
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final resolvedAvatar = ApiConfig.resolveImageUrl(_avatarUrl);
    final activeStatusText = _isOnline
        ? 'Active now'
        : PhilippineTime.formatLastActive(_lastActiveAt, isOnline: _isOnline);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0.5,
        titleSpacing: 0,
        title: InkWell(
          onTap: _openShopProfile,
          borderRadius: BorderRadius.circular(8),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
            child: Row(
              children: [
                // Clickable Avatar with online status dot
                Stack(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: const BoxDecoration(
                        color: AppTheme.primarySoft,
                        shape: BoxShape.circle,
                      ),
                      child: resolvedAvatar != null
                          ? ClipOval(
                              child: CachedNetworkImage(
                                imageUrl: resolvedAvatar,
                                fit: BoxFit.cover,
                                errorWidget: (_, __, ___) => Center(
                                  child: Text(
                                    _partnerName.isNotEmpty
                                        ? _partnerName[0].toUpperCase()
                                        : 'S',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.bold,
                                      color: AppTheme.primary,
                                      fontSize: 16,
                                    ),
                                  ),
                                ),
                              ),
                            )
                          : Center(
                              child: Text(
                                _partnerName.isNotEmpty
                                    ? _partnerName[0].toUpperCase()
                                    : 'S',
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: AppTheme.primary,
                                  fontSize: 16,
                                ),
                              ),
                            ),
                    ),
                    if (_isOnline)
                      Positioned(
                        right: 0,
                        bottom: 0,
                        child: Container(
                          width: 11,
                          height: 11,
                          decoration: BoxDecoration(
                            color: const Color(0xFF10B981),
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              _partnerName,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.bold,
                                color: AppTheme.ink,
                              ),
                            ),
                          ),
                          const SizedBox(width: 4),
                          const Icon(
                            Icons.chevron_right_rounded,
                            size: 18,
                            color: AppTheme.muted,
                          ),
                        ],
                      ),
                      Row(
                        children: [
                          if (_isOnline) ...[
                            Container(
                              width: 6,
                              height: 6,
                              margin: const EdgeInsets.only(right: 4),
                              decoration: const BoxDecoration(
                                color: Color(0xFF10B981),
                                shape: BoxShape.circle,
                              ),
                            ),
                          ],
                          Flexible(
                            child: Text(
                              '$activeStatusText • View shop',
                              style: TextStyle(
                                fontSize: 11,
                                color: _isOnline
                                    ? const Color(0xFF059669)
                                    : AppTheme.muted,
                                fontWeight: _isOnline
                                    ? FontWeight.w500
                                    : FontWeight.normal,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Chat message stream with Date Dividers, Sender Icon & Name
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
                            const Icon(
                              Icons.waving_hand_outlined,
                              size: 40,
                              color: Color(0xFFE26A7A),
                            ),
                            const SizedBox(height: 10),
                            Text(
                              'Say hello to $_partnerName!',
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: AppTheme.ink,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Ask about product details, availability, custom requests, or Laguna deliveries.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 12,
                                color: AppTheme.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView.builder(
                      controller: _scrollCtrl,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 12,
                      ),
                      itemCount: _messages.length,
                      itemBuilder: (context, i) {
                        final m = _messages[i];
                        final isFirst = i == 0;
                        final isNewDay =
                            isFirst ||
                            !PhilippineTime.isSameDay(
                              _messages[i - 1].createdAt,
                              m.createdAt,
                            );
                        final bubbleTime = PhilippineTime.formatBubbleTime(
                          m.createdAt,
                        );

                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // Date divider pill when day changes
                            if (isNewDay)
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 12,
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Container(
                                        height: 1,
                                        color: const Color(0xFFE2E8F0),
                                      ),
                                    ),
                                    Container(
                                      margin: const EdgeInsets.symmetric(
                                        horizontal: 10,
                                      ),
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 12,
                                        vertical: 4,
                                      ),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFF1F5F9),
                                        borderRadius: BorderRadius.circular(12),
                                        border: Border.all(
                                          color: const Color(0xFFE2E8F0),
                                        ),
                                      ),
                                      child: Text(
                                        PhilippineTime.formatDateDivider(
                                          m.createdAt,
                                        ),
                                        style: const TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w600,
                                          color: Color(0xFF64748B),
                                        ),
                                      ),
                                    ),
                                    Expanded(
                                      child: Container(
                                        height: 1,
                                        color: const Color(0xFFE2E8F0),
                                      ),
                                    ),
                                  ],
                                ),
                              ),

                            // Partner Message Header with Name & Icon (when incoming)
                            if (!m.isMe) ...[
                              Padding(
                                padding: const EdgeInsets.only(
                                  left: 38,
                                  bottom: 2,
                                  top: 4,
                                ),
                                child: Text(
                                  _partnerName,
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF64748B),
                                  ),
                                ),
                              ),
                            ],

                            // Message row with avatar on incoming
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 2),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                mainAxisAlignment: m.isMe
                                    ? MainAxisAlignment.end
                                    : MainAxisAlignment.start,
                                children: [
                                  // Seller avatar icon beside incoming message
                                  if (!m.isMe) ...[
                                    Container(
                                      width: 28,
                                      height: 28,
                                      margin: const EdgeInsets.only(
                                        right: 8,
                                        bottom: 2,
                                      ),
                                      decoration: const BoxDecoration(
                                        color: AppTheme.primarySoft,
                                        shape: BoxShape.circle,
                                      ),
                                      child: resolvedAvatar != null
                                          ? ClipOval(
                                              child: CachedNetworkImage(
                                                imageUrl: resolvedAvatar,
                                                fit: BoxFit.cover,
                                                errorWidget: (_, __, ___) =>
                                                    Center(
                                                      child: Text(
                                                        _partnerName.isNotEmpty
                                                            ? _partnerName[0]
                                                                  .toUpperCase()
                                                            : 'S',
                                                        style: const TextStyle(
                                                          fontWeight:
                                                              FontWeight.bold,
                                                          color:
                                                              AppTheme.primary,
                                                          fontSize: 12,
                                                        ),
                                                      ),
                                                    ),
                                              ),
                                            )
                                          : Center(
                                              child: Text(
                                                _partnerName.isNotEmpty
                                                    ? _partnerName[0]
                                                          .toUpperCase()
                                                    : 'S',
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  color: AppTheme.primary,
                                                  fontSize: 12,
                                                ),
                                              ),
                                            ),
                                    ),
                                  ],

                                  // Bubble content with Unsend & Product Enquiry support
                                  GestureDetector(
                                    onLongPress: (m.isMe && !m.isUnsent)
                                        ? () => _unsendMessage(m)
                                        : null,
                                    child: Container(
                                      constraints: BoxConstraints(
                                        maxWidth:
                                            MediaQuery.of(context).size.width *
                                            0.74,
                                      ),
                                      padding: const EdgeInsets.fromLTRB(
                                        12,
                                        10,
                                        12,
                                        8,
                                      ),
                                      decoration: BoxDecoration(
                                        gradient: m.isMe && !m.isUnsent
                                            ? const LinearGradient(
                                                colors: [
                                                  Color(0xFFD63363),
                                                  Color(0xFFBE2854),
                                                ],
                                                begin: Alignment.topLeft,
                                                end: Alignment.bottomRight,
                                              )
                                            : null,
                                        color: m.isMe
                                            ? (m.isUnsent
                                                ? const Color(0xFFF1F5F9)
                                                : null)
                                            : Colors.white,
                                        borderRadius: BorderRadius.only(
                                          topLeft: const Radius.circular(16),
                                          topRight: const Radius.circular(16),
                                          bottomLeft: Radius.circular(
                                            m.isMe ? 16 : 4,
                                          ),
                                          bottomRight: Radius.circular(
                                            m.isMe ? 4 : 16,
                                          ),
                                        ),
                                        border: m.isMe && !m.isUnsent
                                            ? null
                                            : Border.all(
                                                color: const Color(0xFFE2E8F0),
                                              ),
                                        boxShadow: [
                                          BoxShadow(
                                            color: Colors.black.withValues(
                                              alpha: 0.03,
                                            ),
                                            blurRadius: 4,
                                            offset: const Offset(0, 1),
                                          ),
                                        ],
                                      ),
                                      child: Column(
                                        crossAxisAlignment: m.isMe
                                            ? CrossAxisAlignment.end
                                            : CrossAxisAlignment.start,
                                        children: [
                                          // Product Enquiry Context Banner
                                          if (!m.isUnsent &&
                                              m.productContext != null) ...[
                                            GestureDetector(
                                              onTap: () {
                                                final pid =
                                                    m.productContext!['product_id'];
                                                if (pid != null) {
                                                  final id = pid is int
                                                      ? pid
                                                      : int.tryParse('$pid');
                                                  if (id != null) {
                                                    Navigator.of(context).push(
                                                      MaterialPageRoute(
                                                        builder: (_) =>
                                                            ProductDetailsPage(
                                                              product: ProductModel(
                                                                id: id,
                                                                name: m.productContext!['name']?.toString() ?? 'Product',
                                                                description: '',
                                                                category: 'General',
                                                                price: 0,
                                                                stock: 1,
                                                                sellerName: _partnerName,
                                                                variations: [],
                                                              ),
                                                            ),
                                                      ),
                                                    );
                                                  }
                                                }
                                              },
                                              child: Container(
                                                margin: const EdgeInsets.only(
                                                  bottom: 8,
                                                ),
                                                padding: const EdgeInsets.all(
                                                  8,
                                                ),
                                                decoration: BoxDecoration(
                                                  color: m.isMe
                                                      ? Colors.white.withValues(
                                                          alpha: 0.18,
                                                        )
                                                      : const Color(0xFFF8FAFC),
                                                  borderRadius:
                                                      BorderRadius.circular(8),
                                                  border: Border.all(
                                                    color: m.isMe
                                                        ? Colors.white.withValues(
                                                            alpha: 0.35,
                                                          )
                                                        : const Color(
                                                            0xFFE2E8F0,
                                                          ),
                                                  ),
                                                ),
                                                child: Row(
                                                  mainAxisSize:
                                                      MainAxisSize.min,
                                                  children: [
                                                    if (m.productContext!['image_url'] !=
                                                        null)
                                                      ClipRRect(
                                                        borderRadius:
                                                            BorderRadius.circular(
                                                              6,
                                                            ),
                                                        child: CachedNetworkImage(
                                                          imageUrl:
                                                              m.productContext!['image_url'],
                                                          width: 40,
                                                          height: 40,
                                                          fit: BoxFit.cover,
                                                          errorWidget:
                                                              (_, __, ___) =>
                                                                  const Icon(
                                                                    Icons
                                                                        .shopping_bag_outlined,
                                                                    size: 22,
                                                                  ),
                                                        ),
                                                      ),
                                                    const SizedBox(width: 8),
                                                    Flexible(
                                                      child: Column(
                                                        crossAxisAlignment:
                                                            CrossAxisAlignment
                                                                .start,
                                                        children: [
                                                          Text(
                                                            'Enquiry: ${m.productContext!['name'] ?? 'Product'}',
                                                            maxLines: 1,
                                                            overflow:
                                                                TextOverflow
                                                                    .ellipsis,
                                                            style: TextStyle(
                                                              fontWeight:
                                                                  FontWeight
                                                                      .bold,
                                                              fontSize: 11.5,
                                                              color: m.isMe
                                                                  ? Colors.white
                                                                  : AppTheme
                                                                      .ink,
                                                            ),
                                                          ),
                                                          if (m.productContext!['price_label'] !=
                                                              null)
                                                            Text(
                                                              '${m.productContext!['price_label']} · at enquiry time',
                                                              style: TextStyle(
                                                                fontSize: 10,
                                                                color: m.isMe
                                                                    ? Colors
                                                                        .white70
                                                                    : AppTheme
                                                                        .primary,
                                                              ),
                                                            ),
                                                        ],
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ),
                                          ],

                                          // Attachments if present
                                          if (!m.isUnsent &&
                                              m.attachments.isNotEmpty) ...[
                                            ...m.attachments.map((att) {
                                              if (att.isImage &&
                                                  att.url.isNotEmpty) {
                                                return Padding(
                                                  padding: const EdgeInsets.only(
                                                    bottom: 6,
                                                  ),
                                                child: ClipRRect(
                                                  borderRadius:
                                                      BorderRadius.circular(8),
                                                  child: GestureDetector(
                                                    onTap: () =>
                                                        _showImagePreview(
                                                          att.url,
                                                        ),
                                                    child: Container(
                                                      constraints:
                                                          const BoxConstraints(
                                                            maxHeight: 180,
                                                          ),
                                                      child: CachedNetworkImage(
                                                        imageUrl: _authenticatedUrl(att.url),
                                                        httpHeaders: _authToken != null
                                                            ? {'Authorization': 'Bearer $_authToken'}
                                                            : null,
                                                        fit: BoxFit.cover,
                                                        placeholder: (_, __) =>
                                                            Container(
                                                              height: 120,
                                                              color: Colors
                                                                  .grey
                                                                  .shade200,
                                                              child: const Center(
                                                                child:
                                                                    CircularProgressIndicator(
                                                                      strokeWidth:
                                                                          2,
                                                                    ),
                                                              ),
                                                            ),
                                                        errorWidget:
                                                            (
                                                              _,
                                                              __,
                                                              ___,
                                                            ) => Container(
                                                              height: 100,
                                                              color: Colors
                                                                  .grey
                                                                  .shade200,
                                                              child: const Center(
                                                                child: Icon(
                                                                  Icons
                                                                      .broken_image,
                                                                  color: Colors
                                                                      .grey,
                                                                ),
                                                              ),
                                                            ),
                                                      ),
                                                    ),
                                                  ),
                                                ),
                                              );
                                            } else {
                                              // Document file attachment chip
                                              return GestureDetector(
                                                onTap: () => _showDocumentInfo(att),
                                                child: Container(
                                                margin: const EdgeInsets.only(
                                                  bottom: 6,
                                                ),
                                                padding: const EdgeInsets.all(
                                                  8,
                                                ),
                                                decoration: BoxDecoration(
                                                  color: m.isMe
                                                      ? Colors.white.withValues(
                                                          alpha: 0.15,
                                                        )
                                                      : const Color(0xFFF1F5F9),
                                                  borderRadius:
                                                      BorderRadius.circular(8),
                                                ),
                                                child: Row(
                                                  mainAxisSize:
                                                      MainAxisSize.min,
                                                  children: [
                                                    Icon(
                                                      att.mimeType.contains(
                                                            'pdf',
                                                          )
                                                          ? Icons
                                                                .picture_as_pdf_rounded
                                                          : Icons
                                                                .insert_drive_file_rounded,
                                                      size: 22,
                                                      color: m.isMe
                                                          ? Colors.white
                                                          : AppTheme.primary,
                                                    ),
                                                    const SizedBox(width: 8),
                                                    Flexible(
                                                      child: Column(
                                                        crossAxisAlignment:
                                                            CrossAxisAlignment
                                                                .start,
                                                        children: [
                                                          Text(
                                                            att.name,
                                                            maxLines: 1,
                                                            overflow:
                                                                TextOverflow
                                                                    .ellipsis,
                                                            style: TextStyle(
                                                              fontWeight:
                                                                  FontWeight
                                                                      .bold,
                                                              fontSize: 12,
                                                              color: m.isMe
                                                                  ? Colors.white
                                                                  : AppTheme
                                                                        .ink,
                                                            ),
                                                          ),
                                                          Text(
                                                            att.formattedSize,
                                                            style: TextStyle(
                                                              fontSize: 10,
                                                              color: m.isMe
                                                                  ? Colors
                                                                        .white70
                                                                  : AppTheme
                                                                        .muted,
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
                                          }),
                                        ],

                                        // Message Text Body / Unsent message text
                                        if (m.body.isNotEmpty) ...[
                                          Text(
                                            m.body,
                                            style: TextStyle(
                                              color: m.isUnsent
                                                  ? const Color(0xFF64748B)
                                                  : (m.isMe
                                                      ? Colors.white
                                                      : AppTheme.ink),
                                              fontStyle: m.isUnsent
                                                  ? FontStyle.italic
                                                  : FontStyle.normal,
                                              fontSize: 13.5,
                                              height: 1.35,
                                            ),
                                          ),
                                        ],

                                        // Philippine Time timestamp + Unsend action
                                        Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            if (bubbleTime.isNotEmpty)
                                              Text(
                                                bubbleTime,
                                                style: TextStyle(
                                                  color: m.isMe && !m.isUnsent
                                                      ? Colors.white.withValues(
                                                          alpha: 0.75,
                                                        )
                                                      : const Color(0xFF94A3B8),
                                                  fontSize: 9.5,
                                                ),
                                              ),
                                            if (m.isMe && !m.isUnsent) ...[
                                              const SizedBox(width: 4),
                                              GestureDetector(
                                                onTap: () => _unsendMessage(m),
                                                child: Icon(
                                                  Icons.more_horiz_rounded,
                                                  size: 14,
                                                  color: Colors.white
                                                      .withValues(alpha: 0.7),
                                                ),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                                ],
                              ),
                            ),
                          ],
                        );
                      },
                    ),
            ),

            // Restricted contact banner or Message composer
            if (!_canSend)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 14,
                ),
                color: const Color(0xFFF8FAFC),
                child: const Text(
                  'Messaging is unavailable because this contact is no longer active or related to your account. Your history remains available.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    color: Color(0xFF64748B),
                    height: 1.4,
                  ),
                ),
              )
            else ...[
              // Attachment preview tray
              _buildAttachmentPreviewTray(),

              // Modern Input Bar with Attachment trigger & Character Counter
              Container(
                padding: const EdgeInsets.fromLTRB(10, 8, 12, 10),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: Color(0xFFEAECEF))),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    // Attachment button (Gallery, Camera, Documents - up to 3, 2MB each)
                    IconButton(
                    icon: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        const Icon(
                          Icons.attach_file_rounded,
                          color: AppTheme.muted,
                          size: 24,
                        ),
                        if (_selectedAttachments.isNotEmpty)
                          Positioned(
                            right: -4,
                            top: -4,
                            child: Container(
                              padding: const EdgeInsets.all(3),
                              decoration: const BoxDecoration(
                                color: AppTheme.primary,
                                shape: BoxShape.circle,
                              ),
                              child: Text(
                                '${_selectedAttachments.length}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 8.5,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                    tooltip: 'Attach photos or files (up to 3, 2MB each)',
                    onPressed: _showAttachmentPickerOptions,
                  ),

                  // Message text field with 2000 character restriction
                  Expanded(
                    child: Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          TextField(
                            controller: _msgCtrl,
                            maxLength: 2000,
                            maxLines: 4,
                            minLines: 1,
                            onChanged: (_) => setState(() {}),
                            textInputAction: TextInputAction.send,
                            onSubmitted: (_) => _send(),
                            decoration: const InputDecoration(
                              hintText: 'Type your message...',
                              hintStyle: TextStyle(
                                fontSize: 13,
                                color: AppTheme.muted,
                              ),
                              border: InputBorder.none,
                              enabledBorder: InputBorder.none,
                              focusedBorder: InputBorder.none,
                              counterText: '',
                              contentPadding: EdgeInsets.fromLTRB(
                                14,
                                10,
                                14,
                                6,
                              ),
                            ),
                          ),
                          // Subtle character counter if length > 50
                          if (_msgCtrl.text.length > 50)
                            Padding(
                              padding: const EdgeInsets.only(
                                right: 12,
                                bottom: 4,
                              ),
                              child: Text(
                                '${_msgCtrl.text.length}/2000',
                                style: TextStyle(
                                  fontSize: 9.5,
                                  color: _msgCtrl.text.length > 1900
                                      ? Colors.red
                                      : AppTheme.muted,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),

                  // Send button
                  InkWell(
                    onTap: _isSending ? null : _send,
                    borderRadius: BorderRadius.circular(22),
                    child: Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFFD63363), Color(0xFFBE2854)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: const Color(
                              0xFFBE2854,
                            ).withValues(alpha: 0.3),
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
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Icon(
                                Icons.send_rounded,
                                color: Colors.white,
                                size: 19,
                              ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    ),
  );
  }
}
