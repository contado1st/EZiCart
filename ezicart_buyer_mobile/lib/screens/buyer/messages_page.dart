import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/chat_model.dart';
import '../../services/api_service.dart';
import '../../utils/philippine_time.dart';
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
    required this.sizeInBytes,
  }) : bytes = null;

  Future<Uint8List> readAsBytes() async {
    if (bytes != null) return bytes!;
    if (path != null && path!.isNotEmpty) {
      return await File(path!).readAsBytes();
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

    _loadMessages();
  }

  @override
  void dispose() {
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

  Future<void> _loadMessages() async {
    try {
      final res = await ApiService.get(
        ApiConfig.chatConversationEndpoint(widget.conversationId),
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final data = body['data'];
        final partner = data?['partner'];

        if (partner != null && mounted) {
          setState(() {
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
          });
        }

        final rawMessages = data?['messages'];
        if (rawMessages is List && mounted) {
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

      // 1. Try pickMultiImage first
      try {
        images = await picker.pickMultiImage();
      } catch (e) {
        debugPrint('pickMultiImage not supported or failed: $e');
      }

      // 2. If multi-image picker returned empty, fallback to standard pickImage
      if (images.isEmpty) {
        try {
          final single = await picker.pickImage(
            source: ImageSource.gallery,
            maxWidth: 1920,
            maxHeight: 1920,
            imageQuality: 85,
          );
          if (single != null) {
            images = [single];
          }
        } catch (e) {
          debugPrint('pickImage gallery failed: $e');
        }
      }

      // 3. If still empty, fallback to FilePicker with image type
      if (images.isEmpty) {
        try {
          final files = await FilePicker.pickFiles(type: FileType.image);
          if (files.isNotEmpty) {
            for (final f in files) {
              if (f.path != null && f.path!.isNotEmpty) {
                images.add(XFile(f.path!, name: f.name));
              }
            }
          }
        } catch (e) {
          debugPrint('FilePicker fallback failed: $e');
        }
      }

      if (images.isNotEmpty) {
        final remaining = 3 - _selectedAttachments.length;
        int added = 0;
        for (final img in images) {
          if (added >= remaining) break;
          final length = await img.length();
          if (length > 2 * 1024 * 1024) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('File "${img.name}" exceeds the 2MB limit.'),
                  backgroundColor: Colors.red.shade700,
                ),
              );
            }
            continue;
          }
          final ext = img.name.contains('.') ? img.name.split('.').last : 'jpg';
          _selectedAttachments.add(
            _LocalAttachment(
              name: img.name,
              extension: ext,
              path: img.path,
              sizeInBytes: length,
            ),
          );
          added++;
        }
        if (mounted) setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Error selecting images: $e')));
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
        final length = await photo.length();
        if (length > 2 * 1024 * 1024) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Photo "${photo.name}" exceeds the 2MB limit.'),
                backgroundColor: Colors.red.shade700,
              ),
            );
          }
          return;
        }
        final ext = photo.name.contains('.')
            ? photo.name.split('.').last
            : 'jpg';
        _selectedAttachments.add(
          _LocalAttachment(
            name: photo.name,
            extension: ext,
            path: photo.path,
            sizeInBytes: length,
          ),
        );
        if (mounted) setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Error opening camera: $e')));
      }
    }
  }

  Future<void> _pickDocuments() async {
    try {
      final List<PlatformFile> files = await FilePicker.pickFiles(
        type: FileType.any,
      );
      if (files.isNotEmpty) {
        final remaining = 3 - _selectedAttachments.length;
        int added = 0;
        for (final file in files) {
          if (added >= remaining) break;
          int size = 0;
          try {
            size = file.lengthSync() ?? (await file.length()) ?? 0;
          } catch (_) {
            if (file.path != null && file.path!.isNotEmpty) {
              try {
                size = File(file.path!).lengthSync();
              } catch (_) {}
            }
          }
          if (size > 2 * 1024 * 1024) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('File "${file.name}" exceeds the 2MB limit.'),
                  backgroundColor: Colors.red.shade700,
                ),
              );
            }
            continue;
          }
          _selectedAttachments.add(
            _LocalAttachment(
              name: file.name,
              extension: file.extension,
              path: file.path,
              sizeInBytes: size,
            ),
          );
          added++;
        }
        if (mounted) setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Error picking file: $e')));
      }
    }
  }

  void _removeAttachment(int index) {
    setState(() {
      _selectedAttachments.removeAt(index);
    });
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
          'body': text,
          if (widget.orderId != null) 'order_id': '${widget.orderId}',
        };

        final files = <http.MultipartFile>[];
        for (final att in _selectedAttachments) {
          if (att.path != null && att.path!.isNotEmpty) {
            files.add(
              await http.MultipartFile.fromPath(
                'attachments[]',
                att.path!,
                filename: att.name,
              ),
            );
          } else {
            final bytes = await att.readAsBytes();
            files.add(
              http.MultipartFile.fromBytes(
                'attachments[]',
                bytes,
                filename: att.name,
              ),
            );
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
          },
        );
      }

      if (res.statusCode == 201) {
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
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Failed to send message. Please retry.'),
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
                  imageUrl: imageUrl,
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

                                  // Bubble content
                                  Container(
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
                                      gradient: m.isMe
                                          ? const LinearGradient(
                                              colors: [
                                                Color(0xFFD63363),
                                                Color(0xFFBE2854),
                                              ],
                                              begin: Alignment.topLeft,
                                              end: Alignment.bottomRight,
                                            )
                                          : null,
                                      color: m.isMe ? null : Colors.white,
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
                                      border: m.isMe
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
                                        // Attachments if present
                                        if (m.attachments.isNotEmpty) ...[
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
                                                        imageUrl: att.url,
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
                                              return Container(
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
                                              );
                                            }
                                          }),
                                        ],

                                        // Message Text Body
                                        if (m.body.isNotEmpty) ...[
                                          Text(
                                            m.body,
                                            style: TextStyle(
                                              color: m.isMe
                                                  ? Colors.white
                                                  : AppTheme.ink,
                                              fontSize: 13.5,
                                              height: 1.35,
                                            ),
                                          ),
                                        ],

                                        // Philippine Time timestamp
                                        if (bubbleTime.isNotEmpty) ...[
                                          const SizedBox(height: 3),
                                          Text(
                                            bubbleTime,
                                            style: TextStyle(
                                              color: m.isMe
                                                  ? Colors.white.withValues(
                                                      alpha: 0.75,
                                                    )
                                                  : const Color(0xFF94A3B8),
                                              fontSize: 9.5,
                                            ),
                                          ),
                                        ],
                                      ],
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
        ),
      ),
    );
  }
}
