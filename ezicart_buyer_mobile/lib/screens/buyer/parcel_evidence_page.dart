import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
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
  int _currentPage = 1;

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
      final res = await ApiService.get('${ApiConfig.parcelEvidenceEndpoint}?page=$page');
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((e) => ParcelEvidenceSummaryModel.fromJson(e as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _parcels = list;
              _currentPage = body['current_page'] is int ? body['current_page'] as int : 1;
              _isLoadingList = false;
            });
            return;
          }
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingList = false);
  }

  Future<void> _performSearch(String query) async {
    final clean = query.trim();
    if (clean.isEmpty) {
      _fetchParcelsList();
      return;
    }
    setState(() => _isLoadingList = true);
    try {
      final res = await ApiService.get('${ApiConfig.parcelEvidenceEndpoint}?reference=${Uri.encodeComponent(clean)}');
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['order_id'] != null) {
          final foundId = int.tryParse(body['order_id'].toString());
          if (foundId != null) {
            _selectedOrderId = foundId;
            _fetchJourney(foundId);
            return;
          }
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('No parcel found with this reference/code.')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Search failed: $e')),
        );
      }
    }
    if (mounted) setState(() => _isLoadingList = false);
  }

  Future<void> _fetchJourney(int orderId) async {
    setState(() => _isLoadingJourney = true);
    try {
      final res = await ApiService.get(ApiConfig.orderEvidenceEndpoint(orderId));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is Map) {
          final detail = ParcelJourneyDetailModel.fromJson(body['data'] as Map<String, dynamic>);
          if (mounted) {
            setState(() {
              _journeyDetail = detail;
              _isLoadingJourney = false;
            });
            return;
          }
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingJourney = false);
  }

  Future<void> _uploadEventPhotos(String eventType, int eventId) async {
    if (_selectedOrderId == null) return;
    try {
      final picker = ImagePicker();
      final pickedFiles = await picker.pickMultiImage(imageQuality: 85);
      if (pickedFiles.isEmpty) return;

      if (pickedFiles.length > 3) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Maximum 3 photos allowed per event.')),
          );
        }
        return;
      }

      if (!mounted) return;

      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => const Center(
          child: Card(
            child: Padding(
              padding: EdgeInsets.all(20),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  CircularProgressIndicator(),
                  SizedBox(width: 16),
                  Text('Uploading evidence...'),
                ],
              ),
            ),
          ),
        ),
      );

      final multipartFiles = <http.MultipartFile>[];
      for (final file in pickedFiles) {
        final bytes = await file.readAsBytes();
        multipartFiles.add(
          http.MultipartFile.fromBytes('photos[]', bytes, filename: file.name),
        );
      }

      final res = await ApiService.postMultipart(
        ApiConfig.orderEvidenceEndpoint(_selectedOrderId!),
        fields: {
          'event_type': eventType,
          'event_id': eventId.toString(),
        },
        files: multipartFiles,
      );

      if (mounted) Navigator.of(context, rootNavigator: true).pop();

      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Evidence photos uploaded successfully.'),
            backgroundColor: Color(0xFF16A34A),
          ),
        );
        _fetchJourney(_selectedOrderId!);
      } else if (mounted) {
        final body = jsonDecode(res.body);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(body['message']?.toString() ?? 'Upload failed.')),
        );
      }
    } catch (e) {
      if (mounted) {
        Navigator.of(context, rootNavigator: true).maybePop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error uploading: $e')),
        );
      }
    }
  }

  void _showPhotoViewer(String url) {
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.black.withValues(alpha: 0.9),
        insetPadding: const EdgeInsets.all(12),
        child: Stack(
          alignment: Alignment.center,
          children: [
            InteractiveViewer(
              panEnabled: true,
              minScale: 0.5,
              maxScale: 4.0,
              child: CachedNetworkImage(
                imageUrl: url,
                httpHeaders: _authToken != null ? {'Authorization': 'Bearer $_authToken'} : null,
                placeholder: (_, __) => const Center(child: CircularProgressIndicator(color: Colors.white)),
                errorWidget: (_, __, ___) => const Center(
                  child: Text('Unable to load photo', style: TextStyle(color: Colors.white70)),
                ),
              ),
            ),
            Positioned(
              top: 12,
              right: 12,
              child: IconButton(
                icon: const Icon(Icons.close, color: Colors.white, size: 28),
                onPressed: () => Navigator.pop(ctx),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isViewingJourney = _selectedOrderId != null;

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FC),
      appBar: AppBar(
        title: Text(
          isViewingJourney ? 'Parcel Journey & Evidence' : 'Parcel Records',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: isViewingJourney && widget.orderId == null
            ? IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: () {
                  setState(() {
                    _selectedOrderId = null;
                    _journeyDetail = null;
                  });
                  _fetchParcelsList();
                },
              )
            : const BackButton(),
        actions: [
          if (isViewingJourney)
            IconButton(
              icon: const Icon(Icons.refresh),
              tooltip: 'Refresh Timeline',
              onPressed: () => _fetchJourney(_selectedOrderId!),
            ),
        ],
      ),
      body: SafeArea(
        child: isViewingJourney ? _buildJourneyView() : _buildParcelsListView(),
      ),
    );
  }

  Widget _buildParcelsListView() {
    return Column(
      children: [
        // Search bar
        Container(
          color: Colors.white,
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchCtrl,
                  decoration: InputDecoration(
                    hintText: 'Search order number or EZP: code...',
                    hintStyle: const TextStyle(fontSize: 13, color: AppTheme.muted),
                    prefixIcon: const Icon(Icons.search, size: 20),
                    suffixIcon: _searchCtrl.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, size: 18),
                            onPressed: () {
                              _searchCtrl.clear();
                              _fetchParcelsList();
                            },
                          )
                        : null,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                      borderSide: const BorderSide(color: Color(0xFFDCE0E8)),
                    ),
                    filled: true,
                    fillColor: const Color(0xFFF8F9FC),
                  ),
                  onSubmitted: _performSearch,
                ),
              ),
              const SizedBox(width: 8),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primary,
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                onPressed: () => _performSearch(_searchCtrl.text),
                child: const Text('Lookup', style: TextStyle(color: Colors.white, fontSize: 13)),
              ),
            ],
          ),
        ),

        // Parcels List
        Expanded(
          child: _isLoadingList
              ? const Center(child: CircularProgressIndicator())
              : _parcels.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.inventory_2_outlined, size: 48, color: Colors.grey.shade400),
                            const SizedBox(height: 12),
                            const Text('No parcel records found', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                            const SizedBox(height: 4),
                            const Text('Active orders and parcel tracking will appear here.', textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: AppTheme.muted)),
                          ],
                        ),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: () => _fetchParcelsList(page: _currentPage),
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _parcels.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, i) {
                          final item = _parcels[i];
                          return _buildParcelSummaryCard(item);
                        },
                      ),
                    ),
        ),
      ],
    );
  }

  Widget _buildParcelSummaryCard(ParcelEvidenceSummaryModel item) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEAECEF)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          setState(() => _selectedOrderId = item.id);
          _fetchJourney(item.id);
        },
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      item.orderNumber ?? 'Order #${item.id}',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14.5, color: AppTheme.ink),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppTheme.primarySoft,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      item.operationalStatus.toUpperCase(),
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.primary),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              if (item.parcelCode != null && item.parcelCode!.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    children: [
                      const Icon(Icons.qr_code_2, size: 14, color: AppTheme.muted),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          'EZP: ${item.parcelCode}',
                          style: const TextStyle(fontSize: 12, color: AppTheme.muted, fontFamily: 'monospace'),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
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
                  if (item.evidencePhotosCount > 0) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.photo_library_outlined, size: 12, color: AppTheme.muted),
                          const SizedBox(width: 4),
                          Text('${item.evidencePhotosCount} photos', style: const TextStyle(fontSize: 11, color: AppTheme.muted)),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
              if (item.latestEvent != null) ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFFEDF2F7)),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.place_outlined, size: 14, color: Color(0xFF2563EB)),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.latestEvent!.location,
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.ink),
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              item.latestEvent!.createdAt,
                              style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildJourneyView() {
    if (_isLoadingJourney) {
      return const Center(child: CircularProgressIndicator());
    }

    final journey = _journeyDetail;
    if (journey == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text('Could not load parcel journey detail.'),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: () => _fetchJourney(_selectedOrderId!),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Order Header Panel
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFEAECEF)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        journey.orderNumber ?? 'Order #${journey.orderId}',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17, color: AppTheme.ink),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppTheme.primarySoft,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        journey.operationalStatus.toUpperCase(),
                        style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: AppTheme.primary),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                if (journey.parcelCode != null && journey.parcelCode!.isNotEmpty)
                  Row(
                    children: [
                      const Icon(Icons.qr_code, size: 15, color: AppTheme.muted),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          'Parcel Code: EZP:${journey.parcelCode}',
                          style: const TextStyle(fontSize: 12.5, color: AppTheme.muted, fontFamily: 'monospace'),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                if (journey.parcelWeightGrams != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      'Recorded weight: ${journey.parcelWeightGrams}g',
                      style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                    ),
                  ),
              ],
            ),
          ),

          // Incident Alert Banner (if fulfillment on hold)
          if (journey.activeIncident != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF2F2),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFECACA)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 22),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${journey.activeIncident!.kind} — Fulfillment On Hold',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: Color(0xFF991B1B)),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          'Recorded custody stage: ${journey.operationalStatus}. The platform is reviewing this incident and any associated claim.',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF7F1D1D), height: 1.3),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],

          // Next Action Guidance Panel (Synchronized with Web)
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFDBEAFE)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.info_outline, color: Color(0xFF2563EB), size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Next Fulfillment Action',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1E40AF)),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        journey.nextAction,
                        style: const TextStyle(fontSize: 12.5, color: Color(0xFF1E3A8A), height: 1.35),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Order Progress Steps Tracker
          if (journey.progress != null && journey.progress!.steps.isNotEmpty) ...[
            const SizedBox(height: 16),
            _buildProgressSteps(journey.progress!),
          ],

          // Tracking Events & Journey Timeline
          const SizedBox(height: 20),
          const Row(
            children: [
              Icon(Icons.timeline, color: AppTheme.primary, size: 20),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Parcel Journey & Scans',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.ink),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          if (journey.events.isEmpty && journey.scans.isEmpty)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFEAECEF)),
              ),
              child: const Center(
                child: Text('Tracking events will appear as the parcel moves through fulfillment.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 13, color: AppTheme.muted)),
              ),
            )
          else ...[
            // Unified Tracking Events
            ...journey.events.map((e) => _buildTrackingEventTile(e)),
            // Scan Events
            if (journey.scans.isNotEmpty) ...[
              const SizedBox(height: 14),
              const Text('Hub & Transfer Scan Records',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink)),
              const SizedBox(height: 8),
              ...journey.scans.map((s) => _buildScanEventTile(s)),
            ],
          ],

          // Delivery Attempts
          if (journey.deliveryAttempts.isNotEmpty) ...[
            const SizedBox(height: 20),
            const Text('Delivery Attempts & Proof',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink)),
            const SizedBox(height: 8),
            ...journey.deliveryAttempts.map((a) => _buildAttemptTile(a)),
          ],
        ],
      ),
    );
  }

  Widget _buildProgressSteps(OrderProgressModel progress) {
    final currentStage = progress.stage ?? 0;

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
                  progress.requiresAction
                      ? 'Action required from you'
                      : (progress.ownerLabel != null ? 'Waiting for ${progress.ownerLabel}' : 'No action required'),
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: progress.requiresAction ? const Color(0xFFDC2626) : const Color(0xFF2563EB),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // Horizontal steps with SingleChildScrollView to prevent overflow
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: List.generate(progress.steps.length, (idx) {
                final isDone = idx < currentStage;
                final isCurrent = idx == currentStage;
                final stepName = progress.steps[idx];

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
                    if (idx < progress.steps.length - 1)
                      Container(
                        width: 20,
                        height: 2,
                        color: isDone ? const Color(0xFF16A34A) : const Color(0xFFE2E8F0),
                        margin: const EdgeInsets.only(bottom: 16, left: 4, right: 4),
                      ),
                  ],
                );
              }),
            ),
          ),
          if (progress.message.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              progress.message,
              style: const TextStyle(fontSize: 12, color: AppTheme.muted, height: 1.3),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildTrackingEventTile(ParcelTrackingEventModel event) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFEAECEF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: AppTheme.primary,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  event.eventType.replaceAll('_', ' ').toUpperCase(),
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.ink),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 6),
              Text(
                event.createdAt,
                style: const TextStyle(fontSize: 11, color: AppTheme.muted),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.location_on_outlined, size: 14, color: AppTheme.muted),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  event.location,
                  style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          if (event.notes != null && event.notes!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              event.notes!,
              style: const TextStyle(fontSize: 12, color: AppTheme.ink),
            ),
          ],
          // Attached Photos
          if (event.photos.isNotEmpty) ...[
            const SizedBox(height: 8),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: event.photos.map((p) {
                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: InkWell(
                      onTap: () => _showPhotoViewer(p.url),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(6),
                        child: CachedNetworkImage(
                          imageUrl: p.url,
                          width: 54,
                          height: 54,
                          fit: BoxFit.cover,
                          httpHeaders: _authToken != null ? {'Authorization': 'Bearer $_authToken'} : null,
                          placeholder: (_, __) => Container(width: 54, height: 54, color: const Color(0xFFEDF2F7)),
                          errorWidget: (_, __, ___) => Container(
                            width: 54,
                            height: 54,
                            color: const Color(0xFFEDF2F7),
                            child: const Icon(Icons.broken_image, size: 20, color: AppTheme.muted),
                          ),
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ],
          // Button to attach photos if under 3 photos
          if (event.photos.length < 3) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                icon: const Icon(Icons.add_a_photo_outlined, size: 14, color: AppTheme.primary),
                label: const Text('Add private photos', style: TextStyle(fontSize: 11, color: AppTheme.primary)),
                onPressed: () => _uploadEventPhotos('tracking', event.id),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildScanEventTile(ParcelScanEventModel scan) {
    final isSuccess = scan.result == 'PASSED' || scan.result == 'SUCCESS';

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFBFBFC),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFEAECEF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                isSuccess ? Icons.check_circle_outline : Icons.error_outline,
                size: 16,
                color: isSuccess ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  '${scan.station ?? 'Station'} · ${scan.result ?? 'Recorded'}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5, color: AppTheme.ink),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                scan.createdAt,
                style: const TextStyle(fontSize: 10.5, color: AppTheme.muted),
              ),
            ],
          ),
          const SizedBox(height: 3),
          Text(
            'Scanned by: ${scan.actorName} (${scan.role ?? 'Staff'})',
            style: const TextStyle(fontSize: 11.5, color: AppTheme.muted),
          ),
          if (scan.failureReason != null)
            Text(
              'Reason: ${scan.failureReason}',
              style: const TextStyle(fontSize: 11.5, color: Color(0xFFDC2626)),
            ),
          if (scan.photos.isNotEmpty) ...[
            const SizedBox(height: 6),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: scan.photos.map((p) {
                  return Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: InkWell(
                      onTap: () => _showPhotoViewer(p.url),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(6),
                        child: CachedNetworkImage(
                          imageUrl: p.url,
                          width: 48,
                          height: 48,
                          fit: BoxFit.cover,
                          httpHeaders: _authToken != null ? {'Authorization': 'Bearer $_authToken'} : null,
                          placeholder: (_, __) => Container(width: 48, height: 48, color: const Color(0xFFEDF2F7)),
                          errorWidget: (_, __, ___) => const Icon(Icons.broken_image, size: 20),
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildAttemptTile(ParcelDeliveryAttemptModel attempt) {
    final isDelivered = attempt.outcome == 'delivered' || attempt.outcome == 'success';

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFEAECEF)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            isDelivered ? Icons.check_circle : Icons.warning_amber_rounded,
            size: 18,
            color: isDelivered ? const Color(0xFF16A34A) : const Color(0xFFD97706),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Attempt #${attempt.attemptNo} — ${attempt.outcome.toUpperCase()}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.ink),
                ),
                if (attempt.reason != null)
                  Text(
                    'Reason: ${attempt.reason}',
                    style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                  ),
                if (attempt.riderName != null)
                  Text(
                    'Rider: ${attempt.riderName}',
                    style: const TextStyle(fontSize: 11.5, color: AppTheme.muted),
                  ),
                if (attempt.attemptedAt != null)
                  Text(
                    attempt.attemptedAt!,
                    style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
