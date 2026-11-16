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
import 'addresses_page.dart';
import 'automatic_dispatch_page.dart';
import 'change_password_page.dart';
import 'earnings_page.dart';
import 'history_page.dart';
import 'load_planning_page.dart';
import 'messages_page.dart';
import 'notifications_page.dart';
import 'order_details_page.dart';
import 'package_collection_scanner_page.dart';
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

class CourierDashboard extends StatefulWidget {
  const CourierDashboard({super.key});

  @override
  State<CourierDashboard> createState() => _CourierDashboardState();
}

class _CourierDashboardState extends State<CourierDashboard>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  int _unreadNotifications = 0;
  int _bottomNavIndex = 0;

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
      final res = await ApiService.get(
        ApiConfig.notificationsUnreadCountEndpoint,
      );
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
    final dateString = DateFormat('EEE, d MMM').format(DateTime.now());
    final isAvailable = user?.workAvailability == 'available';
    final dutyLabel = (user?.workAvailability ?? 'OFF DUTY').toUpperCase();

    return Scaffold(
      appBar: AppBar(
        flexibleSpace: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFFD63363), Color(0xFFBE2854)],
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
            ),
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'EZiCart Courier',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w900,
                color: AppTheme.ink,
                letterSpacing: -0.3,
              ),
            ),
            Text(
              _bottomNavIndex == 0
                  ? (user?.name ?? 'Courier Dispatch Board')
                  : _bottomNavIndex == 1
                  ? 'Active Parcels Tracking'
                  : _bottomNavIndex == 2
                  ? 'Messages & Chats'
                  : 'My Rider Profile',
              style: const TextStyle(
                fontFamily: 'Instrument Sans',
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
              overflow: TextOverflow.ellipsis,
            ),
            Row(
              children: [
                Text(
                  dateString,
                  style: const TextStyle(
                    fontSize: 10,
                    color: Colors.white70,
                    fontWeight: FontWeight.normal,
                  ),
                ),
                if (_bottomNavIndex == 0) ...[
                  const SizedBox(width: 6),
                  InkWell(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const WorkAvailabilityPage(),
                        ),
                      ).then((_) => _refresh());
                    },
                    borderRadius: BorderRadius.circular(4),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 5,
                        vertical: 1.5,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 6,
                            height: 6,
                            decoration: BoxDecoration(
                              color: isAvailable
                                  ? const Color(0xFF10B981)
                                  : Colors.amberAccent,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            isAvailable ? 'ON DUTY' : dutyLabel,
                            style: const TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.qr_code_scanner_rounded, size: 22),
            tooltip: 'Package Scanner & Collection',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const PackageCollectionScannerPage(),
                ),
              ).then((_) => _refresh());
            },
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(
                  Icons.notifications_outlined,
                  color: Colors.white,
                  size: 22,
                ),
                tooltip: 'Notifications',
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const NotificationsPage(),
                    ),
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
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    constraints: const BoxConstraints(
                      minWidth: 16,
                      minHeight: 16,
                    ),
                    child: Text(
                      _unreadNotifications > 99
                          ? '99+'
                          : _unreadNotifications.toString(),
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: AppTheme.primary,
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
            ],
          ),
          IconButton(
            icon: const Icon(
              Icons.refresh_rounded,
              color: Colors.white,
              size: 22,
            ),
            tooltip: 'Refresh',
            onPressed: _refresh,
          ),
        ],
      ),
      drawer: _buildDrawer(context, auth, user),
      body: Stack(
        fit: StackFit.expand,
        children: [
          Positioned.fill(
            child: Opacity(
              opacity: 0.045,
              child: Image.asset(
                'assets/images/ezicart-background.png',
                fit: BoxFit.cover,
                alignment: Alignment.center,
                errorBuilder: (_, __, ___) => const SizedBox.shrink(),
              ),
            ),
          ),
          IndexedStack(
            index: _bottomNavIndex,
            children: [
              _buildWorkboard(context, auth, courierProv, user),
              const TrackingPage(isEmbedded: true),
              const MessagesPage(isEmbedded: true),
              const RiderProfilePage(isEmbedded: true),
            ],
          ),
        ],
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: const Color(
            0xFFFFF7F9,
          ), // Soft EZiCart pink matching Buyer Mobile
          border: const Border(
            top: BorderSide(color: Color(0xFFFFDDE5), width: 1),
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFFBE2854).withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, -3),
            ),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _bottomNavIndex,
          onTap: (index) {
            setState(() => _bottomNavIndex = index);
          },
          backgroundColor: Colors.transparent,
          elevation: 0,
          type: BottomNavigationBarType.fixed,
          selectedItemColor: const Color(0xFFBE2854),
          unselectedItemColor: const Color(0xFF8A757D),
          selectedFontSize: 11,
          unselectedFontSize: 11,
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.dashboard_outlined),
              activeIcon: Icon(Icons.dashboard_rounded),
              label: 'Workboard',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.radar_outlined),
              activeIcon: Icon(Icons.radar_rounded),
              label: 'Active',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.chat_bubble_outline),
              activeIcon: Icon(Icons.chat_bubble),
              label: 'Messages',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.person_outline),
              activeIcon: Icon(Icons.person),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildWorkboard(
    BuildContext context,
    AuthProvider auth,
    CourierProvider courierProv,
    UserModel? user,
  ) {
    if (courierProv.isLoading &&
        courierProv.deliveries.isEmpty &&
        courierProv.availablePickups.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: AppTheme.primary),
      );
    }
    return RefreshIndicator(
      onRefresh: _refresh,
      child: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) {
          return [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Messages Banner
                    if (courierProv.errorMessage != null)
                      _buildAlertBanner(
                        courierProv.errorMessage!,
                        isError: true,
                      ),
                    if (courierProv.successMessage != null)
                      _buildAlertBanner(
                        courierProv.successMessage!,
                        isError: false,
                      ),

                    // Workboard Header Card (Priority callout + stats grid + quick actions)
                    _buildWorkboardHeaderCard(context, auth, courierProv, user),
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
                  labelStyle: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                  tabs: [
                    Tab(
                      text:
                          'Available Pickups (${courierProv.availablePickups.length})',
                    ),
                    Tab(
                      text:
                          'Accepted Pickups (${courierProv.claimedPickups.length})',
                    ),
                    Tab(
                      text: 'Hub Handoff (${courierProv.inTransitHub.length})',
                    ),
                    Tab(text: 'Deliveries (${courierProv.deliveries.length})'),
                    Tab(text: 'Returns (${courierProv.returns.length})'),
                    Tab(
                      text: 'Failed (${courierProv.failedDeliveries.length})',
                    ),
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
    );
  }

  Widget _buildAlertBanner(String message, {required bool isError}) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: isError ? const Color(0xFFFFF1F2) : const Color(0xFFECFDF5),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: isError ? const Color(0xFFFECDD3) : const Color(0xFFA7F3D0),
        ),
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
                color: isError
                    ? const Color(0xFF9F1239)
                    : const Color(0xFF065F46),
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

  Widget _buildWorkboardHeaderCard(
    BuildContext context,
    AuthProvider auth,
    CourierProvider courierProv,
    UserModel? user,
  ) {
    final stats = courierProv.stats;
    final isAvailable = user?.workAvailability == 'available';
    final dutyLabel = (user?.workAvailability ?? 'OFF DUTY').toUpperCase();
    final firstName =
        user?.firstName ??
        (user != null && user.name.isNotEmpty
            ? user.name.split(' ').first
            : 'Rider');

    // Priority delivery action callout
    String priorityTitle;
    String prioritySubtitle;
    IconData priorityIcon;
    Color priorityColor;
    VoidCallback? onPriorityTap;

    if (courierProv.deliveries.isNotEmpty) {
      final order = courierProv.deliveries.first;
      priorityTitle = 'Active Delivery: ${order.orderNumber}';
      prioritySubtitle =
          'To: ${order.recipientName} • ${order.deliveryAddress}';
      priorityIcon = Icons.local_shipping_rounded;
      priorityColor = AppTheme.primary;
      onPriorityTap = () {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) =>
                OrderDetailsPage(orderId: order.id, initialOrder: order),
          ),
        );
      };
    } else if (courierProv.claimedPickups.isNotEmpty) {
      final order = courierProv.claimedPickups.first;
      priorityTitle = 'Pickup in Progress: ${order.orderNumber}';
      prioritySubtitle = 'Collect from ${order.sellerName}';
      priorityIcon = Icons.store_rounded;
      priorityColor = AppTheme.warning;
      onPriorityTap = () => _tabController.animateTo(1);
    } else if (courierProv.inTransitHub.isNotEmpty) {
      final order = courierProv.inTransitHub.first;
      priorityTitle = 'Hub Handoff: ${order.orderNumber}';
      prioritySubtitle = 'Take collected parcel to Sorting Hub';
      priorityIcon = Icons.hub_rounded;
      priorityColor = AppTheme.info;
      onPriorityTap = () => _tabController.animateTo(2);
    } else if (courierProv.returns.isNotEmpty) {
      final order = courierProv.returns.first;
      priorityTitle = 'Seller Return: ${order.orderNumber}';
      prioritySubtitle = 'Handoff return parcel to seller';
      priorityIcon = Icons.assignment_return_rounded;
      priorityColor = Colors.purple;
      onPriorityTap = () => _tabController.animateTo(4);
    } else if (courierProv.availablePickups.isNotEmpty) {
      priorityTitle = '${courierProv.availablePickups.length} Pickups Waiting';
      prioritySubtitle = 'Review and accept available seller pickups';
      priorityIcon = Icons.add_task_rounded;
      priorityColor = AppTheme.success;
      onPriorityTap = () => _tabController.animateTo(0);
    } else {
      priorityTitle = 'All Deliveries Clear';
      prioritySubtitle = isAvailable
          ? 'Automated dispatch active • Waiting for next task'
          : 'Currently off duty';
      priorityIcon = Icons.check_circle_outline_rounded;
      priorityColor = isAvailable ? AppTheme.success : Colors.grey;
      onPriorityTap = null;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
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
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Greeting & Interactive Duty Pill
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              'Hello, $firstName',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: AppTheme.ink,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: 4),
                          const Icon(
                            Icons.verified_rounded,
                            size: 16,
                            color: AppTheme.primary,
                          ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        DateFormat('EEEE, d MMMM yyyy').format(DateTime.now()),
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppTheme.muted,
                        ),
                      ),
                    ],
                  ),
                ),
                InkWell(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const WorkAvailabilityPage(),
                      ),
                    ).then((_) => _refresh());
                  },
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: (isAvailable ? AppTheme.success : Colors.orange)
                          .withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: (isAvailable ? AppTheme.success : Colors.orange)
                            .withValues(alpha: 0.3),
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: isAvailable
                                ? AppTheme.success
                                : Colors.orange,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          isAvailable ? 'ON DUTY' : dutyLabel,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: isAvailable
                                ? const Color(0xFF065F46)
                                : const Color(0xFF92400E),
                          ),
                        ),
                        const SizedBox(width: 2),
                        Icon(
                          Icons.keyboard_arrow_right_rounded,
                          size: 14,
                          color: isAvailable
                              ? const Color(0xFF065F46)
                              : const Color(0xFF92400E),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),

          const Divider(height: 1, color: AppTheme.line),

          // 3 Consolidated Metric Pillars
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            child: Row(
              children: [
                Expanded(
                  child: _buildMetricTile(
                    label: 'Deliveries',
                    count: courierProv.deliveries.length,
                    icon: Icons.local_shipping_outlined,
                    color: AppTheme.primary,
                    onTap: () => _tabController.animateTo(3),
                  ),
                ),
                Container(width: 1, height: 38, color: AppTheme.line),
                Expanded(
                  child: _buildMetricTile(
                    label: 'Pickups',
                    count:
                        courierProv.claimedPickups.length +
                        courierProv.availablePickups.length,
                    icon: Icons.store_outlined,
                    color: AppTheme.warning,
                    onTap: () => _tabController.animateTo(
                      courierProv.claimedPickups.isNotEmpty ? 1 : 0,
                    ),
                  ),
                ),
                Container(width: 1, height: 38, color: AppTheme.line),
                Expanded(
                  child: _buildMetricTile(
                    label: 'Done Today',
                    count: stats['completed_today'] ?? 0,
                    icon: Icons.check_circle_outline_rounded,
                    color: AppTheme.success,
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const HistoryPage()),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),

          // Priority Callout Strip
          InkWell(
            onTap: onPriorityTap,
            child: Container(
              margin: const EdgeInsets.fromLTRB(12, 0, 12, 10),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: priorityColor.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: priorityColor.withValues(alpha: 0.2)),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(7),
                    decoration: BoxDecoration(
                      color: priorityColor.withValues(alpha: 0.15),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(priorityIcon, size: 16, color: priorityColor),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          priorityTitle,
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: priorityColor,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          prioritySubtitle,
                          style: TextStyle(
                            fontSize: 11,
                            color: Colors.grey.shade700,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  if (onPriorityTap != null) ...[
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: priorityColor,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'Open',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          SizedBox(width: 2),
                          Icon(
                            Icons.arrow_forward_rounded,
                            color: Colors.white,
                            size: 11,
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),

          // Quick Action Chips Row
          Container(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              child: Row(
                children: [
                  _buildQuickActionChip(
                    icon: Icons.scale_outlined,
                    label: 'Load Planning',
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const LoadPlanningPage(),
                        ),
                      );
                    },
                  ),
                  const SizedBox(width: 8),
                  _buildQuickActionChip(
                    icon: Icons.electric_moped_outlined,
                    label: 'Auto Dispatch',
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const AutomaticDispatchPage(),
                        ),
                      );
                    },
                  ),
                  const SizedBox(width: 8),
                  _buildQuickActionChip(
                    icon: Icons.qr_code_2_rounded,
                    label: 'Rider Badge',
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const RiderBadgePage(),
                        ),
                      );
                    },
                  ),
                  const SizedBox(width: 8),
                  _buildQuickActionChip(
                    icon: Icons.account_balance_wallet_outlined,
                    label: 'Wallet & COD',
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const WalletPage()),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMetricTile({
    required String label,
    required int count,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, size: 15, color: color),
                const SizedBox(width: 4),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    count.toString(),
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      color: color,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                color: Colors.grey.shade700,
                fontWeight: FontWeight.w500,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickActionChip({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: AppTheme.primary),
            const SizedBox(width: 5),
            Text(
              label,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: AppTheme.ink,
              ),
            ),
          ],
        ),
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
                  child: const Text(
                    'Accept Pickup',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                  ),
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
            label: const Text(
              'Scan & Confirm Collection',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            ),
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => PackageCollectionScannerPage(
                    order: order,
                    orderId: order.id,
                    expectedParcelCode: order.parcelCode,
                  ),
                ),
              );
              _refresh();
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
                Icon(
                  Icons.hourglass_top_rounded,
                  color: Color(0xFFB45309),
                  size: 16,
                ),
                SizedBox(width: 6),
                Expanded(
                  child: Text(
                    'Waiting for seller handover confirmation.',
                    style: TextStyle(
                      color: Color(0xFFB45309),
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          );
        } else {
          action = ElevatedButton.icon(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            icon: const Icon(Icons.navigation_rounded, size: 16),
            label: const Text(
              'Record Arrival at Seller',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            ),
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
      return _buildEmptyState(
        'No collected parcels in transit to sorting center.',
      );
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
                    style: TextStyle(
                      color: AppTheme.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
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
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
              ),
              icon: const Icon(Icons.play_arrow_rounded, size: 16),
              label: const Text(
                'Start Delivery (Out For Delivery)',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
              ),
              onPressed: () async {
                final res = await showDialog<Map<String, String>>(
                  context: context,
                  builder: (_) => ActionScanDialog(
                    title: 'Start Delivery',
                    message:
                        'Scan the parcel QR code or continue to start delivery.',
                    hint: order.parcelCode ?? 'EZP:...',
                    isCodeRequired: false,
                    confirmLabel: 'Start Delivery',
                  ),
                );
                if (res != null) {
                  await prov.startDelivery(
                    order.id,
                    parcelReference: res['code'],
                    method: res['method'] ?? 'manual',
                  );
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
                      Icon(
                        Icons.info_outline_rounded,
                        color: Color(0xFFE11D48),
                        size: 16,
                      ),
                      SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          'Waiting for Logistics Hub release before departing.',
                          style: TextStyle(
                            color: Color(0xFF9F1239),
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                          ),
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
                  child: const Text(
                    'Decline Assignment',
                    style: TextStyle(fontSize: 11),
                  ),
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
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.success,
                  ),
                  icon: const Icon(Icons.check_circle_rounded, size: 16),
                  label: const Text(
                    'Complete Delivery',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                  ),
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
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.danger,
                ),
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
            label: const Text(
              'Scan & Confirm Seller Return Handoff',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            ),
            onPressed: () async {
              final res = await showDialog<Map<String, String>>(
                context: context,
                builder: (_) => ActionScanDialog(
                  title: 'Confirm Seller Return',
                  message:
                      'Scan the return parcel QR code to record handoff to seller.',
                  hint: order.parcelCode ?? 'EZP:...',
                  confirmColor: Colors.purple,
                ),
              );
              if (res != null) {
                await prov.confirmReturnDelivery(
                  order.id,
                  res['code']!,
                  method: res['method'] ?? 'manual',
                );
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
      return _buildEmptyState(
        'No failed deliveries need follow-up. All parcels clear.',
      );
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
                    const Icon(
                      Icons.warning_amber_rounded,
                      color: AppTheme.danger,
                      size: 16,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Reason: ${order.deliveryFailureReason?.replaceAll('_', ' ').toUpperCase() ?? 'RECORDED FAILURE'}',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 11,
                        color: AppTheme.danger,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                const Text(
                  'Logistics Hub will review. Parcel must be physically returned to the hub before retry.',
                  style: TextStyle(fontSize: 11, color: Color(0xFF9F1239)),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.primary,
                      side: const BorderSide(color: AppTheme.primary),
                      padding: const EdgeInsets.symmetric(vertical: 8),
                    ),
                    icon: const Icon(Icons.schedule_send_rounded, size: 16),
                    label: const Text(
                      'Propose Redelivery / Retry',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => OrderDetailsPage(
                            orderId: order.id,
                            initialOrder: order,
                          ),
                        ),
                      ).then((_) => _refresh());
                    },
                  ),
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
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              color: AppTheme.ink,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Pull down to refresh dispatch board.',
            style: TextStyle(fontSize: 12, color: AppTheme.muted),
          ),
        ],
      ),
    );
  }

  Widget _buildDrawer(
    BuildContext context,
    AuthProvider auth,
    UserModel? user,
  ) {
    final isAvailable = user?.workAvailability == 'available';
    final dutyLabel = (user?.workAvailability ?? 'AVAILABLE').toUpperCase();
    final badge = user?.badgeCode ?? 'N/A';
    final vehicle = user?.vehicleType ?? 'Motorcycle';
    final plate = user?.plateNumber ?? 'Unregistered';

    return Drawer(
      backgroundColor: Colors.white,
      child: Column(
        children: [
          // Modern Premium Header
          Container(
            padding: EdgeInsets.only(
              top: MediaQuery.of(context).padding.top + 16,
              left: 16,
              right: 16,
              bottom: 16,
            ),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFFD63363), Color(0xFFBE2854)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor: Colors.white,
                      backgroundImage: user?.avatarUrl != null
                          ? NetworkImage(user!.avatarUrl!)
                          : null,
                      child: user?.avatarUrl == null
                          ? Text(
                              user?.name != null && user!.name.isNotEmpty
                                  ? user.name[0].toUpperCase()
                                  : 'C',
                              style: const TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.bold,
                                color: AppTheme.primary,
                              ),
                            )
                          : null,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user?.name ?? 'Courier Rider',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 2),
                          Text(
                            user?.email ?? 'rider@ezicart.trade',
                            style: const TextStyle(
                              color: Colors.white70,
                              fontSize: 11,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white24,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              'BADGE #$badge • $vehicle ($plate)',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Duty Status Switcher Bar inside Header
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 7,
                            height: 7,
                            decoration: BoxDecoration(
                              color: isAvailable
                                  ? AppTheme.success
                                  : Colors.amberAccent,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            isAvailable
                                ? 'Active on Dispatch'
                                : 'Off Duty / Inactive',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      InkWell(
                        onTap: () {
                          Navigator.of(context).pop();
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => const WorkAvailabilityPage(),
                            ),
                          ).then((_) => _refresh());
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            dutyLabel,
                            style: const TextStyle(
                              color: AppTheme.primary,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Scrollable Menu Items
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
              children: [
                _buildDrawerSectionHeader('Operations'),
                _buildDrawerItem(
                  icon: Icons.dashboard_rounded,
                  title: 'Workboard',
                  isSelected: true,
                  onTap: () => Navigator.of(context).pop(),
                ),
                _buildDrawerItem(
                  icon: Icons.qr_code_scanner_rounded,
                  title: 'Parcel Scanner & Collection',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context)
                        .push(
                          MaterialPageRoute(
                            builder: (_) =>
                                const PackageCollectionScannerPage(),
                          ),
                        )
                        .then((_) => _refresh());
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.radar_rounded,
                  title: 'Active Parcels',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const TrackingPage()),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.history_rounded,
                  title: 'Delivery History',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const HistoryPage()),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.scale_outlined,
                  title: 'Weight & Load Planning',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const LoadPlanningPage(),
                      ),
                    );
                  },
                ),

                const Divider(height: 16),

                _buildDrawerSectionHeader('Records & Dispatch'),
                _buildDrawerItem(
                  icon: Icons.electric_moped_outlined,
                  title: 'Automatic Dispatch',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const AutomaticDispatchPage(),
                      ),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.assignment_return_outlined,
                  title: 'Approved Return Pickups',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const ReturnPickupsPage(),
                      ),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.fact_check_outlined,
                  title: 'Journey & Evidence',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const ParcelEvidencePage(),
                      ),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.report_problem_outlined,
                  title: 'Parcel Incidents & Claims',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const ParcelIncidentsPage(),
                      ),
                    );
                  },
                ),

                const Divider(height: 16),

                _buildDrawerSectionHeader('Finances'),
                _buildDrawerItem(
                  icon: Icons.account_balance_wallet_outlined,
                  title: 'Finance & Wallet',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const WalletPage()),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.paid_outlined,
                  title: 'Earnings & Tariff Allocations',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const EarningsPage()),
                    );
                  },
                ),

                const Divider(height: 16),

                _buildDrawerSectionHeader('Communication & Account'),
                _buildDrawerItem(
                  icon: Icons.chat_bubble_outline_rounded,
                  title: 'Direct Messages',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const MessagesPage()),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.notifications_outlined,
                  title: 'Notifications',
                  trailing: _unreadNotifications > 0
                      ? Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: AppTheme.danger,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '$_unreadNotifications',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        )
                      : null,
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context)
                        .push(
                          MaterialPageRoute(
                            builder: (_) => const NotificationsPage(),
                          ),
                        )
                        .then((_) => _fetchUnreadNotifications());
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.badge_outlined,
                  title: 'My Rider Profile',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context)
                        .push(
                          MaterialPageRoute(
                            builder: (_) => const RiderProfilePage(),
                          ),
                        )
                        .then((_) => _refresh());
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.location_on_outlined,
                  title: 'Address Book',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const AddressesPage(),
                      ),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.qr_code_2_rounded,
                  title: 'My QR Badge',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const RiderBadgePage()),
                    );
                  },
                ),
                _buildDrawerItem(
                  icon: Icons.lock_reset_rounded,
                  title: 'Change Password',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const ChangePasswordPage(),
                      ),
                    );
                  },
                ),
              ],
            ),
          ),

          const Divider(height: 1),

          // Drawer Footer with Sign Out
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: Colors.grey.shade50,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                InkWell(
                  onTap: () async {
                    final confirm = await showDialog<bool>(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        title: const Text('Sign Out'),
                        content: const Text(
                          'Are you sure you want to log out of the courier app?',
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(ctx, false),
                            child: const Text('Cancel'),
                          ),
                          ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppTheme.danger,
                              foregroundColor: Colors.white,
                            ),
                            onPressed: () => Navigator.pop(ctx, true),
                            child: const Text('Sign Out'),
                          ),
                        ],
                      ),
                    );
                    if (confirm == true && context.mounted) {
                      Navigator.of(context).pop();
                      await auth.logout();
                    }
                  },
                  borderRadius: BorderRadius.circular(8),
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.logout_rounded,
                          color: AppTheme.danger,
                          size: 18,
                        ),
                        SizedBox(width: 8),
                        Text(
                          'Sign Out',
                          style: TextStyle(
                            color: AppTheme.danger,
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const Text(
                  'v1.0.0 • EZiCart',
                  style: TextStyle(fontSize: 10, color: Colors.grey),
                ),
              ],
            ),
          ),
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
      leading: Icon(
        icon,
        color: isSelected ? AppTheme.primary : Colors.grey.shade700,
        size: 20,
      ),
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
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Container(color: Colors.white, child: _tabBar);
  }

  @override
  bool shouldRebuild(_SliverAppBarDelegate oldDelegate) {
    return false;
  }
}
