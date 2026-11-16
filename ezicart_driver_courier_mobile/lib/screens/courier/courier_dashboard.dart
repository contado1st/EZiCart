import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/user_model.dart';
import '../../providers/auth_provider.dart';
import '../../providers/courier_provider.dart';
import '../../services/api_service.dart';
import 'automatic_dispatch_page.dart';
import 'change_password_page.dart';
import 'earnings_page.dart';
import 'history_page.dart';
import 'load_planning_page.dart';
import 'messages_page.dart';
import 'notifications_page.dart';
import 'order_details_page.dart';
import 'parcel_evidence_page.dart';
import 'parcel_incidents_page.dart';
import 'return_pickups_page.dart';
import 'rider_badge_page.dart';
import 'rider_profile_page.dart';
import 'tracking_page.dart';
import 'wallet_page.dart';
import 'work_availability_page.dart';
import 'widgets/action_scan_dialog.dart';
import 'widgets/complete_delivery_dialog.dart';
import 'widgets/fail_delivery_dialog.dart';
import 'widgets/order_card.dart';
import 'widgets/stat_card.dart';

class CourierDashboard extends StatefulWidget {
  const CourierDashboard({super.key});

  @override
  State<CourierDashboard> createState() => _CourierDashboardState();
}

class _CourierDashboardState extends State<CourierDashboard> with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  int _unreadNotifications = 0;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 6, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _refresh();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchUnreadNotifications() async {
    try {
      final res = await ApiService.get(ApiConfig.notificationsUnreadCountEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            _unreadNotifications = (data['unread_count'] as num?)?.toInt() ?? 0;
          });
        }
      }
    } catch (_) {}
  }

  Future<void> _refresh() async {
    await Future.wait([
      context.read<CourierProvider>().fetchDashboard(),
      context.read<AuthProvider>().refreshUser(),
      _fetchUnreadNotifications(),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final courierProv = context.watch<CourierProvider>();
    final user = auth.user;
    final stats = courierProv.stats;

    final dateString = DateFormat('EEE, d MMM').format(DateTime.now());
    final isAvailable = user?.workAvailability == 'available';
    final dutyLabel = (user?.workAvailability ?? 'OFF DUTY').toUpperCase();

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              user?.name ?? 'Courier Dispatch Board',
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
              overflow: TextOverflow.ellipsis,
            ),
            Row(
              children: [
                Text(
                  dateString,
                  style: const TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.normal),
                ),
                const SizedBox(width: 6),
                InkWell(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const WorkAvailabilityPage()),
                    ).then((_) => _refresh());
                  },
                  borderRadius: BorderRadius.circular(4),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                    decoration: BoxDecoration(
                      color: (isAvailable ? AppTheme.success : Colors.orange).withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 5,
                          height: 5,
                          decoration: BoxDecoration(
                            color: isAvailable ? AppTheme.success : Colors.orange,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 4),
                        Text(
                          isAvailable ? 'ON DUTY' : dutyLabel,
                          style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.bold,
                            color: isAvailable ? AppTheme.success : Colors.orange.shade800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
        actions: [
          // Messages icon
          IconButton(
            icon: const Icon(Icons.chat_bubble_outline_rounded, size: 20),
            tooltip: 'Messages',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const MessagesPage()),
              );
            },
          ),
          // Notifications with badge
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.notifications_outlined, size: 22),
                tooltip: 'Notifications',
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const NotificationsPage()),
                  ).then((_) => _fetchUnreadNotifications());
                },
              ),
              if (_unreadNotifications > 0)
                Positioned(
                  top: 8,
                  right: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: AppTheme.danger,
                      shape: BoxShape.circle,
                    ),
                    constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                    child: Text(
                      _unreadNotifications > 99 ? '99+' : _unreadNotifications.toString(),
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
            ],
          ),
          // Rider Badge
          IconButton(
            icon: const Icon(Icons.qr_code_2_rounded, color: AppTheme.primary, size: 22),
            tooltip: 'My Rider Badge',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const RiderBadgePage()),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded, size: 22),
            onPressed: _refresh,
          ),
        ],
      ),
      drawer: _buildDrawer(context, auth, user),
      body: courierProv.isLoading && courierProv.deliveries.isEmpty && courierProv.availablePickups.isEmpty
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _refresh,
              child: NestedScrollView(
                headerSliverBuilder: (context, innerBoxIsScrolled) {
                  return [
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // Messages Banner
                            if (courierProv.errorMessage != null)
                              _buildAlertBanner(courierProv.errorMessage!, isError: true),
                            if (courierProv.successMessage != null)
                              _buildAlertBanner(courierProv.successMessage!, isError: false),

                            // Work Summary Metrics Grid
                            _buildStatsGrid(stats),
                            const SizedBox(height: 12),

                            // Priority Hero Card
                            _buildPriorityHeroCard(courierProv),
                            const SizedBox(height: 12),
                          ],
                        ),
                      ),
                    ),
                    SliverPersistentHeader(
                      pinned: true,
                      delegate: _SliverAppBarDelegate(
                        TabBar(
                          controller: _tabController,
                          isScrollable: true,
                          tabAlignment: TabAlignment.start,
                          labelColor: AppTheme.primary,
                          unselectedLabelColor: AppTheme.muted,
                          indicatorColor: AppTheme.primary,
                          indicatorWeight: 3,
                          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                          tabs: [
                            Tab(text: 'Available Pickups (${courierProv.availablePickups.length})'),
                            Tab(text: 'Accepted Pickups (${courierProv.claimedPickups.length})'),
                            Tab(text: 'Hub Handoff (${courierProv.inTransitHub.length})'),
                            Tab(text: 'Deliveries (${courierProv.deliveries.length})'),
                            Tab(text: 'Returns (${courierProv.returns.length})'),
                            Tab(text: 'Failed (${courierProv.failedDeliveries.length})'),
                          ],
                        ),
                      ),
                    ),
                  ];
                },
                body: TabBarView(
                  controller: _tabController,
                  children: [
                    _buildAvailablePickupsTab(courierProv),
                    _buildAcceptedPickupsTab(courierProv),
                    _buildHubHandoffTab(courierProv),
                    _buildDeliveriesTab(courierProv),
                    _buildReturnsTab(courierProv),
                    _buildFailedDeliveriesTab(courierProv),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildAlertBanner(String message, {required bool isError}) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: isError ? const Color(0xFFFFF1F2) : const Color(0xFFECFDF5),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: isError ? const Color(0xFFFECDD3) : const Color(0xFFA7F3D0)),
      ),
      child: Row(
        children: [
          Icon(
            isError ? Icons.error_outline_rounded : Icons.check_circle_outline,
            color: isError ? AppTheme.danger : AppTheme.success,
            size: 18,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontSize: 12,
                color: isError ? const Color(0xFF9F1239) : const Color(0xFF065F46),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close_rounded, size: 16),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
            onPressed: () => context.read<CourierProvider>().clearMessages(),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsGrid(Map<String, int> stats) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 3,
      crossAxisSpacing: 8,
      mainAxisSpacing: 8,
      childAspectRatio: 1.15,
      children: [
        StatCard(
          label: 'Pickups',
          count: stats['claimed_pickups'] ?? 0,
          sublabel: 'Accepted tasks',
          icon: Icons.store_rounded,
          color: AppTheme.warning,
          onTap: () => _tabController.animateTo(1),
        ),
        StatCard(
          label: 'To Hub',
          count: stats['in_transit_hub'] ?? 0,
          sublabel: 'Hub handoff',
          icon: Icons.hub_rounded,
          color: AppTheme.info,
          onTap: () => _tabController.animateTo(2),
        ),
        StatCard(
          label: 'Deliveries',
          count: stats['assigned_delivery'] ?? 0,
          sublabel: 'Assigned load',
          icon: Icons.local_shipping_rounded,
          color: AppTheme.primary,
          onTap: () => _tabController.animateTo(3),
        ),
        StatCard(
          label: 'Returns',
          count: stats['returning_to_seller'] ?? 0,
          sublabel: 'Seller handoff',
          icon: Icons.assignment_return_rounded,
          color: Colors.purple,
          onTap: () => _tabController.animateTo(4),
        ),
        StatCard(
          label: 'Delivered',
          count: stats['completed_today'] ?? 0,
          sublabel: 'Done today',
          icon: Icons.check_circle_rounded,
          color: AppTheme.success,
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const HistoryPage()),
            );
          },
        ),
        StatCard(
          label: 'Failed',
          count: stats['failed_today'] ?? 0,
          sublabel: 'Review needed',
          icon: Icons.warning_amber_rounded,
          color: AppTheme.danger,
          onTap: () => _tabController.animateTo(5),
        ),
      ],
    );
  }

  Widget _buildPriorityHeroCard(CourierProvider courierProv) {
    String title;
    String description;
    VoidCallback? onAction;
    String actionLabel;

    if (courierProv.deliveries.isNotEmpty) {
      final order = courierProv.deliveries.first;
      title = 'Doorstep Delivery: ${order.orderNumber}';
      description = 'Deliver to ${order.recipientName} in ${order.deliveryAddress}.';
      actionLabel = 'Open Delivery';
      onAction = () {
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => OrderDetailsPage(orderId: order.id, initialOrder: order)),
        );
      };
    } else if (courierProv.claimedPickups.isNotEmpty) {
      final order = courierProv.claimedPickups.first;
      title = 'Pickup in Progress: ${order.orderNumber}';
      description = order.pickupArrivedAt != null
          ? 'Collect parcel from ${order.sellerName} at ${order.sellerAddress}.'
          : 'Head to ${order.sellerName} and record arrival.';
      actionLabel = 'Continue Pickup';
      onAction = () => _tabController.animateTo(1);
    } else if (courierProv.inTransitHub.isNotEmpty) {
      final order = courierProv.inTransitHub.first;
      title = 'Hub Handoff: ${order.orderNumber}';
      description = 'Take collected parcel ${order.orderNumber} to the sorting hub.';
      actionLabel = 'View Hub Parcels';
      onAction = () => _tabController.animateTo(2);
    } else if (courierProv.returns.isNotEmpty) {
      final order = courierProv.returns.first;
      title = 'Seller Return: ${order.orderNumber}';
      description = 'Complete seller return for parcel ${order.orderNumber}.';
      actionLabel = 'Continue Return';
      onAction = () => _tabController.animateTo(4);
    } else if (courierProv.availablePickups.isNotEmpty) {
      title = 'Available Seller Pickup';
      description = 'You have ${courierProv.availablePickups.length} pickup request(s) waiting for acceptance.';
      actionLabel = 'Review Pickups';
      onAction = () => _tabController.animateTo(0);
    } else {
      title = 'All caught up!';
      description = 'You have no open parcel tasks right now. Great job!';
      actionLabel = 'Check History';
      onAction = () {
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => const HistoryPage()));
      };
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.line),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
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
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: AppTheme.primarySoft,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  'PRIORITY ACTION',
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.primary),
                ),
              ),
              const Icon(Icons.arrow_forward_rounded, size: 16, color: AppTheme.primary),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.ink),
          ),
          const SizedBox(height: 4),
          Text(
            description,
            style: const TextStyle(fontSize: 12, color: AppTheme.muted),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                padding: const EdgeInsets.symmetric(vertical: 10),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              onPressed: onAction,
              child: Text(
                actionLabel,
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // 1. Available Pickups Tab
  Widget _buildAvailablePickupsTab(CourierProvider prov) {
    if (prov.availablePickups.isEmpty) {
      return _buildEmptyState('No unclaimed seller pickups available.');
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: prov.availablePickups.length,
      itemBuilder: (ctx, i) {
        final order = prov.availablePickups[i];
        return OrderCard(
          order: order,
          trailingAction: Row(
            children: [
              Expanded(
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                  onPressed: () => prov.claimPickup(order.id),
                  child: const Text('Accept Pickup', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                ),
              ),
              const SizedBox(width: 8),
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.danger,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                ),
                onPressed: () => prov.declinePickup(order.id),
                child: const Text('Decline', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
        );
      },
    );
  }

  // 2. Accepted Pickups Tab
  Widget _buildAcceptedPickupsTab(CourierProvider prov) {
    if (prov.claimedPickups.isEmpty) {
      return _buildEmptyState('No accepted pickups waiting for collection.');
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: prov.claimedPickups.length,
      itemBuilder: (ctx, i) {
        final order = prov.claimedPickups[i];
        Widget action;

        if (order.pickupArrivedAt != null && order.sellerHandoverAt != null) {
          action = ElevatedButton.icon(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            icon: const Icon(Icons.qr_code_scanner_rounded, size: 16),
            label: const Text('Scan & Confirm Possession', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
            onPressed: () async {
              final res = await showDialog<Map<String, String>>(
                context: context,
                builder: (_) => ActionScanDialog(
                  title: 'Confirm Pickup',
                  message: 'Scan the parcel QR code or enter label code.',
                  hint: order.parcelCode ?? 'EZP:...',
                ),
              );
              if (res != null) {
                await prov.confirmPickup(order.id, res['code']!, method: res['method'] ?? 'manual');
              }
            },
          );
        } else if (order.pickupArrivedAt != null) {
          action = Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: const Color(0xFFFEF3C7),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Row(
              children: [
                Icon(Icons.hourglass_top_rounded, color: Color(0xFFB45309), size: 16),
                SizedBox(width: 6),
                Expanded(
                  child: Text(
                    'Waiting for seller handover confirmation.',
                    style: TextStyle(color: Color(0xFFB45309), fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          );
        } else {
          action = ElevatedButton.icon(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            icon: const Icon(Icons.navigation_rounded, size: 16),
            label: const Text('Record Arrival at Seller', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
            onPressed: () => prov.arriveAtPickup(order.id),
          );
        }

        return OrderCard(order: order, trailingAction: action);
      },
    );
  }

  // 3. Hub Handoff Tab
  Widget _buildHubHandoffTab(CourierProvider prov) {
    if (prov.inTransitHub.isEmpty) {
      return _buildEmptyState('No collected parcels in transit to sorting center.');
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: prov.inTransitHub.length,
      itemBuilder: (ctx, i) {
        final order = prov.inTransitHub[i];
        return OrderCard(
          order: order,
          trailingAction: Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: AppTheme.primarySoft,
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Row(
              children: [
                Icon(Icons.hub_rounded, color: AppTheme.primary, size: 16),
                SizedBox(width: 6),
                Expanded(
                  child: Text(
                    'Collected. Travel to sorting hub for receipt scan.',
                    style: TextStyle(color: AppTheme.primary, fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // 4. Deliveries Tab
  Widget _buildDeliveriesTab(CourierProvider prov) {
    if (prov.deliveries.isEmpty) {
      return _buildEmptyState('No doorstep deliveries assigned to you.');
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: prov.deliveries.length,
      itemBuilder: (ctx, i) {
        final order = prov.deliveries[i];
        Widget action;

        if (order.status == 'ASSIGNED_TO_RIDER') {
          if (order.hubReleasedAt != null) {
            action = ElevatedButton.icon(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
              icon: const Icon(Icons.play_arrow_rounded, size: 16),
              label: const Text('Start Delivery (Out For Delivery)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
              onPressed: () async {
                final res = await showDialog<Map<String, String>>(
                  context: context,
                  builder: (_) => ActionScanDialog(
                    title: 'Start Delivery',
                    message: 'Scan the parcel QR code or continue to start delivery.',
                    hint: order.parcelCode ?? 'EZP:...',
                    isCodeRequired: false,
                    confirmLabel: 'Start Delivery',
                  ),
                );
                if (res != null) {
                  await prov.startDelivery(order.id, parcelReference: res['code'], method: res['method'] ?? 'manual');
                }
              },
            );
          } else {
            action = Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF1F2),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.info_outline_rounded, color: Color(0xFFE11D48), size: 16),
                      SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          'Waiting for Logistics Hub release before departing.',
                          style: TextStyle(color: Color(0xFF9F1239), fontSize: 11, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 6),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppTheme.danger,
                    padding: const EdgeInsets.symmetric(vertical: 8),
                  ),
                  onPressed: () => prov.declineDeliveryAssignment(order.id),
                  child: const Text('Decline Assignment', style: TextStyle(fontSize: 11)),
                ),
              ],
            );
          }
        } else {
          // OUT_FOR_DELIVERY
          action = Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(backgroundColor: AppTheme.success),
                  icon: const Icon(Icons.check_circle_rounded, size: 16),
                  label: const Text('Complete Delivery', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  onPressed: () async {
                    final res = await showDialog<Map<String, dynamic>>(
                      context: context,
                      builder: (_) => CompleteDeliveryDialog(order: order),
                    );
                    if (res != null) {
                      await prov.completeDelivery(
                        orderId: order.id,
                        recipientConfirmation: res['recipient_confirmation'],
                        deliveryCode: res['delivery_code'],
                        codCollectedAmount: res['cod_collected_amount'],
                        deliveryNotes: res['delivery_notes'],
                        proofFilePath: res['proof_file_path'],
                      );
                    }
                  },
                ),
              ),
              const SizedBox(width: 8),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger),
                icon: const Icon(Icons.cancel_outlined, size: 16),
                label: const Text('Failed', style: TextStyle(fontSize: 12)),
                onPressed: () async {
                  final res = await showDialog<Map<String, dynamic>>(
                    context: context,
                    builder: (_) => FailDeliveryDialog(order: order),
                  );
                  if (res != null) {
                    await prov.failDelivery(
                      orderId: order.id,
                      failureReason: res['failure_reason'],
                      deliveryNotes: res['delivery_notes'],
                    );
                  }
                },
              ),
            ],
          );
        }

        return OrderCard(order: order, trailingAction: action);
      },
    );
  }

  // 5. Returns Tab
  Widget _buildReturnsTab(CourierProvider prov) {
    if (prov.returns.isEmpty) {
      return _buildEmptyState('No return parcels waiting for seller handoff.');
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: prov.returns.length,
      itemBuilder: (ctx, i) {
        final order = prov.returns[i];
        return OrderCard(
          order: order,
          trailingAction: ElevatedButton.icon(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.purple),
            icon: const Icon(Icons.assignment_return_rounded, size: 16),
            label: const Text('Scan & Confirm Seller Return Handoff', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
            onPressed: () async {
              final res = await showDialog<Map<String, String>>(
                context: context,
                builder: (_) => ActionScanDialog(
                  title: 'Confirm Seller Return',
                  message: 'Scan the return parcel QR code to record handoff to seller.',
                  hint: order.parcelCode ?? 'EZP:...',
                  confirmColor: Colors.purple,
                ),
              );
              if (res != null) {
                await prov.confirmReturnDelivery(order.id, res['code']!, method: res['method'] ?? 'manual');
              }
            },
          ),
        );
      },
    );
  }

  // 6. Failed Deliveries Tab
  Widget _buildFailedDeliveriesTab(CourierProvider prov) {
    if (prov.failedDeliveries.isEmpty) {
      return _buildEmptyState('No failed deliveries need follow-up. All parcels clear.');
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: prov.failedDeliveries.length,
      itemBuilder: (ctx, i) {
        final order = prov.failedDeliveries[i];
        return OrderCard(
          order: order,
          trailingAction: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF1F2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.warning_amber_rounded, color: AppTheme.danger, size: 16),
                    const SizedBox(width: 6),
                    Text(
                      'Reason: ${order.deliveryFailureReason?.replaceAll('_', ' ').toUpperCase() ?? 'RECORDED FAILURE'}',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.danger),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                const Text(
                  'Logistics Hub will review. Parcel must be physically returned to the hub before retry.',
                  style: TextStyle(fontSize: 11, color: Color(0xFF9F1239)),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildEmptyState(String message) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.inbox_outlined, size: 48, color: AppTheme.muted),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.ink),
          ),
          const SizedBox(height: 6),
          const Text('Pull down to refresh dispatch board.', style: TextStyle(fontSize: 12, color: AppTheme.muted)),
        ],
      ),
    );
  }

  Widget _buildDrawer(BuildContext context, AuthProvider auth, UserModel? user) {
    final drawerDuty = (user?.workAvailability ?? 'AVAILABLE').toUpperCase();
    return Drawer(
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          UserAccountsDrawerHeader(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFFD63363), Color(0xFFBE2854)],
              ),
            ),
            accountName: Text(
              user?.name ?? 'Courier Rider',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            accountEmail: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(user?.email ?? 'rider@ezicart.trade', style: const TextStyle(fontSize: 12)),
                const SizedBox(height: 2),
                Text(
                  'Badge: ${user?.badgeCode ?? 'N/A'} • $drawerDuty',
                  style: const TextStyle(fontSize: 11, color: Colors.white70),
                ),
              ],
            ),
            currentAccountPicture: CircleAvatar(
              backgroundColor: Colors.white,
              backgroundImage: user?.avatarUrl != null ? NetworkImage(user!.avatarUrl!) : null,
              child: user?.avatarUrl == null
                  ? Text(
                      user?.name != null && user!.name.isNotEmpty ? user.name[0].toUpperCase() : 'C',
                      style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppTheme.primary),
                    )
                  : null,
            ),
          ),

          // SECTION A: DELIVERY OPERATIONS
          _buildDrawerSectionHeader('Delivery Operations'),
          _buildDrawerItem(
            icon: Icons.dashboard_outlined,
            title: 'Workboard',
            isSelected: true,
            onTap: () => Navigator.of(context).pop(),
          ),
          _buildDrawerItem(
            icon: Icons.radar_rounded,
            title: 'Active Parcels',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TrackingPage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.history_rounded,
            title: 'Delivery History',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const HistoryPage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.paid_outlined,
            title: 'Earnings & Statements',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EarningsPage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.badge_outlined,
            title: 'My Rider Profile',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RiderProfilePage())).then((_) => _refresh());
            },
          ),
          _buildDrawerItem(
            icon: Icons.tune_rounded,
            title: 'Work Availability',
            trailing: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: (user?.workAvailability == 'available' ? AppTheme.success : Colors.orange).withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                drawerDuty,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                  color: user?.workAvailability == 'available' ? AppTheme.success : Colors.orange.shade800,
                ),
              ),
            ),
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const WorkAvailabilityPage())).then((_) => _refresh());
            },
          ),

          const Divider(height: 16),

          // SECTION B: PARCEL RECORDS
          _buildDrawerSectionHeader('Parcel Records'),
          _buildDrawerItem(
            icon: Icons.fact_check_outlined,
            title: 'Journey & Evidence',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ParcelEvidencePage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.report_problem_outlined,
            title: 'Parcel Incidents & Claims',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ParcelIncidentsPage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.electric_moped_outlined,
            title: 'Automatic Dispatch',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AutomaticDispatchPage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.assignment_return_outlined,
            title: 'Approved Return Pickups',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ReturnPickupsPage()));
            },
          ),

          const Divider(height: 16),

          // SECTION C: PLANNING
          _buildDrawerSectionHeader('Planning'),
          _buildDrawerItem(
            icon: Icons.scale_outlined,
            title: 'Weight & Load Planning',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoadPlanningPage()));
            },
          ),

          const Divider(height: 16),

          // SECTION D: ACCOUNT
          _buildDrawerSectionHeader('Account'),
          _buildDrawerItem(
            icon: Icons.notifications_outlined,
            title: 'Notifications',
            trailing: _unreadNotifications > 0
                ? Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.danger,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$_unreadNotifications',
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                    ),
                  )
                : null,
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsPage())).then((_) => _fetchUnreadNotifications());
            },
          ),
          _buildDrawerItem(
            icon: Icons.account_balance_wallet_outlined,
            title: 'Finance & Wallet',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const WalletPage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.qr_code_2_rounded,
            title: 'My Rider Badge & QR',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RiderBadgePage()));
            },
          ),
          _buildDrawerItem(
            icon: Icons.lock_reset_rounded,
            title: 'Change Password',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ChangePasswordPage()));
            },
          ),

          const Divider(height: 16),

          // SECTION E: MESSAGES
          _buildDrawerSectionHeader('Messages'),
          _buildDrawerItem(
            icon: Icons.chat_bubble_outline_rounded,
            title: 'Direct Messages',
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const MessagesPage()));
            },
          ),

          const Divider(height: 24),

          // SIGN OUT
          ListTile(
            dense: true,
            leading: const Icon(Icons.logout_rounded, color: AppTheme.danger, size: 20),
            title: const Text('Sign Out', style: TextStyle(color: AppTheme.danger, fontWeight: FontWeight.bold, fontSize: 13)),
            onTap: () async {
              Navigator.of(context).pop();
              await auth.logout();
            },
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _buildDrawerSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Text(
        title.toUpperCase(),
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w700,
          color: Colors.grey.shade600,
          letterSpacing: 0.6,
        ),
      ),
    );
  }

  Widget _buildDrawerItem({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
    Widget? trailing,
    bool isSelected = false,
  }) {
    return ListTile(
      dense: true,
      visualDensity: VisualDensity.compact,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 0),
      leading: Icon(icon, color: isSelected ? AppTheme.primary : Colors.grey.shade700, size: 20),
      title: Text(
        title,
        style: TextStyle(
          fontSize: 13,
          fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
          color: isSelected ? AppTheme.primary : AppTheme.ink,
        ),
      ),
      trailing: trailing,
      selected: isSelected,
      onTap: onTap,
    );
  }
}

class _SliverAppBarDelegate extends SliverPersistentHeaderDelegate {
  final TabBar _tabBar;

  _SliverAppBarDelegate(this._tabBar);

  @override
  double get minExtent => _tabBar.preferredSize.height;
  @override
  double get maxExtent => _tabBar.preferredSize.height;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Container(
      color: Colors.white,
      child: _tabBar,
    );
  }

  @override
  bool shouldRebuild(_SliverAppBarDelegate oldDelegate) {
    return false;
  }
}
