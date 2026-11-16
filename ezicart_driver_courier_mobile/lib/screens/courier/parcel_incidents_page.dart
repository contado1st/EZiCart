import 'dart:convert';
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
        if (body['data'] is Map && mounted) {
          final inc = ParcelIncidentModel.fromJson(body['data'] as Map<String, dynamic>);
          _showIncidentBottomSheet(inc);
        }
      }
    } catch (_) {}
  }

  void _showIncidentBottomSheet(ParcelIncidentModel incident) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.75,
        minChildSize: 0.4,
        maxChildSize: 0.95,
        expand: false,
        builder: (_, scrollController) => Container(
          padding: const EdgeInsets.all(20),
          child: ListView(
            controller: scrollController,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Incident #${incident.id}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: incident.status == 'OPEN' ? const Color(0xFFFFF1F2) : const Color(0xFFECFDF5),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      incident.status == 'OPEN' ? 'CUSTODY HOLD' : 'RESOLVED',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: incident.status == 'OPEN' ? AppTheme.danger : AppTheme.success,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                'Order ${incident.orderNumber}',
                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
              ),
              const SizedBox(height: 16),

              _buildDetailRow('Incident Kind', incident.kind),
              _buildDetailRow('Claim Status', incident.claimStatusLabel),
              _buildDetailRow('Recorded Custody', incident.recordedCustody),
              _buildDetailRow('Reported Reason', incident.reason),
              if (incident.claimNotes != null)
                _buildDetailRow('Claim Review Notes', incident.claimNotes!),

              if (incident.assessment != null) ...[
                const Divider(height: 24),
                const Text('Financial & Settlement Assessment', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                const SizedBox(height: 8),
                _buildDetailRow('Liable Party', incident.assessment!.liableParty ?? 'Logistics Pool'),
                _buildDetailRow('Assessment Status', incident.assessment!.status),
                if (incident.assessment!.refundEstimatePesos != null)
                  _buildDetailRow('Refund/Coverage Estimate', 'â‚±${incident.assessment!.refundEstimatePesos!.toStringAsFixed(2)}'),
                if (incident.assessment!.resolutionSummary != null)
                  _buildDetailRow('Resolution Summary', incident.assessment!.resolutionSummary!),
              ],

              if (incident.hasReportEvidence && incident.reportEvidenceUrl != null) ...[
                const Divider(height: 24),
                const Text('Report Damage Evidence Photo', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    _authToken != null && !incident.reportEvidenceUrl!.contains('token=')
                        ? '${incident.reportEvidenceUrl!}?token=$_authToken'
                        : incident.reportEvidenceUrl!,
                    height: 180,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      height: 120,
                      color: const Color(0xFFF1F5F9),
                      child: const Center(child: Icon(Icons.broken_image, color: AppTheme.muted)),
                    ),
                  ),
                ),
              ],

              const SizedBox(height: 20),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primary,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                icon: const Icon(Icons.timeline_rounded, color: Colors.white),
                label: const Text('Open Unified Parcel Journey', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                onPressed: () {
                  Navigator.pop(ctx);
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ParcelEvidencePage(orderId: incident.orderId),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(label, style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.ink)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Parcel Incidents & Claims', style: TextStyle(fontWeight: FontWeight.bold)),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppTheme.primary,
          unselectedLabelColor: AppTheme.muted,
          indicatorColor: AppTheme.primary,
          indicatorWeight: 3,
          tabs: _tabLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _fetchIncidents,
              child: _incidents.isEmpty
                  ? const Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.verified_user_outlined, size: 48, color: AppTheme.muted),
                          SizedBox(height: 12),
                          Text('No incident holds on your parcels', style: TextStyle(fontWeight: FontWeight.bold)),
                          SizedBox(height: 4),
                          Text('Parcels in your route have no open damage or loss claims.', style: TextStyle(fontSize: 12, color: AppTheme.muted)),
                        ],
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _incidents.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (ctx, i) {
                        final inc = _incidents[i];
                        return _buildIncidentCard(inc);
                      },
                    ),
            ),
    );
  }

  Widget _buildIncidentCard(ParcelIncidentModel incident) {
    final isOpen = incident.status == 'OPEN';
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: isOpen ? AppTheme.danger.withValues(alpha: 0.3) : AppTheme.line),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => _showIncidentBottomSheet(incident),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    incident.orderNumber,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: isOpen ? const Color(0xFFFFF1F2) : const Color(0xFFECFDF5),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      isOpen ? 'HOLD: ${incident.kind}' : 'RESOLVED',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: isOpen ? AppTheme.danger : AppTheme.success,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Reason: ${incident.reason}',
                style: const TextStyle(fontSize: 12, color: AppTheme.ink),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Claim: ${incident.claimStatusLabel}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                  ),
                  const Row(
                    children: [
                      Text('Details', style: TextStyle(fontSize: 11, color: AppTheme.primary, fontWeight: FontWeight.bold)),
                      Icon(Icons.chevron_right, size: 16, color: AppTheme.primary),
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
}
