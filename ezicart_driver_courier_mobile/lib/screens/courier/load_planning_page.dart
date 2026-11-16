import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/load_planning_model.dart';
import '../../models/order_model.dart';
import '../../services/api_service.dart';
import 'order_details_page.dart';

class LoadPlanningPage extends StatefulWidget {
  const LoadPlanningPage({super.key});

  @override
  State<LoadPlanningPage> createState() => _LoadPlanningPageState();
}

class _LoadPlanningPageState extends State<LoadPlanningPage> {
  bool _isLoading = true;
  LoadPlanningModel? _loadPlan;

  @override
  void initState() {
    super.initState();
    _fetchLoadPlanning();
  }

  Future<void> _fetchLoadPlanning() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.courierLoadPlanningEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            _loadPlan = LoadPlanningModel.fromJson(data);
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Weight & Load Planning', style: TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _fetchLoadPlanning,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _fetchLoadPlanning,
              child: _loadPlan == null
                  ? _buildEmptyState()
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        _buildCapacityCard(_loadPlan!.summary),
                        const SizedBox(height: 16),
                        _buildVehicleDutyCard(_loadPlan!.summary),
                        const SizedBox(height: 20),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Parcels on Vehicle (${_loadPlan!.orders.length})',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                            if (_loadPlan!.summary.unknownWeightCount > 0)
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFFBEB),
                                  borderRadius: BorderRadius.circular(6),
                                  border: Border.all(color: const Color(0xFFFDE68A)),
                                ),
                                child: Text(
                                  '${_loadPlan!.summary.unknownWeightCount} unweighed',
                                  style: const TextStyle(fontSize: 11, color: Color(0xFF92400E), fontWeight: FontWeight.w600),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (_loadPlan!.orders.isEmpty)
                          Container(
                            padding: const EdgeInsets.all(24),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppTheme.line),
                            ),
                            child: const Column(
                              children: [
                                Icon(Icons.inventory_2_outlined, size: 40, color: AppTheme.muted),
                                SizedBox(height: 10),
                                Text(
                                  'No parcels currently loaded on vehicle',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                ),
                                SizedBox(height: 4),
                                Text(
                                  'Active deliveries, accepted pickups, and hub transfers will appear here.',
                                  style: TextStyle(fontSize: 12, color: AppTheme.muted),
                                  textAlign: TextAlign.center,
                                ),
                              ],
                            ),
                          )
                        else
                          ..._loadPlan!.orders.map((o) => _buildParcelCard(o)),
                      ],
                    ),
            ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.scale_rounded, size: 48, color: AppTheme.muted),
          const SizedBox(height: 12),
          const Text('Could not load vehicle weight plan', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          ElevatedButton(
            onPressed: _fetchLoadPlanning,
            child: const Text('Try Again'),
          ),
        ],
      ),
    );
  }

  Widget _buildCapacityCard(LoadPlanningSummaryModel summary) {
    final hasCapacity = summary.capacityGrams != null && summary.capacityGrams! > 0;
    final isOverweight = summary.overweight;
    final pct = summary.utilizationPercentage;
    final isNearLimit = pct >= 80.0 && !isOverweight;

    Color progressColor;
    if (isOverweight) {
      progressColor = AppTheme.danger;
    } else if (isNearLimit) {
      progressColor = AppTheme.warning;
    } else {
      progressColor = AppTheme.success;
    }

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isOverweight ? AppTheme.danger.withValues(alpha: 0.5) : AppTheme.line,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 4),
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
                  Icon(Icons.scale_rounded, color: AppTheme.primary, size: 22),
                  SizedBox(width: 8),
                  Text('Cargo Weight Utilization', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: isOverweight
                      ? const Color(0xFFFFF1F2)
                      : (isNearLimit ? const Color(0xFFFFFBEB) : const Color(0xFFECFDF5)),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  isOverweight ? 'OVERWEIGHT' : (hasCapacity ? '${pct.toStringAsFixed(1)}%' : 'UNVERIFIED'),
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: isOverweight
                        ? AppTheme.danger
                        : (isNearLimit ? const Color(0xFFB45309) : const Color(0xFF047857)),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Progress Bar
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: hasCapacity ? (pct / 100).clamp(0.0, 1.0) : 0.0,
              minHeight: 12,
              backgroundColor: const Color(0xFFF1F5F9),
              valueColor: AlwaysStoppedAnimation<Color>(progressColor),
            ),
          ),
          const SizedBox(height: 14),

          // Weight Figures
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Current Load', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                  Text(summary.kgFormatted, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const Text('Remaining', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                  Text(
                    summary.remainingKgFormatted ?? 'â€”',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                      color: isOverweight ? AppTheme.danger : AppTheme.ink,
                    ),
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text('Verified Limit', style: TextStyle(fontSize: 11, color: AppTheme.muted)),
                  Text(summary.capacityKgFormatted, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ],
              ),
            ],
          ),

          if (isOverweight) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1F2),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFFECDD3)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.warning_amber_rounded, color: AppTheme.danger, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Vehicle exceeds verified cargo capacity! Please contact logistics hub to offload or reassign excess parcels.',
                      style: TextStyle(fontSize: 11, color: Color(0xFF9F1239), fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildVehicleDutyCard(LoadPlanningSummaryModel summary) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.line),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppTheme.line),
            ),
            child: Icon(
              summary.hubTransferOnly ? Icons.compare_arrows_rounded : Icons.two_wheeler_rounded,
              color: AppTheme.primary,
              size: 24,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  summary.hubTransferOnly ? 'Hub Transfer Duty' : 'Doorstep & Hub Courier',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
                Text(
                  '${summary.vehicleType ?? 'Vehicle'} Â· ${summary.plateNumber ?? 'Plate unrecorded'}',
                  style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: summary.hubTransferOnly ? Colors.blue.shade50 : AppTheme.primarySoft,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              summary.hubTransferOnly ? 'TRANSFERS' : 'STANDARD',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: summary.hubTransferOnly ? Colors.blue.shade700 : AppTheme.primary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildParcelCard(OrderModel order) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => OrderDetailsPage(orderId: order.id, initialOrder: order),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.inventory_2_outlined, color: AppTheme.ink),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.orderNumber,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      order.deliveryArea ?? order.deliveryAddress,
                      style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      order.statusLabel,
                      style: const TextStyle(fontSize: 11, color: AppTheme.primary, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    order.weightKgFormatted,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'â‚±${order.totalAmount.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.muted),
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
