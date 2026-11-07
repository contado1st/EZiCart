import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../services/api_service.dart';
import 'disputes_list_page.dart';
import 'order_details_page.dart';

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
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'PLACED':
      case 'CONFIRMED':
      case 'PREPARING':
        return Colors.blue;
      case 'READY_FOR_PICKUP':
      case 'PICKED_UP':
      case 'AT_SORTING_CENTER':
      case 'SORTED':
      case 'ASSIGNED_TO_RIDER':
      case 'OUT_FOR_DELIVERY':
        return Colors.orange.shade800;
      case 'DELIVERED':
        return Colors.purple;
      case 'COMPLETED':
        return Colors.green;
      case 'CANCELLED':
      case 'RETURNED':
      case 'DELIVERY_FAILED':
        return Colors.red;
      default:
        return AppTheme.primary;
    }
  }

  void _confirmReceipt(OrderModel order) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm Receipt'),
        content: Text('Are you sure you have received your package for Order #${order.id}?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm Receipt')),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      final res = await ApiService.post(ApiConfig.confirmOrderEndpoint(order.id), {});
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Order completed! Thank you for confirming.'), backgroundColor: Colors.green),
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
        title: const Text('Request Cancellation'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Enter reason for cancellation before shipment:', style: TextStyle(fontSize: 13, color: AppTheme.muted)),
            const SizedBox(height: 10),
            TextField(controller: reasonCtrl, decoration: const InputDecoration(hintText: 'e.g. Changed my mind')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Back')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Submit Request'),
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
      appBar: AppBar(
        title: const Text('My Orders'),
        actions: [
          IconButton(
            icon: const Icon(Icons.shield_outlined),
            tooltip: 'Return & Dispute Cases',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const DisputesListPage()),
              );
            },
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          labelColor: AppTheme.primary,
          indicatorColor: AppTheme.primary,
          unselectedLabelColor: AppTheme.muted,
          tabAlignment: TabAlignment.start,
          tabs: List.generate(_tabs.length, (i) {
            final key = _tabs[i];
            final label = _tabLabels[i];
            final count = _counts[key];
            return Tab(
              child: Row(
                children: [
                  Text(label),
                  if (count != null && count > 0) ...[
                    const SizedBox(width: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
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
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _orders.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.inventory_outlined, size: 56, color: Colors.grey.shade400),
                        const SizedBox(height: 12),
                        const Text('No orders found in this tab', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        const SizedBox(height: 4),
                        const Text('Your purchase history will update in real-time.', style: TextStyle(color: AppTheme.muted, fontSize: 13)),
                      ],
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: _fetchOrders,
                    child: ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _orders.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) {
                        final order = _orders[i];
                        final statusColor = _getStatusColor(order.status);

                        return Card(
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                            side: const BorderSide(color: AppTheme.line),
                          ),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(12),
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
                                // Seller & Status Header
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Expanded(
                                      child: Row(
                                        children: [
                                          const Icon(Icons.storefront_outlined, size: 16, color: AppTheme.muted),
                                          const SizedBox(width: 6),
                                          Expanded(
                                            child: Text(
                                              order.sellerName,
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: statusColor.withValues(alpha: 0.12),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        order.statusLabel.toUpperCase(),
                                        style: TextStyle(
                                          color: statusColor,
                                          fontSize: 10,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const Divider(height: 18),

                                // Items summary
                                ...order.items.take(2).map((item) {
                                  return Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 2),
                                    child: Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            '${item.quantity}× ${item.name}',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(fontSize: 13),
                                          ),
                                        ),
                                        Text(currency.format(item.total), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                                      ],
                                    ),
                                  );
                                }),
                                if (order.items.length > 2)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 2),
                                    child: Text(
                                      '+ ${order.items.length - 2} more item(s)',
                                      style: const TextStyle(color: AppTheme.muted, fontSize: 11),
                                    ),
                                  ),

                                const Divider(height: 18),

                                // Total & Action buttons
                                Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          if (order.parcelCode != null)
                                            Text('Parcel: ${order.parcelCode}', style: const TextStyle(fontSize: 11, color: AppTheme.muted)),
                                          Text(
                                            'Total: ${currency.format(order.totalAmount)}',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.ink),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    if (order.status == 'DELIVERED')
                                      Padding(
                                        padding: const EdgeInsets.only(right: 8),
                                        child: ElevatedButton(
                                          style: ElevatedButton.styleFrom(
                                            backgroundColor: Colors.green,
                                            minimumSize: const Size(90, 34),
                                            padding: const EdgeInsets.symmetric(horizontal: 10),
                                          ),
                                          onPressed: () => _confirmReceipt(order),
                                          child: const Text('Received', style: TextStyle(fontSize: 12)),
                                        ),
                                      ),
                                    if (['PLACED', 'CONFIRMED', 'PREPARING'].contains(order.status))
                                      Padding(
                                        padding: const EdgeInsets.only(right: 8),
                                        child: OutlinedButton(
                                          style: OutlinedButton.styleFrom(
                                            foregroundColor: Colors.red,
                                            side: const BorderSide(color: Colors.red),
                                            minimumSize: const Size(70, 34),
                                            padding: const EdgeInsets.symmetric(horizontal: 8),
                                          ),
                                          onPressed: () => _cancelOrder(order),
                                          child: const Text('Cancel', style: TextStyle(fontSize: 12)),
                                        ),
                                      ),
                                    OutlinedButton(
                                      style: OutlinedButton.styleFrom(
                                        minimumSize: const Size(80, 34),
                                        padding: const EdgeInsets.symmetric(horizontal: 10),
                                        side: const BorderSide(color: AppTheme.line),
                                      ),
                                      onPressed: () {
                                        Navigator.of(context).push(
                                          MaterialPageRoute(
                                            builder: (_) => OrderDetailsPage(orderId: order.id),
                                          ),
                                        ).then((_) => _fetchOrders());
                                      },
                                      child: const Text('Details', style: TextStyle(fontSize: 12, color: AppTheme.ink)),
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
