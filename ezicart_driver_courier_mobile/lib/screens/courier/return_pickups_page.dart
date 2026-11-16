import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/return_pickup_model.dart';
import '../../services/api_service.dart';
import 'order_details_page.dart';

class ReturnPickupsPage extends StatefulWidget {
  const ReturnPickupsPage({super.key});

  @override
  State<ReturnPickupsPage> createState() => _ReturnPickupsPageState();
}

class _ReturnPickupsPageState extends State<ReturnPickupsPage> {
  bool _isLoading = true;
  List<ReturnPickupModel> _pickups = [];
  String? _message;
  bool _isError = false;

  @override
  void initState() {
    super.initState();
    _fetchPickups();
  }

  Future<void> _fetchPickups() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.courierReturnPickupsEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final list = (data['data'] as List? ?? [])
            .map((p) => ReturnPickupModel.fromJson(p as Map<String, dynamic>))
            .toList();

        if (mounted) {
          setState(() {
            _pickups = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _acceptAssignment(ReturnPickupModel pickup) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Accept Return Pickup?'),
        content: Text(
          'Confirm that you will travel to the buyer in ${pickup.buyer.municipality} to collect the return parcel for ${pickup.orderNumber ?? 'Order #${pickup.id}'}.',
          style: const TextStyle(fontSize: 13),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Accept', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
    if (confirm != true) return;

    setState(() => _isLoading = true);
    try {
      final res = await ApiService.post(
        ApiConfig.courierAcceptReturnPickupEndpoint(pickup.id),
        {},
      );
      final data = jsonDecode(res.body);
      if (res.statusCode == 200 && data['success'] == true) {
        setState(() {
          _message = data['message']?.toString() ?? 'Return pickup accepted.';
          _isError = false;
        });
        await _fetchPickups();
      } else {
        setState(() {
          _message = data['message']?.toString() ?? 'Failed to accept return pickup.';
          _isError = true;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _message = e.toString();
        _isError = true;
        _isLoading = false;
      });
    }
  }

  Future<void> _collectParcel(ReturnPickupModel pickup) async {
    final refCtrl = TextEditingController(text: pickup.parcelCode != null ? 'EZP:${pickup.parcelCode}' : '');
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.qr_code_scanner_rounded, color: AppTheme.primary),
            SizedBox(width: 8),
            Text('Scan Return Parcel', style: TextStyle(fontSize: 16)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Enter or scan the parcel label QR/reference code from the physical package provided by the buyer.',
              style: TextStyle(fontSize: 12, color: AppTheme.muted),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: refCtrl,
              decoration: const InputDecoration(
                labelText: 'Parcel Reference (EZP:â€¦)',
                hintText: 'EZP:â€¦',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Confirm Collection', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;
    final reference = refCtrl.text.trim();
    if (reference.isEmpty) return;

    setState(() => _isLoading = true);
    try {
      final res = await ApiService.post(
        ApiConfig.courierCollectReturnPickupEndpoint(pickup.id),
        {'parcel_reference': reference},
      );
      final data = jsonDecode(res.body);
      if (res.statusCode == 200 && data['success'] == true) {
        setState(() {
          _message = data['message']?.toString() ?? 'Return parcel collected successfully.';
          _isError = false;
        });
        await _fetchPickups();
      } else {
        setState(() {
          _message = data['message']?.toString() ?? 'Failed to collect return parcel.';
          _isError = true;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _message = e.toString();
        _isError = true;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Approved Return Pickups', style: TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _fetchPickups,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _fetchPickups,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_message != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: _isError ? const Color(0xFFFFF1F2) : const Color(0xFFECFDF5),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: _isError ? const Color(0xFFFECDD3) : const Color(0xFFA7F3D0),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            _isError ? Icons.error_outline : Icons.check_circle_outline,
                            color: _isError ? AppTheme.danger : AppTheme.success,
                            size: 18,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _message!,
                              style: TextStyle(
                                fontSize: 12,
                                color: _isError ? const Color(0xFF9F1239) : const Color(0xFF065F46),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],

                  if (_pickups.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(32),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppTheme.line),
                      ),
                      child: const Column(
                        children: [
                          Icon(Icons.assignment_return_outlined, size: 48, color: AppTheme.muted),
                          SizedBox(height: 12),
                          Text(
                            'No return pickups assigned',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'When customer disputes are approved for replacement or refund, return pickup assignments for your route will appear here.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 12, color: AppTheme.muted),
                          ),
                        ],
                      ),
                    )
                  else
                    ..._pickups.map((pickup) => _buildPickupCard(pickup)),
                ],
              ),
            ),
    );
  }

  Widget _buildPickupCard(ReturnPickupModel pickup) {
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  pickup.orderNumber ?? 'Order #${pickup.id}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: pickup.status == 'RETURN_IN_TRANSIT'
                        ? const Color(0xFFFFFBEB)
                        : (pickup.isHandedOver ? const Color(0xFFEFF6FF) : const Color(0xFFF1F5F9)),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    pickup.status == 'RETURN_IN_TRANSIT'
                        ? 'IN TRANSIT TO MERCHANT'
                        : (pickup.isHandedOver ? 'HANDOVER CONFIRMED' : (pickup.isAccepted ? 'ACCEPTED' : 'PENDING ACCEPTANCE')),
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: pickup.status == 'RETURN_IN_TRANSIT'
                          ? const Color(0xFFB45309)
                          : (pickup.isHandedOver ? const Color(0xFF1D4ED8) : AppTheme.ink),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),

            if (pickup.disputeReason != null) ...[
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF1F2),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline, size: 14, color: AppTheme.danger),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Return Reason: ${pickup.disputeReason}',
                        style: const TextStyle(fontSize: 11, color: Color(0xFF9F1239)),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
            ],

            // Pickup & Destination Info
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Collect From (Buyer)', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                      Text(pickup.buyer.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                      Text(
                        pickup.buyer.address,
                        style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      const Text('Return To (Merchant)', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                      Text(pickup.seller.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                      Text(
                        pickup.seller.address,
                        style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.end,
                      ),
                    ],
                  ),
                ),
              ],
            ),

            const Divider(height: 20),

            // Action row
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                TextButton(
                  style: TextButton.styleFrom(
                    padding: EdgeInsets.zero,
                    visualDensity: VisualDensity.compact,
                  ),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => OrderDetailsPage(orderId: pickup.id),
                      ),
                    );
                  },
                  child: const Text('View Order', style: TextStyle(fontSize: 12)),
                ),
                if (pickup.canAccept) ...[
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      visualDensity: VisualDensity.compact,
                    ),
                    onPressed: () => _acceptAssignment(pickup),
                    child: const Text('Accept Assignment', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                  ),
                ] else if (pickup.canCollect) ...[
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.success,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      visualDensity: VisualDensity.compact,
                    ),
                    icon: const Icon(Icons.qr_code_scanner_rounded, size: 16, color: Colors.white),
                    label: const Text('Scan & Collect', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                    onPressed: () => _collectParcel(pickup),
                  ),
                ] else if (pickup.status == 'RETURN_IN_TRANSIT') ...[
                  const Text(
                    'Deliver to merchant on Workboard',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFB45309)),
                  ),
                ] else ...[
                  const Text(
                    'Awaiting buyer physical handover',
                    style: TextStyle(fontSize: 11, color: AppTheme.muted, fontStyle: FontStyle.italic),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
