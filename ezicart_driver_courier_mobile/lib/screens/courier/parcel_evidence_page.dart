import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/parcel_record_model.dart';
import '../../services/api_service.dart';

class ParcelEvidencePage extends StatefulWidget {
  final int? orderId;
  final String? parcelCode;

  const ParcelEvidencePage({super.key, this.orderId, this.parcelCode});

  @override
  State<ParcelEvidencePage> createState() => _ParcelEvidencePageState();
}

class _ParcelEvidencePageState extends State<ParcelEvidencePage> {
  final TextEditingController _searchCtrl = TextEditingController();
  List<ParcelEvidenceSummaryModel> _parcels = [];
  bool _isLoadingList = false;

  // Selected Order Journey View
  int? _selectedOrderId;
  ParcelJourneyDetailModel? _journeyDetail;
  bool _isLoadingJourney = false;
  String? _authToken;

  @override
  void initState() {
    super.initState();
    _loadAuthToken();
    if (widget.orderId != null) {
      _selectedOrderId = widget.orderId;
      _fetchJourney(widget.orderId!);
    } else if (widget.parcelCode != null && widget.parcelCode!.isNotEmpty) {
      _searchCtrl.text = widget.parcelCode!;
      _performSearch(widget.parcelCode!);
    } else {
      _fetchParcelsList();
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadAuthToken() async {
    final token = await ApiService.getToken();
    if (mounted) setState(() => _authToken = token);
  }

  Future<void> _fetchParcelsList({int page = 1}) async {
    setState(() => _isLoadingList = true);
    try {
      final res = await ApiService.get(
        '${ApiConfig.parcelEvidenceEndpoint}?page=$page',
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map(
                (e) => ParcelEvidenceSummaryModel.fromJson(
                  e as Map<String, dynamic>,
                ),
              )
              .toList();
          if (mounted) {
            setState(() {
              _parcels = list;
              _isLoadingList = false;
            });
            return;
          }
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingList = false);
  }

  Future<void> _performSearch(String ref) async {
    final query = ref.trim();
    if (query.isEmpty) {
      _fetchParcelsList();
      return;
    }

    setState(() => _isLoadingList = true);
    try {
      final res = await ApiService.get(
        '${ApiConfig.parcelEvidenceEndpoint}?reference=${Uri.encodeComponent(query)}',
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['order_id'] != null) {
          final orderId = (body['order_id'] is num)
              ? (body['order_id'] as num).toInt()
              : int.tryParse(body['order_id'].toString());
          if (orderId != null) {
            _fetchJourney(orderId);
            return;
          }
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Parcel reference not found in your routes'),
            ),
          );
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingList = false);
  }

  Future<void> _fetchJourney(int orderId) async {
    setState(() {
      _selectedOrderId = orderId;
      _isLoadingJourney = true;
    });

    try {
      final res = await ApiService.get(
        ApiConfig.orderEvidenceEndpoint(orderId),
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            _journeyDetail = ParcelJourneyDetailModel.fromJson(body);
            _isLoadingJourney = false;
          });
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingJourney = false);
  }

  Future<void> _pickAndUploadEvidence(int eventId, String eventType) async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.camera,
      maxWidth: 1800,
      maxHeight: 1800,
      imageQuality: 85,
    );
    if (picked == null || _selectedOrderId == null) return;

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Uploading evidence photo...')),
    );

    try {
      final res = await ApiService.multipartPost(
        url: ApiConfig.orderEvidenceEndpoint(_selectedOrderId!),
        fields: {'event_type': eventType, 'event_id': eventId.toString()},
        fileField: 'photos[]',
        filePath: picked.path,
      );

      if (res.statusCode == 200) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Evidence photo uploaded successfully.'),
          ),
        );
        _fetchJourney(_selectedOrderId!);
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to upload evidence photo.')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Upload error: $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _selectedOrderId != null ? 'Journey & Evidence' : 'Parcel Records',
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        leading: _selectedOrderId != null
            ? IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: () {
                  setState(() {
                    _selectedOrderId = null;
                    _journeyDetail = null;
                  });
                },
              )
            : null,
      ),
      body: _selectedOrderId != null ? _buildJourneyView() : _buildListView(),
    );
  }

  Widget _buildListView() {
    return Column(
      children: [
        // Search Bar
        Padding(
          padding: const EdgeInsets.all(16),
          child: TextField(
            controller: _searchCtrl,
            decoration: InputDecoration(
              hintText: 'Search by Parcel Ref (EZP:...) or Order #',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                icon: const Icon(Icons.clear),
                onPressed: () {
                  _searchCtrl.clear();
                  _fetchParcelsList();
                },
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 12,
              ),
            ),
            onSubmitted: _performSearch,
          ),
        ),

        // List Content
        Expanded(
          child: _isLoadingList
              ? const Center(
                  child: CircularProgressIndicator(color: AppTheme.primary),
                )
              : _parcels.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.folder_open_outlined,
                        size: 48,
                        color: AppTheme.muted,
                      ),
                      SizedBox(height: 12),
                      Text(
                        'No parcel records found',
                        style: TextStyle(fontWeight: FontWeight.bold),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Parcels on your route will appear here.',
                        style: TextStyle(fontSize: 12, color: AppTheme.muted),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _fetchParcelsList,
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 8,
                    ),
                    itemCount: _parcels.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (ctx, i) {
                      final p = _parcels[i];
                      return _buildParcelItem(p);
                    },
                  ),
                ),
        ),
      ],
    );
  }

  Widget _buildParcelItem(ParcelEvidenceSummaryModel p) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => _fetchJourney(p.id),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      p.orderNumber ?? 'Order #${p.id}',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: AppTheme.primarySoft,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      p.operationalStatus,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.primary,
                      ),
                    ),
                  ),
                ],
              ),
              if (p.parcelCode != null) ...[
                const SizedBox(height: 4),
                Text(
                  'EZP:${p.parcelCode}',
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppTheme.muted,
                    fontFamily: 'monospace',
                  ),
                ),
              ],
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      'Seller: ${p.sellerName}',
                      style: const TextStyle(fontSize: 12, color: AppTheme.ink),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (p.evidencePhotosCount > 0)
                    Row(
                      children: [
                        const Icon(
                          Icons.photo_camera_back_outlined,
                          size: 14,
                          color: AppTheme.muted,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${p.evidencePhotosCount} photos',
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppTheme.muted,
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildJourneyView() {
    if (_isLoadingJourney) {
      return const Center(
        child: CircularProgressIndicator(color: AppTheme.primary),
      );
    }
    if (_journeyDetail == null) {
      return const Center(
        child: Text('Could not load parcel journey details.'),
      );
    }

    final detail = _journeyDetail!;

    return RefreshIndicator(
      onRefresh: () => _fetchJourney(_selectedOrderId!),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Next Action Banner
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFBFDBFE)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.flag_rounded,
                  color: Color(0xFF1D4ED8),
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Next Operational Action',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                          color: Color(0xFF1E40AF),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        detail.nextAction,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF1E3A8A),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Header summary
          Container(
            padding: const EdgeInsets.all(16),
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
                    Expanded(
                      child: Text(
                        detail.orderNumber ?? 'Order #${detail.orderId}',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      detail.operationalStatus,
                      style: const TextStyle(
                        color: AppTheme.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
                if (detail.parcelCode != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Reference: EZP:${detail.parcelCode}',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppTheme.muted,
                      fontFamily: 'monospace',
                    ),
                  ),
                ],
                if (detail.parcelWeightGrams != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Verified Weight: ${(detail.parcelWeightGrams! / 1000).toStringAsFixed(2)} kg',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Events & Timeline
          const Text(
            'Unified Tracking Timeline',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 10),

          if (detail.events.isEmpty)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Center(
                child: Text(
                  'No events recorded yet',
                  style: TextStyle(color: AppTheme.muted),
                ),
              ),
            )
          else
            ...detail.events.map((e) => _buildTimelineTile(e)),

          const SizedBox(height: 20),

          // Scans section
          if (detail.scans.isNotEmpty) ...[
            const Text(
              'Sorting Center Physical Scans',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 10),
            ...detail.scans.map((s) => _buildScanTile(s)),
            const SizedBox(height: 20),
          ],

          // Delivery attempts section
          if (detail.attempts.isNotEmpty) ...[
            const Text(
              'Recorded Delivery Attempts',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 10),
            ...detail.attempts.map((a) => _buildAttemptTile(a)),
            const SizedBox(height: 20),
          ],

          // Photos Gallery
          if (detail.photos.isNotEmpty) ...[
            const Text(
              'Recorded Evidence Photos',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 10),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                crossAxisSpacing: 8,
                mainAxisSpacing: 8,
              ),
              itemCount: detail.photos.length,
              itemBuilder: (ctx, idx) {
                final photo = detail.photos[idx];
                final photoUrl =
                    _authToken != null && !photo.url.contains('token=')
                    ? '${photo.url}?token=$_authToken'
                    : photo.url;
                return ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    photoUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      color: const Color(0xFFF1F5F9),
                      child: const Icon(
                        Icons.broken_image,
                        color: AppTheme.muted,
                      ),
                    ),
                  ),
                );
              },
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildTimelineTile(ParcelTrackingEventModel e) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: const BoxDecoration(
              color: AppTheme.primarySoft,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.check, size: 16, color: AppTheme.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppTheme.line),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        e.eventType.replaceAll('_', ' ').toUpperCase(),
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                      Text(
                        e.createdAt.length >= 16
                            ? e.createdAt.substring(0, 16).replaceAll('T', ' ')
                            : e.createdAt,
                        style: const TextStyle(
                          fontSize: 10,
                          color: AppTheme.muted,
                        ),
                      ),
                    ],
                  ),
                  if (e.location.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      e.location,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppTheme.muted,
                      ),
                    ),
                  ],
                  if (e.notes != null && e.notes!.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      e.notes!,
                      style: const TextStyle(fontSize: 12, color: AppTheme.ink),
                    ),
                  ],
                  const SizedBox(height: 6),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'By: ${e.actorName}',
                        style: const TextStyle(
                          fontSize: 11,
                          fontStyle: FontStyle.italic,
                          color: AppTheme.muted,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(
                          Icons.add_a_photo_outlined,
                          size: 18,
                          color: AppTheme.primary,
                        ),
                        tooltip: 'Add Evidence Photo',
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        onPressed: () =>
                            _pickAndUploadEvidence(e.id, 'tracking'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildScanTile(ParcelScanEventModel s) {
    final isAccepted = s.result == 'accepted';
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: ListTile(
        leading: Icon(
          isAccepted ? Icons.qr_code_2_rounded : Icons.warning_amber_rounded,
          color: isAccepted ? AppTheme.success : AppTheme.danger,
        ),
        title: Text(
          s.station?.replaceAll('_', ' ').toUpperCase() ?? 'SCAN',
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
        ),
        subtitle: Text(
          'By: ${s.actorName} (${s.role ?? 'Staff'}) · ${s.method ?? 'scanner'}',
          style: const TextStyle(fontSize: 11),
        ),
        trailing: Text(
          s.result?.toUpperCase() ?? '',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 11,
            color: isAccepted ? AppTheme.success : AppTheme.danger,
          ),
        ),
      ),
    );
  }

  Widget _buildAttemptTile(ParcelDeliveryAttemptModel a) {
    final isSuccess = a.outcome == 'delivered';
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: ListTile(
        leading: Icon(
          isSuccess ? Icons.check_circle_outline : Icons.cancel_outlined,
          color: isSuccess ? AppTheme.success : AppTheme.danger,
        ),
        title: Text(
          'Attempt #${a.attemptNo}: ${a.outcome.toUpperCase()}',
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
        ),
        subtitle: Text(
          a.reason ?? (a.riderName != null ? 'Rider: ${a.riderName}' : ''),
          style: const TextStyle(fontSize: 11),
        ),
        trailing: Text(
          a.attemptedAt != null && a.attemptedAt!.length >= 10
              ? a.attemptedAt!.substring(0, 10)
              : '',
          style: const TextStyle(fontSize: 11, color: AppTheme.muted),
        ),
      ),
    );
  }
}
