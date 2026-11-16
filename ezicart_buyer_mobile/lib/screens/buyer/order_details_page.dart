import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../services/api_service.dart';
import '../../utils/philippine_time.dart';
import 'create_dispute_page.dart';
import 'messages_page.dart';
import 'parcel_evidence_page.dart';
import 'parcel_incidents_page.dart';
import 'return_pickups_page.dart';
import 'shop_page.dart';
import 'wallet_payment_dialog.dart';

class OrderDetailsPage extends StatefulWidget {
  final int orderId;

  const OrderDetailsPage({super.key, required this.orderId});

  @override
  State<OrderDetailsPage> createState() => _OrderDetailsPageState();
}

class _OrderDetailsPageState extends State<OrderDetailsPage> {
  OrderModel? _order;
  bool _isLoading = true;
  bool _isActionLoading = false;

  @override
  void initState() {
    super.initState();
    _fetchDetails();
  }

  Future<void> _fetchDetails() async {
    try {
      final res = await ApiService.get(
        ApiConfig.orderDetailEndpoint(widget.orderId),
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is Map) {
          setState(() {
            _order = OrderModel.fromJson(body['data'] as Map<String, dynamic>);
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  Future<void> _cancelOrder() async {
    final reasonCtrl = TextEditingController();
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Order'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Are you sure you want to cancel this order? Fulfillment will be stopped.',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: reasonCtrl,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Reason for cancellation',
                hintText: 'e.g. Changed my mind, ordered by mistake',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Keep Order'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () {
              if (reasonCtrl.text.trim().isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Please provide a reason for cancellation.'),
                  ),
                );
                return;
              }
              Navigator.pop(ctx, true);
            },
            child: const Text('Cancel Order'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isActionLoading = true);
    try {
      final res =
          await ApiService.post(ApiConfig.cancelOrderEndpoint(widget.orderId), {
            'reason': reasonCtrl.text.trim().isNotEmpty
                ? reasonCtrl.text.trim()
                : 'Cancelled by buyer',
          });
      final body = jsonDecode(res.body);
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Cancellation requested.'),
            backgroundColor: Colors.green,
          ),
        );
        _fetchDetails();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(body['message'] ?? 'Failed to cancel order.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _isActionLoading = false);
    }
  }

  Future<void> _openDispute() async {
    if (_order == null) return;
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => CreateDisputePage(order: _order!)),
    );
    if (created == true) {
      _fetchDetails();
    }
  }

  Future<void> _refreshDeliveryCode() async {
    setState(() => _isActionLoading = true);
    try {
      final res = await ApiService.post(
        ApiConfig.refreshDeliveryCodeEndpoint(widget.orderId),
        {},
      );
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Delivery verification code refreshed.'),
            backgroundColor: Colors.green,
          ),
        );
        _fetchDetails();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      setState(() => _isActionLoading = false);
    }
  }

  Future<void> _confirmReceived() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm Receipt'),
        content: const Text(
          'Are you sure you have received all items in this delivery in good condition?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Confirm Received'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isActionLoading = true);
    try {
      final res = await ApiService.post(
        ApiConfig.confirmOrderEndpoint(widget.orderId),
        {},
      );
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Order marked as Completed! Thank you.'),
            backgroundColor: Colors.green,
          ),
        );
        _fetchDetails();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      setState(() => _isActionLoading = false);
    }
  }

  void _showReviewDialog(int productId, String productName) {
    int rating = 5;
    final commentCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDlgState) => AlertDialog(
          title: Text(
            'Review $productName',
            style: const TextStyle(fontSize: 16),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (i) {
                  return IconButton(
                    icon: Icon(
                      i < rating ? Icons.star : Icons.star_border,
                      color: Colors.amber,
                      size: 32,
                    ),
                    onPressed: () => setDlgState(() => rating = i + 1),
                  );
                }),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: commentCtrl,
                maxLines: 3,
                maxLength: 500,
                decoration: const InputDecoration(
                  hintText:
                      'Share your feedback on the product quality (max 500 characters)...',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () async {
                if (rating < 1) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Please select at least 1 star.'),
                    ),
                  );
                  return;
                }
                if (commentCtrl.text.trim().length > 500) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Review cannot exceed 500 characters.'),
                    ),
                  );
                  return;
                }
                Navigator.pop(ctx);
                final scaffold = ScaffoldMessenger.of(context);
                try {
                  final res = await ApiService.post(
                    ApiConfig.reviewOrderEndpoint(widget.orderId),
                    {
                      'product_id': productId,
                      'rating': rating,
                      'comment': commentCtrl.text.trim(),
                    },
                  );
                  if (res.statusCode == 200 && mounted) {
                    scaffold.showSnackBar(
                      const SnackBar(
                        content: Text('Review submitted! Thank you.'),
                        backgroundColor: Colors.green,
                      ),
                    );
                  }
                } catch (_) {}
              },
              child: const Text('Submit Review'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: Text('Order #${widget.orderId}')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_order == null) {
      return Scaffold(
        appBar: AppBar(title: Text('Order #${widget.orderId}')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.receipt_long_outlined,
                  size: 54,
                  color: Colors.grey.shade400,
                ),
                const SizedBox(height: 12),
                const Text(
                  'Order details could not be loaded.',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Please verify your connection and tap retry below.',
                  style: TextStyle(color: AppTheme.muted, fontSize: 13),
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
                  icon: const Icon(Icons.refresh, size: 18),
                  label: const Text('Retry'),
                  onPressed: () {
                    setState(() => _isLoading = true);
                    _fetchDetails();
                  },
                ),
              ],
            ),
          ),
        ),
      );
    }

    final currency = NumberFormat.currency(
      locale: 'en_PH',
      symbol: '₱',
      decimalDigits: 2,
    );
    final isOutForDelivery = _order!.status == 'OUT_FOR_DELIVERY';
    final displayOrderRef = _order!.orderNumber ?? 'Order #${_order!.id}';

    return Scaffold(
      appBar: AppBar(
        title: Text(
          displayOrderRef,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          if (_order!.sellerId != null)
            IconButton(
              icon: const Icon(Icons.chat_outlined),
              tooltip: 'Chat with Seller',
              onPressed: () async {
                final nav = Navigator.of(context);
                try {
                  final res = await ApiService.post(
                    ApiConfig.chatStartEndpoint(_order!.sellerId!),
                    {},
                  );
                  if (res.statusCode == 200 && mounted) {
                    final body = jsonDecode(res.body);
                    nav.push(
                      MaterialPageRoute(
                        builder: (_) => ChatConversationScreen(
                          conversationId: body['conversation_id'],
                          partnerName: _order!.sellerName,
                          orderId: _order!.id,
                        ),
                      ),
                    );
                  }
                } catch (_) {}
              },
            ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Parcel & Status Header Card
              Card(
                elevation: 0,
                color: AppTheme.primarySoft,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'STATUS',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.primary,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: _order!.hasPendingCancellation
                                  ? const Color(0xFFDC2626)
                                  : AppTheme.primary,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              _order!.hasPendingCancellation
                                  ? 'CANCELLATION PENDING'
                                  : _order!.statusLabel.toUpperCase(),
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _order!.parcelCode != null
                            ? 'Tracking: ${_order!.parcelCode}'
                            : 'Order #${_order!.id}',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: AppTheme.ink,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Placed on: ${PhilippineTime.format(_order!.createdAt)}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppTheme.muted,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Journey & Evidence Quick Access Button (Synchronized with Web)
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  backgroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                  side: const BorderSide(color: AppTheme.primary),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                icon: const Icon(Icons.timeline, color: AppTheme.primary, size: 18),
                label: const Text(
                  'View Parcel Journey and Event Photos',
                  style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary, fontSize: 13),
                ),
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ParcelEvidencePage(orderId: _order!.id),
                    ),
                  );
                },
              ),
              const SizedBox(height: 12),

              // Incident Hold Alert (Synchronized with Web components/order-progress)
              if (_order!.fulfillmentOnHold || _order!.activeIncident != null) ...[
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFECACA)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              '${_order!.incidentStatus ?? "Incident Recorded"} — Fulfillment on hold',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF991B1B)),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Recorded custody stage: ${_order!.statusLabel}. The platform is reviewing this transport incident and any claim progress.',
                        style: const TextStyle(fontSize: 12, color: Color(0xFF7F1D1D), height: 1.3),
                      ),
                      const SizedBox(height: 10),
                      OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFFDC2626),
                          side: const BorderSide(color: Color(0xFFFCA5A5)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        icon: const Icon(Icons.shield_outlined, size: 14),
                        label: const Text('Review Incident & Claim Progress', style: TextStyle(fontSize: 12)),
                        onPressed: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(builder: (_) => const ParcelIncidentsPage()),
                          );
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Approved Return Pickup Alert (Synchronized with Web return-pickups)
              if (_order!.returnPickup != null ||
                  (_order!.dispute != null &&
                      ['REFUND_APPROVED', 'REPLACEMENT_APPROVED'].contains(_order!.dispute!.status))) ...[
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF0FDF4),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFBBF7D0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.assignment_return_outlined, color: Color(0xFF16A34A), size: 20),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Return Approved — Rider Pickup Scheduled',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF166534)),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'A courier rider will collect the return parcel from your address. Confirm handover only after physically giving the parcel to the rider.',
                        style: TextStyle(fontSize: 12, color: Color(0xFF14532D), height: 1.3),
                      ),
                      const SizedBox(height: 10),
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF16A34A),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        icon: const Icon(Icons.handshake_outlined, size: 14, color: Colors.white),
                        label: const Text('View Approved Return Pickups', style: TextStyle(fontSize: 12, color: Colors.white)),
                        onPressed: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(builder: (_) => const ReturnPickupsPage()),
                          );
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Order Progress Steps Tracker (Synchronized with Web)
              if (_order!.orderProgress != null) ...[
                _buildProgressTrackerWidget(_order!.orderProgress!),
                const SizedBox(height: 12),
              ],

              // Awaiting Wallet Payment Notice & Action
              if (_order!.isAwaitingWalletPayment) ...[
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.amber.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.amber.shade300),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(
                            Icons.hourglass_top,
                            color: Colors.amber.shade900,
                            size: 20,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Reserved, awaiting demo wallet payment',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                                color: Colors.amber.shade900,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Stock and vouchers are held. The seller will begin preparation after your payment is recorded.',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.amber.shade900,
                        ),
                      ),
                      const SizedBox(height: 10),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppTheme.primary,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          icon: const Icon(
                            Icons.account_balance_wallet,
                            size: 16,
                          ),
                          label: const Text('Pay with EZiCart Wallet'),
                          onPressed: () {
                            WalletPaymentDialog.show(
                              context,
                              order: _order!,
                              onPaymentSuccess: _fetchDetails,
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],
              if (isOutForDelivery && _order!.deliveryCode != null) ...[
                Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: const BorderSide(color: Colors.orange, width: 1.5),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.qr_code_2_outlined,
                              color: Colors.orange.shade800,
                              size: 24,
                            ),
                            const SizedBox(width: 8),
                            const Text(
                              'Delivery Hand-off Verification',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Present this OTP code or QR payload to the courier upon doorstep arrival to receive your package.',
                          style: TextStyle(fontSize: 12, color: AppTheme.muted),
                        ),
                        const SizedBox(height: 14),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 20,
                                vertical: 10,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.orange.shade50,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                  color: Colors.orange.shade300,
                                ),
                              ),
                              child: Text(
                                _order!.deliveryCode!,
                                style: TextStyle(
                                  fontSize: 28,
                                  fontWeight: FontWeight.bold,
                                  letterSpacing: 6,
                                  color: Colors.orange.shade900,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            IconButton(
                              icon: const Icon(
                                Icons.copy,
                                size: 20,
                                color: Colors.orange,
                              ),
                              tooltip: 'Copy Code',
                              onPressed: () {
                                Clipboard.setData(
                                  ClipboardData(text: _order!.deliveryCode!),
                                );
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Delivery verification code copied!',
                                    ),
                                  ),
                                );
                              },
                            ),
                          ],
                        ),
                        if (_order!.deliveryQrPayload != null) ...[
                          const SizedBox(height: 8),
                          Text(
                            'Scan: ${_order!.deliveryQrPayload}',
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppTheme.muted,
                            ),
                          ),
                        ],
                        const SizedBox(height: 12),
                        TextButton.icon(
                          icon: const Icon(Icons.refresh, size: 16),
                          label: const Text('Refresh Delivery Code'),
                          onPressed: _isActionLoading
                              ? null
                              : _refreshDeliveryCode,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Delivery Address Card
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 18,
                            color: AppTheme.primary,
                          ),
                          SizedBox(width: 6),
                          Text(
                            'Delivery Destination',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                      const Divider(height: 16),
                      Text(
                        '${_order!.recipientName ?? 'Buyer'} (${_order!.recipientContact ?? ''})',
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _order!.deliveryAddress ??
                            'Registered delivery address',
                        style: const TextStyle(
                          color: AppTheme.muted,
                          fontSize: 12,
                        ),
                      ),
                      if (_order!.courierName != null) ...[
                        const Divider(height: 16),
                        Row(
                          children: [
                            const Icon(
                              Icons.motorcycle_outlined,
                              size: 18,
                              color: AppTheme.muted,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'Courier: ${_order!.courierName} (${_order!.courierPlate ?? ''})',
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Items Ordered
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Items Ordered',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Flexible(
                            child: InkWell(
                              onTap: _order!.sellerId != null
                                  ? () {
                                      Navigator.of(context).push(
                                        MaterialPageRoute(
                                          builder: (_) => ShopPage(
                                            sellerId: _order!.sellerId!,
                                            initialShopName: _order!.sellerName,
                                          ),
                                        ),
                                      );
                                    }
                                  : null,
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(
                                    Icons.storefront_outlined,
                                    size: 14,
                                    color: AppTheme.primary,
                                  ),
                                  const SizedBox(width: 4),
                                  Flexible(
                                    child: Text(
                                      _order!.sellerName,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: AppTheme.primary,
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                      const Divider(height: 16),
                      ..._order!.items.map((item) {
                        final itemImg = ApiConfig.resolveImageUrl(
                          item.imagePath,
                        );
                        return Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                width: 48,
                                height: 48,
                                decoration: BoxDecoration(
                                  color: Colors.grey.shade100,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: AppTheme.line),
                                ),
                                child: itemImg != null
                                    ? ClipRRect(
                                        borderRadius: BorderRadius.circular(7),
                                        child: CachedNetworkImage(
                                          imageUrl: itemImg,
                                          fit: BoxFit.cover,
                                          errorWidget: (_, __, ___) =>
                                              const Icon(
                                                Icons.inventory_2_outlined,
                                                size: 20,
                                                color: AppTheme.muted,
                                              ),
                                        ),
                                      )
                                    : const Icon(
                                        Icons.inventory_2_outlined,
                                        size: 20,
                                        color: AppTheme.muted,
                                      ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      item.name,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w600,
                                        fontSize: 13,
                                      ),
                                    ),
                                    if (item.variationInfo != null)
                                      Text(
                                        item.variationInfo!,
                                        style: const TextStyle(
                                          color: AppTheme.muted,
                                          fontSize: 11,
                                        ),
                                      ),
                                    Text(
                                      '${item.quantity} × ${currency.format(item.price)}',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        color: AppTheme.muted,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    currency.format(item.total),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 13,
                                    ),
                                  ),
                                  if (_order!.status == 'COMPLETED' &&
                                      item.productId != null)
                                    TextButton(
                                      style: TextButton.styleFrom(
                                        padding: EdgeInsets.zero,
                                        visualDensity: VisualDensity.compact,
                                      ),
                                      onPressed: () => _showReviewDialog(
                                        item.productId!,
                                        item.name,
                                      ),
                                      child: const Text(
                                        'Review',
                                        style: TextStyle(
                                          fontSize: 12,
                                          color: AppTheme.primary,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Tracking Timeline
              if (_order!.trackingTimeline.isNotEmpty) ...[
                Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: const BorderSide(color: AppTheme.line),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(
                              Icons.route_outlined,
                              size: 18,
                              color: AppTheme.primary,
                            ),
                            SizedBox(width: 6),
                            Text(
                              'Tracking History',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                        const Divider(height: 16),
                        ..._order!.trackingTimeline.map((event) {
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  width: 8,
                                  height: 8,
                                  margin: const EdgeInsets.only(
                                    top: 4,
                                    right: 10,
                                  ),
                                  decoration: const BoxDecoration(
                                    color: AppTheme.primary,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        event.description,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 12,
                                        ),
                                      ),
                                      Text(
                                        event.timestamp,
                                        style: const TextStyle(
                                          color: AppTheme.muted,
                                          fontSize: 10,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          );
                        }),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Dispute Card (if existing)
              if (_order!.dispute != null) ...[
                Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: BorderSide(color: Colors.red.shade300),
                  ),
                  color: Colors.red.shade50,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.shield_outlined,
                              color: Colors.red.shade800,
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            const Text(
                              'Dispute / Return Case Filed',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                              ),
                            ),
                            const Spacer(),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 6,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.red.shade100,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                _order!.dispute!.status.toUpperCase(),
                                style: TextStyle(
                                  color: Colors.red.shade900,
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Reason: ${_order!.dispute!.reason}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 12,
                          ),
                        ),
                        Text(
                          _order!.dispute!.description,
                          style: const TextStyle(fontSize: 12),
                        ),
                        if (_order!.dispute!.adminNotes != null) ...[
                          const SizedBox(height: 6),
                          Text(
                            'Admin Notes: ${_order!.dispute!.adminNotes}',
                            style: const TextStyle(
                              fontSize: 11,
                              fontStyle: FontStyle.italic,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Price Summary Card
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Subtotal',
                            style: TextStyle(color: AppTheme.muted),
                          ),
                          Text(
                            currency.format(_order!.subtotal),
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                      if (_order!.discount > 0) ...[
                        const SizedBox(height: 4),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Discount',
                              style: TextStyle(color: Colors.green),
                            ),
                            Text(
                              '-${currency.format(_order!.discount)}',
                              style: const TextStyle(
                                color: Colors.green,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ],
                      const SizedBox(height: 4),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Shipping Fee',
                            style: TextStyle(color: AppTheme.muted),
                          ),
                          Text(
                            currency.format(_order!.shippingFee),
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                      const Divider(height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Total Amount (${_order!.paymentMethod})',
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 15,
                            ),
                          ),
                          Text(
                            currency.format(_order!.totalAmount),
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 17,
                              color: AppTheme.primary,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Action Buttons
              if (_order!.isAwaitingWalletPayment) ...[
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    minimumSize: const Size(double.infinity, 46),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  icon: const Icon(Icons.account_balance_wallet),
                  label: const Text(
                    'Pay with EZiCart Wallet',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  onPressed: () {
                    WalletPaymentDialog.show(
                      context,
                      order: _order!,
                      onPaymentSuccess: _fetchDetails,
                    );
                  },
                ),
                const SizedBox(height: 10),
              ],

              if (_order!.status == 'DELIVERED') ...[
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    minimumSize: const Size(double.infinity, 46),
                  ),
                  onPressed: _isActionLoading ? null : _confirmReceived,
                  child: const Text('Confirm Order Received'),
                ),
                const SizedBox(height: 10),
              ],

              if (_order!.hasPendingCancellation) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFFECACA)),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.hourglass_empty, color: Color(0xFFDC2626), size: 18),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Cancellation pending seller approval. Fulfillment is paused.',
                          style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.bold, color: Color(0xFF991B1B)),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 10),
              ] else if ([
                'PLACED',
                'CONFIRMED',
                'PREPARING',
              ].contains(_order!.status) && !_order!.isAwaitingWalletPayment) ...[
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red.shade700,
                    side: BorderSide(color: Colors.red.shade300),
                    minimumSize: const Size(double.infinity, 46),
                  ),
                  onPressed: _isActionLoading ? null : _cancelOrder,
                  child: const Text('Cancel Order'),
                ),
                const SizedBox(height: 10),
              ],

              if (['DELIVERED', 'COMPLETED'].contains(_order!.status) &&
                  _order!.dispute == null) ...[
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red.shade800,
                    side: BorderSide(color: Colors.red.shade300),
                    minimumSize: const Size(double.infinity, 46),
                  ),
                  icon: const Icon(Icons.report_problem_outlined, size: 18),
                  label: const Text('Raise Return / Dispute Request'),
                  onPressed: _isActionLoading ? null : _openDispute,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildProgressTrackerWidget(Map<String, dynamic> progress) {
    final rawStage = progress['stage'];
    final stage = rawStage is int ? rawStage : (int.tryParse(rawStage?.toString() ?? '') ?? 0);
    final message = progress['message']?.toString() ?? '';
    final ownerLabel = progress['owner_label']?.toString();
    final requiresAction = progress['requires_action'] == true;
    const steps = [
      'Placed',
      'Confirmed',
      'Prepared',
      'In Hub',
      'Out for Delivery',
      'Delivered',
      'Completed',
    ];

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEAECEF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  requiresAction
                      ? 'Action required from you'
                      : (ownerLabel != null ? 'Waiting for $ownerLabel' : 'Progress Timeline'),
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: requiresAction ? const Color(0xFFDC2626) : const Color(0xFF2563EB),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: List.generate(steps.length, (idx) {
                final isDone = idx < stage;
                final isCurrent = idx == stage;
                final stepName = steps[idx];

                return Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        CircleAvatar(
                          radius: 12,
                          backgroundColor: isDone
                              ? const Color(0xFF16A34A)
                              : (isCurrent ? AppTheme.primary : const Color(0xFFE2E8F0)),
                          child: isDone
                              ? const Icon(Icons.check, size: 14, color: Colors.white)
                              : Text(
                                  '${idx + 1}',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: isCurrent ? Colors.white : const Color(0xFF64748B),
                                  ),
                                ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          stepName,
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: isCurrent ? FontWeight.bold : FontWeight.normal,
                            color: isCurrent ? AppTheme.ink : AppTheme.muted,
                          ),
                        ),
                      ],
                    ),
                    if (idx < steps.length - 1)
                      Container(
                        width: 18,
                        height: 2,
                        color: isDone ? const Color(0xFF16A34A) : const Color(0xFFE2E8F0),
                        margin: const EdgeInsets.only(bottom: 16, left: 4, right: 4),
                      ),
                  ],
                );
              }),
            ),
          ),
          if (message.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              message,
              style: const TextStyle(fontSize: 12, color: AppTheme.muted, height: 1.3),
            ),
          ],
        ],
      ),
    );
  }
}
