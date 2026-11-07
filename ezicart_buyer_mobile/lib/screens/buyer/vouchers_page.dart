import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/voucher_model.dart';
import '../../services/api_service.dart';

class VouchersPage extends StatefulWidget {
  final bool selectMode;

  const VouchersPage({super.key, this.selectMode = false});

  @override
  State<VouchersPage> createState() => _VouchersPageState();
}

class _VouchersPageState extends State<VouchersPage> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<VoucherModel> _vouchers = [];
  bool _isLoading = true;
  String? _claimingCode;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _fetchVouchers();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchVouchers() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.vouchersEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((v) => VoucherModel.fromJson(v as Map<String, dynamic>))
              .toList();
          setState(() {
            _vouchers = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  Future<void> _claimVoucher(VoucherModel voucher) async {
    setState(() => _claimingCode = voucher.code);
    try {
      final res = await ApiService.post(ApiConfig.voucherClaimEndpoint(voucher.id), {});
      final body = jsonDecode(res.body);
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Voucher claimed successfully!'),
            backgroundColor: Colors.green,
          ),
        );
        _fetchVouchers();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(body['message'] ?? 'Unable to claim voucher.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _claimingCode = null);
    }
  }

  void _onUseVoucher(VoucherModel voucher) {
    if (widget.selectMode) {
      Navigator.of(context).pop(voucher.code);
      return;
    }

    Clipboard.setData(ClipboardData(text: voucher.code));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Voucher code "${voucher.code}" copied to clipboard! Apply it at checkout.'),
        backgroundColor: AppTheme.primary,
        action: SnackBarAction(
          label: 'OK',
          textColor: Colors.white,
          onPressed: () {},
        ),
      ),
    );
  }

  List<VoucherModel> _getFilteredVouchers(int tabIndex) {
    switch (tabIndex) {
      case 1:
        return _vouchers.where((v) => v.isPlatformVoucher).toList();
      case 2:
        return _vouchers.where((v) => !v.isPlatformVoucher).toList();
      case 3:
        return _vouchers.where((v) => v.isClaimed).toList();
      default:
        return _vouchers;
    }
  }

  Widget _buildVoucherCard(VoucherModel voucher) {
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);
    final isClaiming = _claimingCode == voucher.code;

    return Card(
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: voucher.isClaimed ? AppTheme.primary : AppTheme.line,
          width: voucher.isClaimed ? 1.5 : 1,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Discount badge
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppTheme.primarySoft,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Column(
                    children: [
                      Icon(
                        voucher.type == 'percent' ? Icons.percent : Icons.confirmation_number_outlined,
                        color: AppTheme.primary,
                        size: 20,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        voucher.type == 'percent' ? '${voucher.value.toInt()}%' : '₱${voucher.value.toInt()}',
                        style: const TextStyle(
                          color: AppTheme.primary,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 14),

                // Details
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: voucher.isPlatformVoucher ? Colors.blue.shade50 : Colors.purple.shade50,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              voucher.isPlatformVoucher ? 'PLATFORM' : 'SELLER SHOP',
                              style: TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                                color: voucher.isPlatformVoucher ? Colors.blue.shade800 : Colors.purple.shade800,
                              ),
                            ),
                          ),
                          const Spacer(),
                          Text(
                            voucher.code,
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                              letterSpacing: 0.5,
                              color: AppTheme.ink,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        voucher.description,
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Store: ${voucher.sellerName}',
                        style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                      ),
                      if (voucher.expiresAt != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          'Valid until: ${DateFormat('MMM dd, yyyy').format(voucher.expiresAt!)}',
                          style: TextStyle(fontSize: 11, color: Colors.orange.shade800),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            const Divider(height: 20),

            // Action row
            Row(
              children: [
                Text(
                  'Min. spend: ${currency.format(voucher.minSpend)}',
                  style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                ),
                const Spacer(),
                if (voucher.isClaimed) ...[
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      minimumSize: const Size(90, 32),
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                    ),
                    onPressed: () => _onUseVoucher(voucher),
                    child: Text(widget.selectMode ? 'Select' : 'Use Code', style: const TextStyle(fontSize: 12)),
                  ),
                ] else if (voucher.isAvailable) ...[
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size(90, 32),
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                    ),
                    onPressed: isClaiming ? null : () => _claimVoucher(voucher),
                    child: isClaiming
                        ? const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Claim', style: TextStyle(fontSize: 12)),
                  ),
                ] else ...[
                  const Text(
                    'Fully Claimed',
                    style: TextStyle(fontSize: 12, color: Colors.grey, fontWeight: FontWeight.bold),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.selectMode ? 'Select Voucher' : 'Voucher Center'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppTheme.primary,
          unselectedLabelColor: AppTheme.muted,
          indicatorColor: AppTheme.primary,
          isScrollable: true,
          tabs: const [
            Tab(text: 'All Vouchers'),
            Tab(text: 'Platform'),
            Tab(text: 'Seller Shops'),
            Tab(text: 'Claimed'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : TabBarView(
              controller: _tabController,
              children: List.generate(4, (index) {
                final list = _getFilteredVouchers(index);
                if (list.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.confirmation_number_outlined, size: 54, color: Colors.grey.shade400),
                        const SizedBox(height: 12),
                        Text(
                          index == 3 ? 'No claimed vouchers yet.' : 'No vouchers available.',
                          style: const TextStyle(fontSize: 14, color: AppTheme.muted),
                        ),
                      ],
                    ),
                  );
                }

                return RefreshIndicator(
                  onRefresh: _fetchVouchers,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: list.length,
                    itemBuilder: (_, i) => _buildVoucherCard(list[i]),
                  ),
                );
              }),
            ),
    );
  }
}

