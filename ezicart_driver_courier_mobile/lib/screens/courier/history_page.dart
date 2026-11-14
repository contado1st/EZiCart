import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/courier_provider.dart';
import 'widgets/order_card.dart';

class HistoryPage extends StatefulWidget {
  const HistoryPage({super.key});

  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  final _searchCtrl = TextEditingController();
  String? _selectedStatus;

  final List<String> _statuses = [
    'DELIVERED',
    'COMPLETED',
    'DELIVERY_FAILED',
    'RETURN_IN_TRANSIT',
    'RETURNED_TO_SELLER',
    'PICKED_UP',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _applyFilter();
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  void _applyFilter() {
    context.read<CourierProvider>().fetchHistory(
      search: _searchCtrl.text.trim().isNotEmpty ? _searchCtrl.text.trim() : null,
      status: _selectedStatus,
    );
  }

  @override
  Widget build(BuildContext context) {
    final courierProv = context.watch<CourierProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Work & Delivery History'),
      ),
      body: Column(
        children: [
          // Filter Bar
          Container(
            padding: const EdgeInsets.all(12),
            color: Colors.white,
            child: Column(
              children: [
                TextField(
                  controller: _searchCtrl,
                  decoration: InputDecoration(
                    hintText: 'Search order number (e.g. ORD-10293)...',
                    prefixIcon: const Icon(Icons.search_rounded, size: 20),
                    suffixIcon: _searchCtrl.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear_rounded, size: 18),
                            onPressed: () {
                              _searchCtrl.clear();
                              _applyFilter();
                            },
                          )
                        : null,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                  onSubmitted: (_) => _applyFilter(),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String?>(
                        initialValue: _selectedStatus,
                        decoration: const InputDecoration(
                          contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          isDense: true,
                        ),
                        items: [
                          const DropdownMenuItem<String?>(
                            value: null,
                            child: Text('All Statuses', style: TextStyle(fontSize: 12)),
                          ),
                          ..._statuses.map(
                            (s) => DropdownMenuItem<String?>(
                              value: s,
                              child: Text(s.replaceAll('_', ' '), style: const TextStyle(fontSize: 12)),
                            ),
                          ),
                        ],
                        onChanged: (val) {
                          setState(() => _selectedStatus = val);
                          _applyFilter();
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primary,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        minimumSize: Size.zero,
                      ),
                      onPressed: _applyFilter,
                      child: const Text('Filter', style: TextStyle(fontSize: 13, color: Colors.white)),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: AppTheme.line),

          // Orders List
          Expanded(
            child: courierProv.isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
                : courierProv.historyOrders.isEmpty
                    ? const Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.inventory_rounded, size: 48, color: AppTheme.muted),
                            SizedBox(height: 12),
                            Text(
                              'No past delivery records found.',
                              style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.ink),
                            ),
                            SizedBox(height: 4),
                            Text(
                              'Try adjusting your search or filters.',
                              style: TextStyle(fontSize: 12, color: AppTheme.muted),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () async => _applyFilter(),
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: courierProv.historyOrders.length,
                          itemBuilder: (ctx, i) {
                            final order = courierProv.historyOrders[i];
                            return OrderCard(order: order);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}
