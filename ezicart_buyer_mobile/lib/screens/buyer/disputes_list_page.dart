import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/dispute_model.dart';
import '../../services/api_service.dart';

class DisputesListPage extends StatefulWidget {
  const DisputesListPage({super.key});

  @override
  State<DisputesListPage> createState() => _DisputesListPageState();
}

class _DisputesListPageState extends State<DisputesListPage> {
  List<DisputeModel> _disputes = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchDisputes();
  }

  Future<void> _fetchDisputes() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.disputesEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((d) => DisputeModel.fromJson(d as Map<String, dynamic>))
              .toList();
          setState(() {
            _disputes = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  Color _getStatusColor(String status) {
    switch (status.toUpperCase()) {
      case 'RESOLVED':
        return Colors.green;
      case 'REJECTED':
        return Colors.red;
      case 'INVESTIGATING':
        return Colors.blue;
      default:
        return Colors.orange.shade800;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Disputes & Returns'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _disputes.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.shield_outlined, size: 54, color: Colors.grey.shade400),
                      const SizedBox(height: 12),
                      const Text(
                        'No disputes recorded',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'All your orders are in good standing.',
                        style: TextStyle(color: AppTheme.muted, fontSize: 13),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _fetchDisputes,
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _disputes.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, i) {
                      final dispute = _disputes[i];
                      final statusColor = _getStatusColor(dispute.status);

                      return Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: AppTheme.line),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Text(
                                    dispute.parcelCode != null ? 'Order #${dispute.parcelCode}' : 'Order #${dispute.orderId}',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                  ),
                                  const Spacer(),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: statusColor.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      dispute.status.toUpperCase(),
                                      style: TextStyle(
                                        color: statusColor,
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                'Reason: ${dispute.reason}',
                                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                dispute.description,
                                style: const TextStyle(fontSize: 12, color: AppTheme.ink),
                              ),
                              if (dispute.adminNotes != null && dispute.adminNotes!.isNotEmpty) ...[
                                const SizedBox(height: 8),
                                Container(
                                  padding: const EdgeInsets.all(8),
                                  decoration: BoxDecoration(
                                    color: Colors.grey.shade100,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('Admin Resolution Notes:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                      const SizedBox(height: 2),
                                      Text(dispute.adminNotes!, style: const TextStyle(fontSize: 11)),
                                    ],
                                  ),
                                ),
                              ],
                              const Divider(height: 16),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  if (dispute.sellerName != null)
                                    Text('Seller: ${dispute.sellerName}', style: const TextStyle(fontSize: 11, color: AppTheme.muted))
                                  else
                                    const SizedBox.shrink(),
                                  if (dispute.createdAt != null)
                                    Text(
                                      DateFormat('MMM dd, yyyy').format(dispute.createdAt!),
                                      style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}

