import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/parcel_record_model.dart';
import '../../services/api_service.dart';
import 'parcel_evidence_page.dart';

class ParcelIncidentsPage extends StatefulWidget {
  final int? initialIncidentId;

  const ParcelIncidentsPage({super.key, this.initialIncidentId});

  @override
  State<ParcelIncidentsPage> createState() => _ParcelIncidentsPageState();
}

class _ParcelIncidentsPageState extends State<ParcelIncidentsPage> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<String> _tabs = ['ALL', 'OPEN', 'RESOLVED'];
  final List<String> _tabLabels = ['All Incidents', 'Open Holds', 'Resolved'];

  List<ParcelIncidentModel> _incidents = [];
  bool _isLoading = true;
  String? _authToken;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        _fetchIncidents();
      }
    });
    _loadAuthToken();
    _fetchIncidents();

    if (widget.initialIncidentId != null) {
      _fetchIncidentDetail(widget.initialIncidentId!);
    }
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadAuthToken() async {
    final token = await ApiService.getToken();
    if (mounted) setState(() => _authToken = token);
  }

  Future<void> _fetchIncidents() async {
    setState(() => _isLoading = true);
    final tab = _tabs[_tabController.index];
    final statusQuery = tab == 'ALL' ? '' : '?status=$tab';

    try {
      final res = await ApiService.get('${ApiConfig.parcelIncidentsEndpoint}$statusQuery');
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((e) => ParcelIncidentModel.fromJson(e as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _incidents = list;
              _isLoading = false;
            });
            return;
          }
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _fetchIncidentDetail(int incidentId) async {
    try {
      final res = await ApiService.get(ApiConfig.parcelIncidentDetailEndpoint(incidentId));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is Map) {
          final detail = ParcelIncidentModel.fromJson(body['data'] as Map<String, dynamic>);
          if (mounted) {
            _showIncidentDetailModal(detail);
            return;
          }
        }
      }
    } catch (_) {}
  }

  void _showIncidentDetailModal(ParcelIncidentModel incident) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.85,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        builder: (_, scrollCtrl) {
          final isResolved = incident.status == 'RESOLVED';

          return Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
            ),
            child: Column(
              children: [
                // Modal Handle
                Center(
                  child: Container(
                    margin: const EdgeInsets.only(top: 10, bottom: 6),
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                Expanded(
                  child: ListView(
                    controller: scrollCtrl,
                    padding: const EdgeInsets.fromLTRB(20, 10, 20, 30),
                    children: [
                      // Header
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  incident.kind.replaceAll('_', ' '),
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppTheme.ink),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  incident.orderNumber ?? 'Incident #${incident.id}',
                                  style: const TextStyle(fontSize: 13, color: AppTheme.muted),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: isResolved ? const Color(0xFFDCFCE7) : const Color(0xFFFEF2F2),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              incident.status,
                              style: TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.bold,
                                color: isResolved ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),

                      // Claim Assessment Status Card
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Claim Review & Assessment',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.ink),
                            ),
                            const SizedBox(height: 6),
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFDBEAFE),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    incident.claimStatusLabel.toUpperCase(),
                                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF1D4ED8)),
                                  ),
                                ),
                                if (incident.assessmentLabel != null) ...[
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'Assessment: ${incident.assessmentLabel}',
                                      style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            if (incident.orderStatus != null) ...[
                              const SizedBox(height: 8),
                              Text(
                                'Recorded custody stage: ${incident.orderStatus!.replaceAll('_', ' ')}',
                                style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Incident Reason
                      if (incident.reason != null && incident.reason!.isNotEmpty) ...[
                        const Text(
                          'Recorded Reason',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: AppTheme.ink),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          incident.reason!,
                          style: const TextStyle(fontSize: 13, color: AppTheme.ink, height: 1.35),
                        ),
                        const SizedBox(height: 14),
                      ],

                      // Claim Notes
                      if (incident.claimNotes != null && incident.claimNotes!.isNotEmpty) ...[
                        const Text(
                          'Claim Notes',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: AppTheme.ink),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          incident.claimNotes!,
                          style: const TextStyle(fontSize: 13, color: AppTheme.muted, height: 1.35),
                        ),
                        const SizedBox(height: 14),
                      ],

                      // Resolution Reason
                      if (incident.resolutionReason != null && incident.resolutionReason!.isNotEmpty) ...[
                        const Text(
                          'Resolution Summary',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: Color(0xFF16A34A)),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          incident.resolutionReason!,
                          style: const TextStyle(fontSize: 13, color: AppTheme.ink, height: 1.35),
                        ),
                        const SizedBox(height: 14),
                      ],

                      // Evidence Photos
                      if (incident.hasReportEvidence || incident.hasResolutionEvidence) ...[
                        const Text(
                          'Attached Incident Evidence Photos',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: AppTheme.ink),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            if (incident.hasReportEvidence && incident.reportEvidenceUrl != null)
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Report photo', style: TextStyle(fontSize: 11.5, color: AppTheme.muted)),
                                    const SizedBox(height: 4),
                                    InkWell(
                                      onTap: () => _showPhotoViewer(incident.reportEvidenceUrl!),
                                      child: ClipRRect(
                                        borderRadius: BorderRadius.circular(8),
                                        child: CachedNetworkImage(
                                          imageUrl: incident.reportEvidenceUrl!,
                                          height: 120,
                                          width: double.infinity,
                                          fit: BoxFit.cover,
                                          httpHeaders: _authToken != null ? {'Authorization': 'Bearer $_authToken'} : null,
                                          placeholder: (_, __) => Container(height: 120, color: const Color(0xFFEDF2F7)),
                                          errorWidget: (_, __, ___) => Container(
                                            height: 120,
                                            color: const Color(0xFFEDF2F7),
                                            child: const Center(child: Icon(Icons.broken_image, size: 28)),
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            if (incident.hasReportEvidence && incident.hasResolutionEvidence)
                              const SizedBox(width: 12),
                            if (incident.hasResolutionEvidence && incident.resolutionEvidenceUrl != null)
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Resolution photo', style: TextStyle(fontSize: 11.5, color: AppTheme.muted)),
                                    const SizedBox(height: 4),
                                    InkWell(
                                      onTap: () => _showPhotoViewer(incident.resolutionEvidenceUrl!),
                                      child: ClipRRect(
                                        borderRadius: BorderRadius.circular(8),
                                        child: CachedNetworkImage(
                                          imageUrl: incident.resolutionEvidenceUrl!,
                                          height: 120,
                                          width: double.infinity,
                                          fit: BoxFit.cover,
                                          httpHeaders: _authToken != null ? {'Authorization': 'Bearer $_authToken'} : null,
                                          placeholder: (_, __) => Container(height: 120, color: const Color(0xFFEDF2F7)),
                                          errorWidget: (_, __, ___) => Container(
                                            height: 120,
                                            color: const Color(0xFFEDF2F7),
                                            child: const Center(child: Icon(Icons.broken_image, size: 28)),
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 20),
                      ],

                      // Actions
                      if (incident.orderId != null)
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppTheme.primary,
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.timeline, color: Colors.white, size: 18),
                            label: const Text('View Full Parcel Journey & Evidence', style: TextStyle(color: Colors.white)),
                            onPressed: () {
                              Navigator.pop(ctx);
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => ParcelEvidencePage(orderId: incident.orderId),
                                ),
                              );
                            },
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
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
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FC),
      appBar: AppBar(
        title: const Text(
          'Parcel Incidents & Claims',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TabBar(
              controller: _tabController,
              labelColor: AppTheme.primary,
              indicatorColor: AppTheme.primary,
              indicatorWeight: 2.5,
              unselectedLabelColor: AppTheme.muted,
              tabs: _tabLabels.map((l) => Tab(text: l)).toList(),
            ),
          ),
        ),
      ),
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _incidents.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(32),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.shield_outlined, size: 48, color: Colors.grey.shade400),
                          const SizedBox(height: 12),
                          const Text('No parcel incidents recorded', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                          const SizedBox(height: 4),
                          const Text(
                            'Parcels experiencing transport loss, damage, or claims will appear here.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 13, color: AppTheme.muted),
                          ),
                        ],
                      ),
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: _fetchIncidents,
                    child: ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _incidents.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) {
                        final item = _incidents[i];
                        return _buildIncidentCard(item);
                      },
                    ),
                  ),
      ),
    );
  }

  Widget _buildIncidentCard(ParcelIncidentModel item) {
    final isResolved = item.status == 'RESOLVED';

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
        onTap: () => _showIncidentDetailModal(item),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      item.kind.replaceAll('_', ' '),
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14.5, color: AppTheme.ink),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: isResolved ? const Color(0xFFDCFCE7) : const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      item.status,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: isResolved ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  const Icon(Icons.receipt_outlined, size: 14, color: AppTheme.muted),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      item.orderNumber ?? 'Incident #${item.id}',
                      style: const TextStyle(fontSize: 12.5, color: AppTheme.muted),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF6FF),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      item.claimStatusLabel.toUpperCase(),
                      style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                    ),
                  ),
                ],
              ),
              if (item.reason != null && item.reason!.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(
                  item.reason!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 12.5, color: AppTheme.ink),
                ),
              ],
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  if (item.hasReportEvidence || item.hasResolutionEvidence)
                    const Row(
                      children: [
                        Icon(Icons.photo_library_outlined, size: 13, color: AppTheme.muted),
                        SizedBox(width: 4),
                        Text('Evidence attached', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                      ],
                    )
                  else
                    const SizedBox.shrink(),
                  const Text('Tap to review details →', style: TextStyle(fontSize: 11.5, color: AppTheme.primary, fontWeight: FontWeight.w600)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
