import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../services/api_service.dart';
import 'disputes_list_page.dart';
import 'order_details_page.dart';
import 'shop_page.dart';

class OrdersPage extends StatefulWidget {
  const OrdersPage({super.key});

  @override
  State<OrdersPage> createState() => _OrdersPageState();
}

class _OrdersPageState extends State<OrdersPage> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<String> _tabs = ['all', 'to_ship', 'to_receive', 'completed', 'cancelled'];
  final List<String> _tabLabels = ['All', 'To Ship', 'To Receive', 'Completed', 'Cancelled'];

  List<OrderModel> _orders = [];
  Map<String, int> _counts = {};
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        _fetchOrders();
      }
    });
    _fetchOrders();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchOrders() async {
    setState(() => _isLoading = true);
    final activeTab = _tabs[_tabController.index];

    try {
      final res = await ApiService.get('${ApiConfig.ordersEndpoint}?tab=$activeTab');
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final list = (body['data'] is List)
            ? (body['data'] as List).map((o) => OrderModel.fromJson(o as Map<String, dynamic>)).toList()
            : <OrderModel>[];
        if (mounted) {
          setState(() {
            _orders = list;
            if (body['counts'] is Map) {
              _counts = (body['counts'] as Map).map(
                (k, v) => MapEntry(k.toString(), (v is num) ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0),
              );
            }
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'PLACED':
      case 'CONFIRMED':
      case 'PREPARING':
        return const Color(0xFF2563EB); // Vibrant Blue
      case 'READY_FOR_PICKUP':
      case 'PICKED_UP':
      case 'AT_SORTING_CENTER':
      case 'SORTED':
      case 'ASSIGNED_TO_RIDER':
      case 'OUT_FOR_DELIVERY':
        return const Color(0xFFD97706); // Amber / Orange
      case 'DELIVERED':
        return const Color(0xFF7C3AED); // Purple
      case 'COMPLETED':
        return const Color(0xFF16A34A); // Emerald Green
      case 'CANCELLED':
      case 'RETURNED':
      case 'DELIVERY_FAILED':
        return const Color(0xFFDC2626); // Rose Red
      default:
        return AppTheme.primary;
    }
  }

  IconData _getStatusIcon(String status) {
    switch (status) {
      case 'PLACED':
      case 'CONFIRMED':
        return Icons.receipt_long_outlined;
      case 'PREPARING':
        return Icons.inventory_2_outlined;
      case 'READY_FOR_PICKUP':
      case 'PICKED_UP':
      case 'AT_SORTING_CENTER':
      case 'SORTED':
        return Icons.warehouse_outlined;
      case 'ASSIGNED_TO_RIDER':
      case 'OUT_FOR_DELIVERY':
        return Icons.local_shipping_outlined;
      case 'DELIVERED':
        return Icons.mark_email_read_outlined;
      case 'COMPLETED':
        return Icons.check_circle_outline;
      case 'CANCELLED':
      case 'RETURNED':
      case 'DELIVERY_FAILED':
        return Icons.cancel_outlined;
      default:
        return Icons.info_outline;
    }
  }

  void _confirmReceipt(OrderModel order) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.check_circle, color: Color(0xFF16A34A), size: 24),
            SizedBox(width: 8),
            Text('Confirm Order Receipt', style: TextStyle(fontSize: 16)),
          ],
        ),
        content: Text('Have you received your package for Order #${order.id} in good condition?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Not Yet')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF16A34A),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Yes, Received'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      final res = await ApiService.post(ApiConfig.confirmOrderEndpoint(order.id), {});
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Order completed! Thank you for confirming.'), backgroundColor: Color(0xFF16A34A)),
        );
        _fetchOrders();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  void _cancelOrder(OrderModel order) async {
    final reasonCtrl = TextEditingController();
    final proceed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Cancel Order', style: TextStyle(fontSize: 16)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Please tell us why you want to cancel this order:', style: TextStyle(fontSize: 12.5, color: AppTheme.muted)),
            const SizedBox(height: 10),
            TextField(
              controller: reasonCtrl,
              maxLines: 2,
              decoration: const InputDecoration(hintText: 'e.g. Changed my mind, need different item...'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Back')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Confirm Cancel'),
          ),
        ],
      ),
    );

    if (proceed != true) return;

    try {
      final res = await ApiService.post(ApiConfig.cancelOrderEndpoint(order.id), {
        'reason': reasonCtrl.text.trim().isNotEmpty ? reasonCtrl.text.trim() : 'Buyer requested cancellation.',
      });
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Cancellation request sent to seller.'), backgroundColor: Colors.orange),
        );
        _fetchOrders();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB), // Clean modern light canvas
      appBar: AppBar(
        title: const Text(
          'My Orders',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.shield_outlined, color: AppTheme.ink),
            tooltip: 'Return & Dispute Cases',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const DisputesListPage()),
              );
            },
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: TabBar(
              controller: _tabController,
              isScrollable: true,
              labelColor: AppTheme.primary,
              indicatorColor: AppTheme.primary,
              indicatorWeight: 2.5,
              indicatorSize: TabBarIndicatorSize.label,
              unselectedLabelColor: AppTheme.muted,
              tabAlignment: TabAlignment.start,
              labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13),
              tabs: List.generate(_tabs.length, (i) {
                final key = _tabs[i];
                final label = _tabLabels[i];
                final count = _counts[key];
                return Tab(
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(label),
                      if (count != null && count > 0) ...[
                        const SizedBox(width: 5),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                          decoration: BoxDecoration(
                            color: AppTheme.primarySoft,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '$count',
                            style: const TextStyle(fontSize: 10, color: AppTheme.primary, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ],
                  ),
                );
              }),
            ),
          ),
        ),
      ),
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _orders.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 32),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.04),
                                  blurRadius: 12,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Icon(Icons.receipt_long_outlined, size: 52, color: Colors.grey.shade400),
                          ),
                          const SizedBox(height: 16),
                          const Text(
                            'No orders found',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.ink),
                          ),
                          const SizedBox(height: 6),
                          const Text(
                            'Orders in this category will appear here in real time once placed.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: AppTheme.muted, fontSize: 13, height: 1.3),
                          ),
                        ],
                      ),
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: _fetchOrders,
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                      itemCount: _orders.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) {
                        final order = _orders[i];
                        final statusColor = _getStatusColor(order.status);
                        final statusIcon = _getStatusIcon(order.status);
                        final totalQuantity = order.items.fold<int>(0, (sum, item) => sum + item.quantity);

                        return Container(
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: const Color(0xFFEAECEF)),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.02),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(14),
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => OrderDetailsPage(orderId: order.id),
                                ),
                              ).then((_) => _fetchOrders());
                            },
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Header: Shop Name & Status Badge
                                  Row(
                                    children: [
                                      InkWell(
                                        onTap: (order.sellerId != null)
                                            ? () {
                                                Navigator.of(context).push(
                                                  MaterialPageRoute(
                                                    builder: (_) => ShopPage(
                                                      sellerId: order.sellerId!,
                                                      initialShopName: order.sellerName,
                                                    ),
                                                  ),
                                                );
                                              }
                                            : null,
                                        borderRadius: BorderRadius.circular(6),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const Icon(Icons.storefront_outlined, size: 16, color: AppTheme.ink),
                                            const SizedBox(width: 6),
                                            Text(
                                              order.sellerName,
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: AppTheme.ink),
                                            ),
                                            const SizedBox(width: 4),
                                            const Icon(Icons.chevron_right, size: 16, color: AppTheme.muted),
                                          ],
                                        ),
                                      ),
                                      const Spacer(),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3.5),
                                        decoration: BoxDecoration(
                                          color: statusColor.withValues(alpha: 0.10),
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Icon(statusIcon, size: 12, color: statusColor),
                                            const SizedBox(width: 4),
                                            Text(
                                              order.statusLabel.toUpperCase(),
                                              style: TextStyle(
                                                color: statusColor,
                                                fontSize: 10,
                                                fontWeight: FontWeight.bold,
                                                letterSpacing: 0.3,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  const Divider(height: 1, color: Color(0xFFF1F3F6)),
                                  const SizedBox(height: 12),

                                  // Primary Order Items Preview (Thumbnails + Names)
                                  ...order.items.take(2).map((item) {
                                    final imgUrl = item.imagePath != null ? ApiConfig.resolveImageUrl(item.imagePath) : null;
                                    return Padding(
                                      padding: const EdgeInsets.only(bottom: 10),
                                      child: Row(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          // Product Thumbnail
                                          Container(
                                            width: 54,
                                            height: 54,
                                            decoration: BoxDecoration(
                                              color: const Color(0xFFF8F9FA),
                                              borderRadius: BorderRadius.circular(8),
                                              border: Border.all(color: const Color(0xFFE9ECEF)),
                                            ),
                                            child: ClipRRect(
                                              borderRadius: BorderRadius.circular(8),
                                              child: imgUrl != null
                                                  ? CachedNetworkImage(
                                                      imageUrl: imgUrl,
                                                      fit: BoxFit.cover,
                                                      placeholder: (_, __) => const Center(
                                                        child: SizedBox(
                                                          width: 14,
                                                          height: 14,
                                                          child: CircularProgressIndicator(strokeWidth: 2),
                                                        ),
                                                      ),
                                                      errorWidget: (_, __, ___) => const Icon(
                                                        Icons.inventory_2_outlined,
                                                        color: AppTheme.muted,
                                                        size: 20,
                                                      ),
                                                    )
                                                  : const Icon(
                                                      Icons.inventory_2_outlined,
                                                      color: AppTheme.muted,
                                                      size: 20,
                                                    ),
                                            ),
                                          ),
                                          const SizedBox(width: 10),

                                          // Product Details
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  item.name,
                                                  maxLines: 1,
                                                  overflow: TextOverflow.ellipsis,
                                                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppTheme.ink),
                                                ),
                                                if (item.variationInfo != null && item.variationInfo!.isNotEmpty) ...[
                                                  const SizedBox(height: 2),
                                                  Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                                    decoration: BoxDecoration(
                                                      color: const Color(0xFFF1F5F9),
                                                      borderRadius: BorderRadius.circular(4),
                                                    ),
                                                    child: Text(
                                                      item.variationInfo!,
                                                      style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                                                    ),
                                                  ),
                                                ],
                                                const SizedBox(height: 4),
                                                Row(
                                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                  children: [
                                                    Text(
                                                      currency.format(item.price),
                                                      style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.bold, color: AppTheme.ink),
                                                    ),
                                                    Text(
                                                      '×${item.quantity}',
                                                      style: const TextStyle(fontSize: 12, color: AppTheme.muted, fontWeight: FontWeight.w500),
                                                    ),
                                                  ],
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                    );
                                  }),

                                  if (order.items.length > 2)
                                    Padding(
                                      padding: const EdgeInsets.only(bottom: 8),
                                      child: Text(
                                        'View ${order.items.length - 2} more item(s)...',
                                        style: const TextStyle(color: AppTheme.muted, fontSize: 11.5, fontWeight: FontWeight.w500),
                                      ),
                                    ),

                                  // Tracking / Parcel code snippet if present
                                  if (order.parcelCode != null) ...[
                                    Container(
                                      margin: const EdgeInsets.only(top: 2, bottom: 8),
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFF8FAFC),
                                        borderRadius: BorderRadius.circular(6),
                                        border: Border.all(color: const Color(0xFFE2E8F0)),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          const Icon(Icons.local_shipping_outlined, size: 13, color: Color(0xFF64748B)),
                                          const SizedBox(width: 5),
                                          Text(
                                            'Tracking: ${order.parcelCode}',
                                            style: const TextStyle(fontSize: 11, color: Color(0xFF475569), fontWeight: FontWeight.w500),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],

                                  const Divider(height: 1, color: Color(0xFFF1F3F6)),
                                  const SizedBox(height: 10),

                                  // Footer: Total Amount & Action Buttons
                                  Row(
                                    children: [
                                      Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            '$totalQuantity item${totalQuantity > 1 ? "s" : ""}, Order Total:',
                                            style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                                          ),
                                          Text(
                                            currency.format(order.totalAmount),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                              fontSize: 15.5,
                                              color: AppTheme.primary,
                                            ),
                                          ),
                                        ],
                                      ),
                                      const Spacer(),

                                      // Actions
                                      if (order.status == 'DELIVERED')
                                        Padding(
                                          padding: const EdgeInsets.only(right: 8),
                                          child: ElevatedButton.icon(
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: const Color(0xFF16A34A),
                                              foregroundColor: Colors.white,
                                              minimumSize: const Size(96, 34),
                                              padding: const EdgeInsets.symmetric(horizontal: 10),
                                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                              elevation: 0,
                                            ),
                                            icon: const Icon(Icons.check, size: 15),
                                            label: const Text('Received', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                            onPressed: () => _confirmReceipt(order),
                                          ),
                                        ),

                                      if (['PLACED', 'CONFIRMED', 'PREPARING'].contains(order.status))
                                        Padding(
                                          padding: const EdgeInsets.only(right: 8),
                                          child: OutlinedButton(
                                            style: OutlinedButton.styleFrom(
                                              foregroundColor: const Color(0xFFDC2626),
                                              side: const BorderSide(color: Color(0xFFFCA5A5)),
                                              minimumSize: const Size(74, 34),
                                              padding: const EdgeInsets.symmetric(horizontal: 10),
                                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                            ),
                                            onPressed: () => _cancelOrder(order),
                                            child: const Text('Cancel', style: TextStyle(fontSize: 12)),
                                          ),
                                        ),

                                      OutlinedButton(
                                        style: OutlinedButton.styleFrom(
                                          foregroundColor: AppTheme.ink,
                                          side: const BorderSide(color: Color(0xFFD1D5DB)),
                                          minimumSize: const Size(76, 34),
                                          padding: const EdgeInsets.symmetric(horizontal: 10),
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                        ),
                                        onPressed: () {
                                          Navigator.of(context).push(
                                            MaterialPageRoute(
                                              builder: (_) => OrderDetailsPage(orderId: order.id),
                                            ),
                                          ).then((_) => _fetchOrders());
                                        },
                                        child: const Text('Details', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                      ),
                                    ],
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
    );
  }
}
