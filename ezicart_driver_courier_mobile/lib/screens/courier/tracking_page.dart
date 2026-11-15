import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/courier_provider.dart';
import 'order_details_page.dart';

class TrackingPage extends StatefulWidget {
  const TrackingPage({super.key});

  @override
  State<TrackingPage> createState() => _TrackingPageState();
}

class _TrackingPageState extends State<TrackingPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CourierProvider>().fetchTracking();
    });
  }

  @override
  Widget build(BuildContext context) {
    final courierProv = context.watch<CourierProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Active Parcels Tracking'),
      ),
      body: courierProv.isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : courierProv.trackingOrders.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.explore_off_rounded, size: 48, color: AppTheme.muted),
                      SizedBox(height: 12),
                      Text(
                        'No active parcels to track.',
                        style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.ink),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'You currently have no open pickup or delivery tasks.',
                        style: TextStyle(fontSize: 12, color: AppTheme.muted),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () => courierProv.fetchTracking(),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: courierProv.trackingOrders.length,
                    itemBuilder: (ctx, i) {
                      final order = courierProv.trackingOrders[i];
                      return Container(
                        margin: const EdgeInsets.only(bottom: 14),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: AppTheme.line),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  order.orderNumber,
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: AppTheme.primarySoft,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    order.statusLabel.toUpperCase(),
                                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.primary),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Recipient: ${order.recipientName} · ${order.deliveryAddress}',
                              style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                            ),
                            const SizedBox(height: 12),
                            const Divider(height: 1, color: AppTheme.line),
                            const SizedBox(height: 10),

                            // Tracking Events Timeline
                            if (order.trackingEvents.isEmpty)
                              const Text('No tracking events recorded yet.', style: TextStyle(fontSize: 12, color: AppTheme.muted))
                            else
                              Column(
                                children: order.trackingEvents.take(4).map((e) {
                                  return Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 4),
                                    child: Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Icon(Icons.circle, size: 7, color: AppTheme.primary),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Text(
                                            '${e.eventLabel} - ${e.location ?? 'Hub'} (${e.createdAt})',
                                            style: const TextStyle(fontSize: 12, color: AppTheme.ink),
                                          ),
                                        ),
                                      ],
                                    ),
                                  );
                                }).toList(),
                              ),

                            const SizedBox(height: 12),
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton.icon(
                                style: TextButton.styleFrom(
                                  foregroundColor: AppTheme.primary,
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                ),
                                icon: const Icon(Icons.arrow_forward_rounded, size: 16),
                                label: const Text('Open Details', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                onPressed: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => OrderDetailsPage(orderId: order.id, initialOrder: order)),
                                  );
                                },
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
