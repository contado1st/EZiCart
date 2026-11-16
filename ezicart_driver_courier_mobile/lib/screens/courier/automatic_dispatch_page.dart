import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/dispatch_offer_model.dart';
import '../../services/api_service.dart';

class AutomaticDispatchPage extends StatefulWidget {
  const AutomaticDispatchPage({super.key});

  @override
  State<AutomaticDispatchPage> createState() => _AutomaticDispatchPageState();
}

class _AutomaticDispatchPageState extends State<AutomaticDispatchPage> {
  bool _isLoading = true;
  List<DispatchOfferModel> _offers = [];
  DispatchLocationPointModel? _locationPoint;
  String? _message;
  bool _isError = false;

  @override
  void initState() {
    super.initState();
    _fetchOffers();
  }

  Future<void> _fetchOffers() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.courierDispatchOffersEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final list = (data['offers'] as List? ?? [])
            .map((o) => DispatchOfferModel.fromJson(o as Map<String, dynamic>))
            .toList();

        DispatchLocationPointModel? point;
        if (data['location_point'] is Map) {
          point = DispatchLocationPointModel.fromJson(data['location_point'] as Map<String, dynamic>);
        }

        if (mounted) {
          setState(() {
            _offers = list;
            _locationPoint = point;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _respondToOffer(DispatchOfferModel offer, bool accept) async {
    String? reason;
    if (!accept) {
      final reasonCtrl = TextEditingController();
      final proceed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Decline Dispatch Offer?'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Provide an optional reason for declining this dispatch assignment:',
                style: TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: reasonCtrl,
                maxLines: 2,
                decoration: const InputDecoration(
                  hintText: 'e.g. Far distance, heavy traffic, etc.',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Decline', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
      if (proceed != true) return;
      reason = reasonCtrl.text.trim();
    }

    setState(() => _isLoading = true);
    try {
      final res = await ApiService.post(
        ApiConfig.courierRespondDispatchOfferEndpoint(offer.id),
        {
          'decision': accept ? 'accept' : 'decline',
          if (reason != null && reason.isNotEmpty) 'reason': reason,
        },
      );

      final data = jsonDecode(res.body);
      if (res.statusCode == 200 && data['success'] == true) {
        setState(() {
          _message = data['message']?.toString() ?? 'Offer response recorded.';
          _isError = false;
        });
        await _fetchOffers();
      } else {
        setState(() {
          _message = data['message']?.toString() ?? 'Failed to respond to offer.';
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

  Future<void> _updatePosition() async {
    final latCtrl = TextEditingController(text: _locationPoint != null ? _locationPoint!.latitude.toString() : '14.5995');
    final lngCtrl = TextEditingController(text: _locationPoint != null ? _locationPoint!.longitude.toString() : '120.9842');
    final accCtrl = TextEditingController(text: '15.0');

    final updated = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.my_location_rounded, color: AppTheme.primary),
            SizedBox(width: 8),
            Text('Report GPS Position', style: TextStyle(fontSize: 16)),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Reporting your device coordinates allows the logistics dispatch algorithm to match nearby available pickups with your vehicle.',
                style: TextStyle(fontSize: 12, color: AppTheme.muted),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: latCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Latitude', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: lngCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Longitude', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: accCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Accuracy (meters)', border: OutlineInputBorder()),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Update', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (updated != true) return;

    try {
      final res = await ApiService.post(ApiConfig.courierDispatchPositionEndpoint, {
        'latitude': double.tryParse(latCtrl.text) ?? 14.5995,
        'longitude': double.tryParse(lngCtrl.text) ?? 120.9842,
        'accuracy_meters': double.tryParse(accCtrl.text) ?? 15.0,
        'captured_at': DateTime.now().toIso8601String(),
      });

      final data = jsonDecode(res.body);
      if (res.statusCode == 200 && data['success'] == true) {
        setState(() {
          _message = 'GPS Position reported successfully for dispatch offers.';
          _isError = false;
        });
        await _fetchOffers();
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Automatic Dispatch', style: TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _fetchOffers,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _fetchOffers,
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

                  // GPS Location Tracking Card
                  _buildLocationCard(),
                  const SizedBox(height: 16),

                  Text(
                    'Dispatch Offers (${_offers.length})',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  const SizedBox(height: 10),

                  if (_offers.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(28),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppTheme.line),
                      ),
                      child: const Column(
                        children: [
                          Icon(Icons.near_me_outlined, size: 48, color: AppTheme.muted),
                          SizedBox(height: 12),
                          Text(
                            'No automatic dispatch offers',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'When sorting hubs run automated assignment cycles, nearest eligible offers will be displayed here for your confirmation.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 12, color: AppTheme.muted),
                          ),
                        ],
                      ),
                    )
                  else
                    ..._offers.map((offer) => _buildOfferCard(offer)),
                ],
              ),
            ),
    );
  }

  Widget _buildLocationCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.line),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.location_on_rounded, color: AppTheme.primary, size: 20),
                  SizedBox(width: 8),
                  Text('Device Position Status', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                ],
              ),
              TextButton.icon(
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  visualDensity: VisualDensity.compact,
                ),
                icon: const Icon(Icons.edit_location_alt_rounded, size: 16),
                label: const Text('Update', style: TextStyle(fontSize: 12)),
                onPressed: _updatePosition,
              ),
            ],
          ),
          const SizedBox(height: 8),
          if (_locationPoint != null) ...[
            Text(
              'Lat: ${_locationPoint!.latitude.toStringAsFixed(4)}, Lng: ${_locationPoint!.longitude.toStringAsFixed(4)} (Â±${_locationPoint!.accuracyMeters.toStringAsFixed(1)}m)',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.ink),
            ),
            const SizedBox(height: 2),
            Text(
              _locationPoint!.capturedAt != null
                  ? 'Last reported: ${_locationPoint!.capturedAt!.replaceAll('T', ' ').substring(0, 19)}'
                  : 'Position active',
              style: const TextStyle(fontSize: 11, color: AppTheme.muted),
            ),
          ] else ...[
            const Text(
              'No position recorded. Update your location so nearby dispatch cycles can find you.',
              style: TextStyle(fontSize: 12, color: AppTheme.muted),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildOfferCard(DispatchOfferModel offer) {
    Color statusBg;
    Color statusFg;
    String statusLabel;

    if (offer.status == 'ACCEPTED') {
      statusBg = const Color(0xFFECFDF5);
      statusFg = AppTheme.success;
      statusLabel = 'ACCEPTED';
    } else if (offer.status == 'DECLINED') {
      statusBg = const Color(0xFFFFF1F2);
      statusFg = AppTheme.danger;
      statusLabel = 'DECLINED';
    } else if (offer.isExpired) {
      statusBg = const Color(0xFFF1F5F9);
      statusFg = AppTheme.muted;
      statusLabel = 'EXPIRED';
    } else {
      statusBg = const Color(0xFFFFFBEB);
      statusFg = const Color(0xFFB45309);
      statusLabel = 'OFFER PENDING';
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
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
                  offer.orderNumber ?? 'Order #${offer.orderId}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusBg,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: statusFg),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Merchant / Pickup', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                      Text(
                        offer.sellerName ?? 'Seller',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (offer.sellerAddress != null)
                        Text(
                          offer.sellerAddress!,
                          style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                        ),
                    ],
                  ),
                ),
                const Icon(Icons.arrow_forward_rounded, color: AppTheme.muted, size: 16),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      const Text('Destination', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                      Text(
                        offer.deliveryArea ?? 'Destination Area',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      Text(
                        'Weight: ${offer.weightKg}',
                        style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const Divider(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Estimated Payout', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                    Text(
                      'â‚±${offer.payoutPesos}',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.primary),
                    ),
                  ],
                ),
                if (offer.isActive) ...[
                  Row(
                    children: [
                      OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: AppTheme.danger),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                          visualDensity: VisualDensity.compact,
                        ),
                        onPressed: () => _respondToOffer(offer, false),
                        child: const Text('Decline', style: TextStyle(color: AppTheme.danger, fontSize: 12)),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          visualDensity: VisualDensity.compact,
                        ),
                        onPressed: () => _respondToOffer(offer, true),
                        child: const Text('Accept Offer', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                      ),
                    ],
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
