import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../config/theme.dart';
import '../../../models/order_model.dart';
import '../order_details_page.dart';

class OrderCard extends StatelessWidget {
  final OrderModel order;
  final Widget? trailingAction;
  final VoidCallback? onTap;

  const OrderCard({
    super.key,
    required this.order,
    this.trailingAction,
    this.onTap,
  });

  Future<void> _makePhoneCall(String phone) async {
    final clean = phone.replaceAll(RegExp(r'\s+'), '');
    final uri = Uri.parse('tel:$clean');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
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
    final isPickupContext = order.status == 'READY_FOR_PICKUP' || order.status == 'PICKED_UP';
    final name = isPickupContext ? order.sellerName : order.recipientName;
    final address = isPickupContext ? order.sellerAddress : order.deliveryAddress;
    final phone = isPickupContext ? order.sellerContact : order.recipientContact;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap ??
            () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => OrderDetailsPage(orderId: order.id)),
              );
            },
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Row: Order Number, Weight & Status
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Text(
                        order.orderNumber,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.ink,
                        ),
                      ),
                      if (order.weightGrams != null && order.weightGrams! > 0) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            '${(order.weightGrams! / 1000).toStringAsFixed(1)} kg',
                            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.muted),
                          ),
                        ),
                      ],
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: _statusColor(order.status).withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      order.statusLabel.toUpperCase(),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: _statusColor(order.status),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),

              // Contact & Address
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    isPickupContext ? Icons.store_rounded : Icons.location_on_rounded,
                    size: 16,
                    color: AppTheme.muted,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: AppTheme.ink,
                          ),
                        ),
                        Text(
                          address,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppTheme.muted,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  if (phone != null && phone.isNotEmpty)
                    IconButton(
                      icon: const Icon(Icons.phone_rounded, color: AppTheme.primary, size: 20),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                      onPressed: () => _makePhoneCall(phone),
                    ),
                ],
              ),
              const SizedBox(height: 8),

              // Payment Method & Total
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: order.isCod
                              ? const Color(0xFFFEF3C7)
                              : const Color(0xFFDCFCE7),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          order.isCod ? 'COD' : order.paymentMethod,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: order.isCod
                                ? const Color(0xFFB45309)
                                : const Color(0xFF15803D),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '₱${order.totalAmount.toStringAsFixed(2)}',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppTheme.ink,
                        ),
                      ),
                    ],
                  ),
                  if (order.items.isNotEmpty)
                    Text(
                      '${order.items.length} item(s)',
                      style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                    ),
                ],
              ),

              if (trailingAction != null) ...[
                const SizedBox(height: 12),
                const Divider(height: 1, color: AppTheme.line),
                const SizedBox(height: 8),
                trailingAction!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}
