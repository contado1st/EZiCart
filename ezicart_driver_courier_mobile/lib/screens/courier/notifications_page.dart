import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/notification_model.dart';
import '../../services/api_service.dart';
import 'order_details_page.dart';

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key});

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<String> _categories = ['all', 'orders', 'messages', 'account'];
  final List<String> _categoryLabels = ['All', 'Orders', 'Messages', 'Account'];

  List<NotificationModel> _notifications = [];
  bool _isLoading = true;
  int _unreadCount = 0;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _categories.length, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        _fetchNotifications();
      }
    });
    _fetchNotifications();
    _fetchUnreadCount();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchUnreadCount() async {
    try {
      final res = await ApiService.get(ApiConfig.notificationsUnreadCountEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            _unreadCount = (data['count'] is num) ? (data['count'] as num).toInt() : 0;
          });
        }
      }
    } catch (_) {}
  }

  Future<void> _fetchNotifications() async {
    setState(() => _isLoading = true);
    final cat = _categories[_tabController.index];
    final query = cat == 'all' ? '' : '?category=$cat';

    try {
      final res = await ApiService.get('${ApiConfig.notificationsEndpoint}$query');
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((n) => NotificationModel.fromJson(n as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _notifications = list;
              _isLoading = false;
            });
            return;
          }
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _markRead(NotificationModel notification) async {
    if (notification.isRead) return;

    try {
      await ApiService.post(ApiConfig.markNotificationReadEndpoint(notification.id), {});
      setState(() {
        final idx = _notifications.indexWhere((n) => n.id == notification.id);
        if (idx != -1) {
          _notifications[idx] = _notifications[idx].copyWith(isRead: true);
          if (_unreadCount > 0) _unreadCount--;
        }
      });
    } catch (_) {}
  }

  Future<void> _markAllRead() async {
    try {
      final res = await ApiService.post(ApiConfig.markAllNotificationsReadEndpoint, {});
      if (res.statusCode == 200) {
        setState(() {
          _notifications = _notifications.map((n) => n.copyWith(isRead: true)).toList();
          _unreadCount = 0;
        });
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('All notifications marked as read')),
          );
        }
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            const Text('Notifications', style: TextStyle(fontWeight: FontWeight.bold)),
            if (_unreadCount > 0) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppTheme.primary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '$_unreadCount',
                  style: const TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.done_all_rounded),
            tooltip: 'Mark all as read',
            onPressed: _notifications.any((n) => !n.isRead) ? _markAllRead : null,
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppTheme.primary,
          unselectedLabelColor: AppTheme.muted,
          indicatorColor: AppTheme.primary,
          indicatorWeight: 3,
          tabs: _categoryLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: () async {
                await _fetchNotifications();
                await _fetchUnreadCount();
              },
              child: _notifications.isEmpty
                  ? const Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.notifications_off_outlined, size: 48, color: AppTheme.muted),
                          SizedBox(height: 12),
                          Text('No notifications', style: TextStyle(fontWeight: FontWeight.bold)),
                          SizedBox(height: 4),
                          Text('Alerts and updates will appear here.', style: TextStyle(fontSize: 12, color: AppTheme.muted)),
                        ],
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _notifications.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (ctx, i) {
                        final n = _notifications[i];
                        return _buildNotificationCard(n);
                      },
                    ),
            ),
    );
  }

  Widget _buildNotificationCard(NotificationModel n) {
    IconData icon;
    Color iconColor;

    switch (n.category) {
      case 'orders':
        icon = Icons.local_shipping_outlined;
        iconColor = AppTheme.primary;
        break;
      case 'messages':
        icon = Icons.chat_bubble_outline_rounded;
        iconColor = Colors.blue;
        break;
      default:
        icon = Icons.notifications_none_rounded;
        iconColor = AppTheme.ink;
    }

    return Card(
      elevation: 0,
      color: n.isRead ? Colors.white : const Color(0xFFFFF7F9),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: n.isRead ? AppTheme.line : AppTheme.primarySoft),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          _markRead(n);
          if (n.orderId != null) {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => OrderDetailsPage(orderId: n.orderId!)),
            );
          }
        },
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: iconColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, color: iconColor, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Text(
                            n.title,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: n.isRead ? FontWeight.w600 : FontWeight.bold,
                              color: AppTheme.ink,
                            ),
                          ),
                        ),
                        if (!n.isRead)
                          Container(
                            width: 8,
                            height: 8,
                            margin: const EdgeInsets.only(left: 6),
                            decoration: const BoxDecoration(
                              color: AppTheme.primary,
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      n.message,
                      style: const TextStyle(fontSize: 12, color: AppTheme.muted, height: 1.3),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      n.createdAt.length >= 16 ? n.createdAt.substring(0, 16).replaceAll('T', ' ') : n.createdAt,
                      style: const TextStyle(fontSize: 10, color: AppTheme.muted),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
