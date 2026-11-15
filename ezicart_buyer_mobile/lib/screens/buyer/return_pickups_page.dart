import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/parcel_record_model.dart';
import '../../services/api_service.dart';
import 'parcel_evidence_page.dart';

class ReturnPickupsPage extends StatefulWidget {
  const ReturnPickupsPage({super.key});

  @override
  State<ReturnPickupsPage> createState() => _ReturnPickupsPageState();
}

class _ReturnPickupsPageState extends State<ReturnPickupsPage> {
  List<ReturnPickupModel> _pickups = [];
  bool _isLoading = true;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _fetchPickups();
  }

  Future<void> _fetchPickups() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.returnPickupsEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((e) => ReturnPickupModel.fromJson(e as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _pickups = list;
              _isLoading = false;
            });
            return;
          }
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _confirmHandover(ReturnPickupModel pickup) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.handshake_outlined, color: AppTheme.primary, size: 24),
            SizedBox(width: 8),
            Expanded(
              child: Text(
                'Confirm Handover',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Are you sure you have physically given the return package for ${pickup.orderNumber ?? 'Order #${pickup.id}'} to the assigned rider?',
                style: const TextStyle(fontSize: 13, height: 1.35),
              ),
              if (pickup.riderName != null) ...[
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.two_wheeler, size: 18, color: AppTheme.ink),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Rider: ${pickup.riderName}',
                          style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.bold, color: AppTheme.ink),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 10),
              const Text(
                'Notice: After confirming, the rider will scan the parcel barcode to officially register custody and transport it to the seller/hub.',
                style: TextStyle(fontSize: 11.5, color: AppTheme.muted),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF16A34A),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Confirm Handover', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _isSubmitting = true);
    try {
      final res = await ApiService.post(ApiConfig.returnPickupHandoverEndpoint(pickup.id), {});
      final body = jsonDecode(res.body);

      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message']?.toString() ?? 'Handover confirmed successfully!'),
            backgroundColor: const Color(0xFF16A34A),
          ),
        );
        _fetchPickups();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message']?.toString() ?? 'Handover confirmation failed.'),
            backgroundColor: const Color(0xFFDC2626),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
    if (mounted) setState(() => _isSubmitting = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FC),
      appBar: AppBar(
        title: const Text(
          'Approved Return Pickups',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
            onPressed: _fetchPickups,
          ),
        ],
      ),
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _pickups.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(32),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.assignment_return_outlined, size: 48, color: Colors.grey.shade400),
                          const SizedBox(height: 12),
                          const Text('No approved return pickups', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                          const SizedBox(height: 4),
                          const Text(
                            'Orders with approved returns or replacement pickups will appear here for rider assignment and handover.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 13, color: AppTheme.muted),
                          ),
                        ],
                      ),
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: _fetchPickups,
                    child: ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _pickups.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) {
                        final item = _pickups[i];
                        return _buildPickupCard(item);
                      },
                    ),
                  ),
      ),
    );
  }

  Widget _buildPickupCard(ReturnPickupModel item) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEAECEF)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Order & Status Pill
            Row(
              children: [
                Expanded(
                  child: Text(
                    item.orderNumber ?? 'Order #${item.id}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.ink),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    item.disputeStatus?.replaceAll('_', ' ') ?? 'RETURN APPROVED',
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF16A34A)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),

            // Parcel Code & Merchant
            Row(
              children: [
                const Icon(Icons.storefront, size: 14, color: AppTheme.muted),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    item.sellerName,
                    style: const TextStyle(fontSize: 12.5, color: AppTheme.ink),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                if (item.parcelCode != null) ...[
                  const SizedBox(width: 6),
                  Text(
                    'EZP: ${item.parcelCode}',
                    style: const TextStyle(fontSize: 11.5, color: AppTheme.muted, fontFamily: 'monospace'),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 10),

            // Pickup Status Guidance Box (Synchronized with Web)
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: item.canConfirmHandover ? const Color(0xFFFFFBEB) : const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: item.canConfirmHandover ? const Color(0xFFFDE68A) : const Color(0xFFE2E8F0),
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    item.isBuyerHandedOver
                        ? Icons.check_circle_outline
                        : (item.canConfirmHandover ? Icons.notification_important_outlined : Icons.info_outline),
                    size: 16,
                    color: item.isBuyerHandedOver
                        ? const Color(0xFF16A34A)
                        : (item.canConfirmHandover ? const Color(0xFFD97706) : const Color(0xFF2563EB)),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      item.statusMessage,
                      style: TextStyle(
                        fontSize: 12,
                        color: item.canConfirmHandover ? const Color(0xFF92400E) : AppTheme.ink,
                        height: 1.3,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Assigned Rider info
            if (item.riderName != null) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  const Icon(Icons.two_wheeler, size: 14, color: AppTheme.muted),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      'Assigned Rider: ${item.riderName}${item.riderPlate != null ? ' (${item.riderPlate})' : ''}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.ink),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (item.riderContact != null)
                    Text(
                      item.riderContact!,
                      style: const TextStyle(fontSize: 11.5, color: AppTheme.muted),
                    ),
                ],
              ),
            ],

            // Actions footer
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              alignment: WrapAlignment.end,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    side: const BorderSide(color: Color(0xFFDCE0E8)),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  icon: const Icon(Icons.timeline, size: 14, color: AppTheme.ink),
                  label: const Text('Parcel Journey', style: TextStyle(fontSize: 12, color: AppTheme.ink)),
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ParcelEvidencePage(orderId: item.id),
                      ),
                    );
                  },
                ),
                if (item.canConfirmHandover)
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF16A34A),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    icon: const Icon(Icons.handshake, size: 14, color: Colors.white),
                    label: const Text('Confirm Handover', style: TextStyle(fontSize: 12, color: Colors.white)),
                    onPressed: _isSubmitting ? null : () => _confirmHandover(item),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
