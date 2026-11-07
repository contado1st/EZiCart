import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/notification_model.dart';
import '../../models/product_model.dart';
import '../../services/api_service.dart';
import 'messages_page.dart';
import 'order_details_page.dart';
import 'product_details_page.dart';
import 'stock_watchlist_page.dart';

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key});

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<String> _categories = ['all', 'orders', 'messages', 'promotions', 'stock'];
  final List<String> _tabLabels = ['All', 'Orders', 'Messages', 'Promos', 'Stock'];

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
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchNotifications() async {
    setState(() => _isLoading = true);
    final category = _categories[_tabController.index];
    final url = '${ApiConfig.notificationsEndpoint}?category=$category';

    try {
      final res = await ApiService.get(url);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((e) => NotificationModel.fromJson(e as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _notifications = list;
              _unreadCount = body['unread_count'] is int ? body['unread_count'] : 0;
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

  Future<void> _markAllAsRead() async {
    try {
      final res = await ApiService.post(ApiConfig.notificationsReadAllEndpoint, {});
      if (res.statusCode == 200 && mounted) {
        setState(() {
          _notifications = _notifications.map((n) => n.copyWith(isRead: true)).toList();
          _unreadCount = 0;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('All notifications marked as read.')),
        );
      }
    } catch (_) {}
  }

  Future<void> _onNotificationTap(NotificationModel item) async {
    if (!item.isRead) {
      // Mark as read in background
      ApiService.post(ApiConfig.notificationReadEndpoint(item.id), {});
      setState(() {
        final idx = _notifications.indexWhere((n) => n.id == item.id);
        if (idx != -1) {
          _notifications[idx] = item.copyWith(isRead: true);
          if (_unreadCount > 0) _unreadCount--;
        }
      });
    }

    // Action routing
    if (item.orderId != null) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => OrderDetailsPage(orderId: item.orderId!)),
      );
    } else if (item.conversationId != null) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ChatConversationScreen(
            conversationId: item.conversationId!,
            partnerName: 'Direct Chat',
          ),
        ),
      );
    } else if (item.productId != null) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ProductDetailsPage(
            product: ProductModel(
              id: item.productId!,
              name: item.title,
              description: '',
              category: 'General',
              price: 0,
              stock: 1,
              sellerName: 'Merchant',
              variations: [],
            ),
          ),
        ),
      );
    } else if (item.category == 'stock') {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const StockWatchlistPage()),
      );
    }
  }

  IconData _iconForCategory(String category) {
    switch (category) {
      case 'orders':
        return Icons.local_shipping_outlined;
      case 'messages':
        return Icons.chat_outlined;
      case 'promotions':
        return Icons.local_offer_outlined;
      case 'stock':
        return Icons.inventory_2_outlined;
      default:
        return Icons.notifications_none_outlined;
    }
  }

  Color _colorForCategory(String category) {
    switch (category) {
      case 'orders':
        return AppTheme.primary;
      case 'messages':
        return Colors.blue;
      case 'promotions':
        return Colors.deepOrange;
      case 'stock':
        return Colors.teal;
      default:
        return Colors.grey.shade700;
    }
  }

  String _formatTimestamp(String ts) {
    try {
      final dt = DateTime.parse(ts).toLocal();
      return DateFormat('MMM d, h:mm a').format(dt);
    } catch (_) {
      return ts;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            const Text('Notifications'),
            if (_unreadCount > 0) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.redAccent,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '$_unreadCount new',
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ],
        ),
        actions: [
          if (_unreadCount > 0)
            TextButton.icon(
              icon: const Icon(Icons.done_all, size: 18),
              label: const Text('Read All'),
              onPressed: _markAllAsRead,
            ),
        ],
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          labelColor: AppTheme.primary,
          indicatorColor: AppTheme.primary,
          unselectedLabelColor: AppTheme.muted,
          tabs: _tabLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _fetchNotifications,
              child: _notifications.isEmpty
                  ? Center(
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(32),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.notifications_off_outlined, size: 56, color: Colors.grey.shade300),
                            const SizedBox(height: 12),
                            const Text(
                              'No notifications here',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'Order alerts, delivery updates, seller discounts, and restock notices will appear here.',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: AppTheme.muted, fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      itemCount: _notifications.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final item = _notifications[index];
                        final catColor = _colorForCategory(item.category);

                        return ListTile(
                          tileColor: item.isRead ? Colors.transparent : AppTheme.primarySoft.withValues(alpha: 0.35),
                          onTap: () => _onNotificationTap(item),
                          leading: Stack(
                            children: [
                              CircleAvatar(
                                backgroundColor: catColor.withValues(alpha: 0.12),
                                child: Icon(_iconForCategory(item.category), color: catColor, size: 22),
                              ),
                              if (!item.isRead)
                                Positioned(
                                  top: 0,
                                  right: 0,
                                  child: Container(
                                    width: 10,
                                    height: 10,
                                    decoration: const BoxDecoration(
                                      color: Colors.redAccent,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                          title: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  item.title,
                                  style: TextStyle(
                                    fontWeight: item.isRead ? FontWeight.w600 : FontWeight.bold,
                                    fontSize: 14,
                                    color: AppTheme.ink,
                                  ),
                                ),
                              ),
                              Text(
                                _formatTimestamp(item.createdAt),
                                style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                              ),
                            ],
                          ),
                          subtitle: Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text(
                              item.message,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: item.isRead ? AppTheme.muted : AppTheme.ink,
                                fontSize: 13,
                              ),
                            ),
                          ),
                          trailing: const Icon(Icons.chevron_right, size: 18, color: AppTheme.muted),
                        );
                      },
                    ),
            ),
    );
  }
}

