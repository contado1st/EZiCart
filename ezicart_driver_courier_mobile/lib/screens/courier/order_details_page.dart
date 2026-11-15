import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../providers/courier_provider.dart';
import 'order_messages_page.dart';
import 'remittance_modal.dart';
import 'widgets/action_scan_dialog.dart';
import 'widgets/complete_delivery_dialog.dart';
import 'widgets/fail_delivery_dialog.dart';

class OrderDetailsPage extends StatefulWidget {
  final int orderId;
  final OrderModel? initialOrder;

  const OrderDetailsPage({super.key, required this.orderId, this.initialOrder});

  @override
  State<OrderDetailsPage> createState() => _OrderDetailsPageState();
}

class _OrderDetailsPageState extends State<OrderDetailsPage> {
  OrderModel? _order;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _order = widget.initialOrder;
    _loadOrder();
  }

  Future<void> _loadOrder() async {
    setState(() => _isLoading = true);
    final courierProv = context.read<CourierProvider>();
    final loaded = await courierProv.fetchOrderDetails(widget.orderId);
    if (mounted) {
      setState(() {
        if (loaded != null) _order = loaded;
        _isLoading = false;
      });
    }
  }

  Future<void> _makeCall(String? phone) async {
    if (phone == null || phone.isEmpty) return;
    final uri = Uri.parse('tel:${phone.replaceAll(RegExp(r'\s+'), '')}');
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'READY_FOR_PICKUP':
        return AppTheme.warning;
      case 'PICKED_UP':
        return AppTheme.info;
      case 'ASSIGNED_TO_RIDER':
        return const Color(0xFFF97316);
      case 'OUT_FOR_DELIVERY':
        return AppTheme.primary;
      case 'DELIVERED':
      case 'COMPLETED':
        return AppTheme.success;
      case 'DELIVERY_FAILED':
        return AppTheme.danger;
      case 'RETURN_IN_TRANSIT':
        return Colors.purple;
      default:
        return AppTheme.muted;
    }
  }

  @override
  Widget build(BuildContext context) {
    final courierProv = context.watch<CourierProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text(_order?.orderNumber ?? 'Order #${widget.orderId}'),
        actions: [
          IconButton(
            icon: const Icon(Icons.chat_outlined),
            tooltip: 'Order Messages',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => OrderMessagesPage(
                    orderId: widget.orderId,
                    orderNumber: _order?.orderNumber ?? 'ORD-${widget.orderId}',
                  ),
                ),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _loadOrder,
          ),
        ],
      ),
      body: _isLoading && _order == null
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : _order == null
              ? const Center(child: Text('Order not found.'))
              : RefreshIndicator(
                  onRefresh: _loadOrder,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Status & Alert Banner
                        _buildStatusHeader(_order!),
                        const SizedBox(height: 14),

                        // Action Panel based on state
                        _buildActionPanel(_order!, courierProv),
                        const SizedBox(height: 16),

                        // Destination & Buyer Info
                        _buildSectionCard(
                          title: 'Drop-off Destination',
                          icon: Icons.location_on_rounded,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _order!.recipientName,
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                              ),
                              const SizedBox(height: 4),
                              Text(_order!.deliveryAddress, style: const TextStyle(fontSize: 13, color: AppTheme.muted)),
                              if (_order!.recipientContact != null && _order!.recipientContact!.isNotEmpty) ...[
                                const SizedBox(height: 8),
                                OutlinedButton.icon(
                                  onPressed: () => _makeCall(_order!.recipientContact),
                                  icon: const Icon(Icons.phone_rounded, size: 16),
                                  label: Text('Call ${_order!.recipientContact!}'),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: AppTheme.primary,
                                    side: const BorderSide(color: AppTheme.primary),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Seller Info
                        _buildSectionCard(
                          title: 'Seller Pickup Point',
                          icon: Icons.store_rounded,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _order!.sellerName,
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                              ),
                              const SizedBox(height: 4),
                              Text(_order!.sellerAddress, style: const TextStyle(fontSize: 13, color: AppTheme.muted)),
                              if (_order!.sellerContact != null && _order!.sellerContact!.isNotEmpty) ...[
                                const SizedBox(height: 8),
                                OutlinedButton.icon(
                                  onPressed: () => _makeCall(_order!.sellerContact),
                                  icon: const Icon(Icons.phone_rounded, size: 16),
                                  label: Text('Call ${_order!.sellerContact!}'),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: AppTheme.primary,
                                    side: const BorderSide(color: AppTheme.primary),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Payment & Parcel Details
                        _buildSectionCard(
                          title: 'Payment & Package Details',
                          icon: Icons.inventory_2_outlined,
                          child: Column(
                            children: [
                              _infoRow(
                                'Payment Method',
                                _order!.paymentMethod,
                                isHighlight: true,
                                highlightColor: _order!.isCod ? const Color(0xFFB45309) : const Color(0xFF15803D),
                              ),
                              _infoRow('Total Amount', '₱${_order!.totalAmount.toStringAsFixed(2)}'),
                              if (_order!.weightGrams != null && _order!.weightGrams! > 0)
                                _infoRow('Weight', '${(_order!.weightGrams! / 1000).toStringAsFixed(2)} kg'),
                              if (_order!.notes != null && _order!.notes!.isNotEmpty)
                                _infoRow('Order Notes', _order!.notes!),
                              if (_order!.codCollectedAmount != null)
                                _infoRow('COD Collected', '₱${_order!.codCollectedAmount!.toStringAsFixed(2)}'),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Items List
                        _buildSectionCard(
                          title: 'Parcel Contents (${_order!.items.length})',
                          icon: Icons.shopping_bag_outlined,
                          child: Column(
                            children: _order!.items.map((item) {
                              return Padding(
                                padding: const EdgeInsets.symmetric(vertical: 4),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Expanded(
                                      child: Text(
                                        '${item.quantity}× ${item.productName}',
                                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                      ),
                                    ),
                                    Text(
                                      '₱${(item.unitPrice * item.quantity).toStringAsFixed(2)}',
                                      style: const TextStyle(fontSize: 13, color: AppTheme.muted),
                                    ),
                                  ],
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Delivery Attempts Timeline
                        if (_order!.deliveryAttempts.isNotEmpty) ...[
                          _buildSectionCard(
                            title: 'Delivery Attempts (${_order!.deliveryAttempts.length})',
                            icon: Icons.history_rounded,
                            child: Column(
                              children: _order!.deliveryAttempts.map((attempt) {
                                return Container(
                                  margin: const EdgeInsets.only(bottom: 8),
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: attempt.outcome == 'failed'
                                        ? const Color(0xFFFFF1F2)
                                        : const Color(0xFFECFDF5),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Icon(
                                        attempt.outcome == 'failed' ? Icons.cancel_outlined : Icons.check_circle_outline,
                                        color: attempt.outcome == 'failed' ? AppTheme.danger : AppTheme.success,
                                        size: 18,
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              'Attempt #${attempt.attemptNo} - ${attempt.outcome.toUpperCase()}',
                                              style: TextStyle(
                                                fontWeight: FontWeight.bold,
                                                fontSize: 12,
                                                color: attempt.outcome == 'failed' ? AppTheme.danger : AppTheme.success,
                                              ),
                                            ),
                                            if (attempt.reason != null)
                                              Text('Reason: ${attempt.reason}', style: const TextStyle(fontSize: 12)),
                                            if (attempt.notes != null)
                                              Text('Notes: ${attempt.notes}', style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
                                            if (attempt.attemptedAt != null)
                                              Text('Time: ${attempt.attemptedAt}', style: const TextStyle(fontSize: 11, color: AppTheme.muted)),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }).toList(),
                            ),
                          ),
                          const SizedBox(height: 14),
                        ],

                        // Tracking Events Timeline
                        if (_order!.trackingEvents.isNotEmpty)
                          _buildSectionCard(
                            title: 'Tracking Timeline (${_order!.trackingEvents.length})',
                            icon: Icons.timeline_rounded,
                            child: Column(
                              children: _order!.trackingEvents.map((event) {
                                return Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 6),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Icon(Icons.circle, size: 8, color: AppTheme.primary),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              event.eventLabel,
                                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                                            ),
                                            if (event.notes != null && event.notes!.isNotEmpty)
                                              Text(event.notes!, style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
                                            Text(
                                              '${event.location ?? 'Hub'} · ${event.createdAt}',
                                              style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }).toList(),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _buildStatusHeader(OrderModel order) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _statusColor(order.status).withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _statusColor(order.status).withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(Icons.local_shipping_rounded, color: _statusColor(order.status), size: 24),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  order.statusLabel.toUpperCase(),
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                    color: _statusColor(order.status),
                  ),
                ),
                Text(
                  'Order Number: ${order.orderNumber}',
                  style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionPanel(OrderModel order, CourierProvider courierProv) {
    // 1. Available Pickup
    if (order.status == 'READY_FOR_PICKUP' && order.pickupClaimedAt == null) {
      return Row(
        children: [
          Expanded(
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
              onPressed: () async {
                final ok = await courierProv.claimPickup(order.id);
                if (ok) _loadOrder();
              },
              child: const Text('Accept Pickup Assignment'),
            ),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger),
            onPressed: () async {
              final ok = await courierProv.declinePickup(order.id);
              if (ok) {
                if (mounted) Navigator.of(context).pop();
              }
            },
            child: const Text('Decline'),
          ),
        ],
      );
    }

    // 2. Claimed Pickup in progress
    if (order.status == 'READY_FOR_PICKUP' && order.pickupClaimedAt != null) {
      if (order.pickupArrivedAt != null && order.sellerHandoverAt != null) {
        return ElevatedButton.icon(
          style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
          icon: const Icon(Icons.qr_code_scanner_rounded),
          label: const Text('Confirm Scanned Parcel Possession'),
          onPressed: () async {
            final res = await showDialog<Map<String, String>>(
              context: context,
              builder: (_) => ActionScanDialog(
                title: 'Confirm Parcel Pickup',
                message: 'Scan the parcel QR code or enter label reference to confirm possession.',
                hint: order.parcelCode ?? 'EZP:...',
                confirmLabel: 'Confirm Collection',
              ),
            );
            if (res != null) {
              final ok = await courierProv.confirmPickup(
                order.id,
                res['code']!,
                method: res['method'] ?? 'manual',
              );
              if (ok) _loadOrder();
            }
          },
        );
      } else if (order.pickupArrivedAt != null) {
        return Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFFEF3C7),
            borderRadius: BorderRadius.circular(10),
          ),
          child: const Row(
            children: [
              Icon(Icons.hourglass_top_rounded, color: Color(0xFFB45309), size: 20),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Waiting for seller to confirm handover on their device.',
                  style: TextStyle(color: Color(0xFFB45309), fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        );
      } else {
        return ElevatedButton.icon(
          style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
          icon: const Icon(Icons.navigation_rounded),
          label: const Text('Record Arrival at Seller'),
          onPressed: () async {
            final ok = await courierProv.arriveAtPickup(order.id);
            if (ok) _loadOrder();
          },
        );
      }
    }

    // 3. Assigned to Rider
    if (order.status == 'ASSIGNED_TO_RIDER') {
      if (order.hubReleasedAt != null) {
        return ElevatedButton.icon(
          style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
          icon: const Icon(Icons.play_arrow_rounded),
          label: const Text('Start Doorstep Delivery'),
          onPressed: () async {
            final res = await showDialog<Map<String, String>>(
              context: context,
              builder: (_) => ActionScanDialog(
                title: 'Start Delivery',
                message: 'Scan the parcel QR code before leaving the hub for delivery.',
                hint: order.parcelCode ?? 'EZP:...',
                confirmLabel: 'Start Delivery',
              ),
            );
            if (res != null) {
              final ok = await courierProv.startDelivery(
                order.id,
                parcelReference: res['code'],
                method: res['method'] ?? 'manual',
              );
              if (ok) _loadOrder();
            }
          },
        );
      } else {
        return Column(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1F2),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Row(
                children: [
                  Icon(Icons.info_outline_rounded, color: Color(0xFFE11D48), size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Waiting for Logistics Hub to release this parcel.',
                      style: TextStyle(color: Color(0xFF9F1239), fontSize: 12, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger),
              onPressed: () async {
                final ok = await courierProv.declineDeliveryAssignment(order.id);
                if (ok) {
                  if (mounted) Navigator.of(context).pop();
                }
              },
              child: const Text('Decline Assignment'),
            ),
          ],
        );
      }
    }

    // 4. Out For Delivery
    if (order.status == 'OUT_FOR_DELIVERY') {
      return Column(
        children: [
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.success),
            icon: const Icon(Icons.check_circle_rounded),
            label: const Text('Complete Delivery'),
            onPressed: () async {
              final res = await showDialog<Map<String, dynamic>>(
                context: context,
                builder: (_) => CompleteDeliveryDialog(order: order),
              );
              if (res != null) {
                final ok = await courierProv.completeDelivery(
                  orderId: order.id,
                  recipientConfirmation: res['recipient_confirmation'],
                  deliveryCode: res['delivery_code'],
                  codCollectedAmount: res['cod_collected_amount'],
                  deliveryNotes: res['delivery_notes'],
                  proofFilePath: res['proof_file_path'],
                );
                if (ok) _loadOrder();
              }
            },
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger),
            icon: const Icon(Icons.cancel_outlined),
            label: const Text('Record Failed Attempt'),
            onPressed: () async {
              final res = await showDialog<Map<String, dynamic>>(
                context: context,
                builder: (_) => FailDeliveryDialog(order: order),
              );
              if (res != null) {
                final ok = await courierProv.failDelivery(
                  orderId: order.id,
                  failureReason: res['failure_reason'],
                  deliveryNotes: res['delivery_notes'],
                );
                if (ok) _loadOrder();
              }
            },
          ),
        ],
      );
    }

    // 5. Return in Transit
    if (order.status == 'RETURN_IN_TRANSIT') {
      return ElevatedButton.icon(
        style: ElevatedButton.styleFrom(backgroundColor: Colors.purple),
        icon: const Icon(Icons.assignment_return_rounded),
        label: const Text('Confirm Return Handover to Seller'),
        onPressed: () async {
          final res = await showDialog<Map<String, String>>(
            context: context,
            builder: (_) => ActionScanDialog(
              title: 'Confirm Seller Return',
              message: 'Scan the return parcel QR code to record handoff to seller.',
              hint: order.parcelCode ?? 'EZP:...',
              confirmLabel: 'Confirm Return',
              confirmColor: Colors.purple,
            ),
          );
          if (res != null) {
            final ok = await courierProv.confirmReturnDelivery(
              order.id,
              res['code']!,
              method: res['method'] ?? 'manual',
            );
            if (ok) _loadOrder();
          }
        },
      );
    }

    // 6. Delivered & COD -> Remittance
    if (order.status == 'DELIVERED' && order.isCod) {
      return ElevatedButton.icon(
        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0F766E)),
        icon: const Icon(Icons.payments_rounded),
        label: const Text('Submit COD Remittance'),
        onPressed: () async {
          final ok = await showModalBottomSheet<bool>(
            context: context,
            isScrollControlled: true,
            shape: const RoundedRectangleBorder(
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
            ),
            builder: (_) => RemittanceModal(order: order),
          );
          if (ok == true) _loadOrder();
        },
      );
    }

    return const SizedBox.shrink();
  }

  Widget _buildSectionCard({
    required String title,
    required IconData icon,
    required Widget child,
  }) {
    return Container(
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
            children: [
              Icon(icon, color: AppTheme.primary, size: 18),
              const SizedBox(width: 8),
              Text(
                title,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
              ),
            ],
          ),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }

  Widget _infoRow(String label, String value, {bool isHighlight = false, Color? highlightColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 13, color: AppTheme.muted)),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: isHighlight ? FontWeight.bold : FontWeight.w600,
              color: highlightColor ?? AppTheme.ink,
            ),
          ),
        ],
      ),
    );
  }
}
