import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:http/http.dart' as http;
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/wallet_model.dart';
import '../../services/api_service.dart';

class WalletPage extends StatefulWidget {
  final int initialTabIndex;

  const WalletPage({super.key, this.initialTabIndex = 0});

  @override
  State<WalletPage> createState() => _WalletPageState();
}

class _WalletPageState extends State<WalletPage> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final NumberFormat _peso = NumberFormat.currency(locale: 'en_PH', symbol: '₱');

  bool _isLoading = true;
  String? _errorMessage;

  WalletBalances _balances = WalletBalances();
  List<WalletTransaction> _transactions = [];
  List<DemoFundingIntent> _fundingIntents = [];
  List<WalletWithdrawal> _withdrawals = [];
  List<WalletFinanceRecord> _financeRecords = [];
  List<WalletRefundRecord> _refundRecords = [];

  // Demo funding form controllers
  final _fundingAmountCtrl = TextEditingController();
  final _fundingReasonCtrl = TextEditingController();
  final _fundingReceiptCtrl = TextEditingController();
  String _selectedFundingKind = 'demo_credit';
  File? _fundingEvidenceFile;
  bool _isSubmittingFunding = false;

  // Withdrawal form controllers
  final _withdrawAmountCtrl = TextEditingController();
  final _withdrawDestinationCtrl = TextEditingController();
  bool _isSubmittingWithdrawal = false;

  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this, initialIndex: widget.initialTabIndex);
    _loadWalletData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _fundingAmountCtrl.dispose();
    _fundingReasonCtrl.dispose();
    _fundingReceiptCtrl.dispose();
    _withdrawAmountCtrl.dispose();
    _withdrawDestinationCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadWalletData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiService.get(ApiConfig.walletEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          final data = body['data'] as Map<String, dynamic>;

          if (data['balances'] is Map<String, dynamic>) {
            _balances = WalletBalances.fromJson(
              data['balances'] as Map<String, dynamic>,
              demoEnabled: data['demo_enabled'] == true,
            );
          }

          if (data['entries'] is List) {
            _transactions = (data['entries'] as List)
                .map((e) => WalletTransaction.fromJson(e as Map<String, dynamic>))
                .toList();
          }

          if (data['funding_intents'] is List) {
            _fundingIntents = (data['funding_intents'] as List)
                .map((e) => DemoFundingIntent.fromJson(e as Map<String, dynamic>))
                .toList();
          }

          if (data['withdrawals'] is List) {
            _withdrawals = (data['withdrawals'] as List)
                .map((e) => WalletWithdrawal.fromJson(e as Map<String, dynamic>))
                .toList();
          }
        }
      } else {
        _errorMessage = 'Failed to load wallet data (${res.statusCode})';
      }

      // Also load finance records
      await _loadFinanceRecords();
    } catch (e) {
      _errorMessage = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _loadFinanceRecords() async {
    try {
      final res = await ApiService.get(ApiConfig.financeRecordsEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          final data = body['data'] as Map<String, dynamic>;
          if (data['order_payments'] is List) {
            _financeRecords = (data['order_payments'] as List)
                .map((e) => WalletFinanceRecord.fromJson(e as Map<String, dynamic>))
                .toList();
          }
          if (data['refunds'] is List) {
            _refundRecords = (data['refunds'] as List)
                .map((e) => WalletRefundRecord.fromJson(e as Map<String, dynamic>))
                .toList();
          }
        }
      }
    } catch (_) {}
  }

  Future<void> _pickEvidence() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 85,
      );
      if (image != null) {
        setState(() => _fundingEvidenceFile = File(image.path));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error selecting file: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _submitDemoFunding() async {
    final amountText = _fundingAmountCtrl.text.trim();
    final amount = double.tryParse(amountText);
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a valid demo amount greater than 0.'), backgroundColor: Colors.red),
      );
      return;
    }

    final reason = _fundingReasonCtrl.text.trim();
    if (reason.length < 5) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Reason must be at least 5 characters.'), backgroundColor: Colors.red),
      );
      return;
    }

    if (_selectedFundingKind == 'demo_cash_deposit') {
      if (_fundingReceiptCtrl.text.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Demo receipt reference is required for cash deposit.'), backgroundColor: Colors.red),
        );
        return;
      }
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Submit demo funding request?'),
        content: const Text(
          'No funds are added until an independent administrator approves. These are school demo credits only.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Submit request')),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _isSubmittingFunding = true);

    try {
      final fields = <String, String>{
        'kind': _selectedFundingKind,
        'amount': amount.toStringAsFixed(2),
        'reason': reason,
        if (_selectedFundingKind == 'demo_cash_deposit' && _fundingReceiptCtrl.text.trim().isNotEmpty)
          'receipt_reference': _fundingReceiptCtrl.text.trim(),
      };

      final List<http.MultipartFile> files = [];
      if (_fundingEvidenceFile != null) {
        files.add(await http.MultipartFile.fromPath('evidence', _fundingEvidenceFile!.path));
      }

      final res = await ApiService.postMultipart(
        ApiConfig.walletDemoFundingEndpoint,
        fields: fields,
        files: files,
      );

      final body = jsonDecode(res.body);

      if (res.statusCode == 201 && body['success'] == true) {
        if (!mounted) return;
        _fundingAmountCtrl.clear();
        _fundingReasonCtrl.clear();
        _fundingReceiptCtrl.clear();
        setState(() => _fundingEvidenceFile = null);

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Demo funding request recorded.'),
            backgroundColor: Colors.green,
          ),
        );
        await _loadWalletData();
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Failed to submit funding request.'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${e.toString()}'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmittingFunding = false);
    }
  }

  Future<void> _submitWithdrawal() async {
    final amountText = _withdrawAmountCtrl.text.trim();
    final amount = double.tryParse(amountText);
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a valid withdrawal amount.'), backgroundColor: Colors.red),
      );
      return;
    }

    if (amount > _balances.available) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Amount exceeds available balance (${_peso.format(_balances.available)}).'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final dest = _withdrawDestinationCtrl.text.trim();
    if (dest.length < 5) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sandbox destination reference must be at least 5 characters.'), backgroundColor: Colors.red),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Request a withdrawal?'),
        content: const Text(
          'This reserves the amount from available funds. Administrator review and sandbox execution are separate steps. Execution is sandbox-only.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Request withdrawal')),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _isSubmittingWithdrawal = true);

    try {
      final res = await ApiService.post(ApiConfig.walletWithdrawEndpoint, {
        'amount': amount.toStringAsFixed(2),
        'destination': dest,
      });

      final body = jsonDecode(res.body);

      if (res.statusCode == 201 && body['success'] == true) {
        if (!mounted) return;
        _withdrawAmountCtrl.clear();
        _withdrawDestinationCtrl.clear();

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Withdrawal requested.'),
            backgroundColor: Colors.green,
          ),
        );
        await _loadWalletData();
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Failed to request withdrawal.'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${e.toString()}'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmittingWithdrawal = false);
    }
  }

  void _showStatementDialog() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.receipt_long, color: AppTheme.primary),
            SizedBox(width: 8),
            Expanded(
              child: Text(
                'Financial Statement',
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        content: SizedBox(
          width: double.maxFinite,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'EZiCart Financial Statement',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                const SizedBox(height: 4),
                Text(
                  'Generated on ${DateFormat('MMM dd, yyyy h:mm a').format(DateTime.now())}',
                  style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                ),
                const SizedBox(height: 12),
                const Divider(),
                _buildStatementRow('Available balance', _peso.format(_balances.available), isHighlight: true),
                _buildStatementRow('Pending holds', _peso.format(_balances.pending)),
                _buildStatementRow('Reserved promotions', _peso.format(_balances.reserved)),
                _buildStatementRow('Withdrawal holds', _peso.format(_balances.held)),
                _buildStatementRow('Total posted transactions', '${_transactions.length}'),
                const Divider(),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.amber.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.amber.shade200),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.info_outline, size: 18, color: Colors.amber.shade900),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Sandbox wallet values are not live e-money or bank balances.',
                          style: TextStyle(fontSize: 11, color: Colors.amber.shade900),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  Widget _buildStatementRow(String label, String value, {bool isHighlight = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13,
                color: isHighlight ? Colors.black87 : AppTheme.muted,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(
                fontSize: 13,
                fontWeight: isHighlight ? FontWeight.bold : FontWeight.w600,
                color: isHighlight ? AppTheme.primary : Colors.black87,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Finance & Wallet'),
        elevation: 0.5,
        actions: [
          IconButton(
            icon: const Icon(Icons.receipt_long),
            tooltip: 'View Statement',
            onPressed: _showStatementDialog,
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
            onPressed: _loadWalletData,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: Colors.red),
                        const SizedBox(height: 12),
                        Text(_errorMessage!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.red)),
                        const SizedBox(height: 16),
                        ElevatedButton(onPressed: _loadWalletData, child: const Text('Try Again')),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadWalletData,
                  child: NestedScrollView(
                    headerSliverBuilder: (context, innerBoxIsScrolled) {
                      return [
                        SliverToBoxAdapter(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _buildBalanceHeader(),
                              if (_balances.isFrozen) _buildFrozenAlert(),
                              _buildSandboxDisclaimer(),
                            ],
                          ),
                        ),
                        SliverPersistentHeader(
                          pinned: true,
                          delegate: _SliverAppBarDelegate(
                            TabBar(
                              controller: _tabController,
                              isScrollable: true,
                              labelColor: AppTheme.primary,
                              unselectedLabelColor: AppTheme.muted,
                              indicatorColor: AppTheme.primary,
                              indicatorWeight: 3,
                              tabAlignment: TabAlignment.start,
                              tabs: [
                                Tab(
                                  child: Row(
                                    children: [
                                      const Icon(Icons.history, size: 16),
                                      const SizedBox(width: 6),
                                      Text('Transactions (${_transactions.length})'),
                                    ],
                                  ),
                                ),
                                Tab(
                                  child: Row(
                                    children: [
                                      const Icon(Icons.add_card, size: 16),
                                      const SizedBox(width: 6),
                                      Text('Add demo funds (${_fundingIntents.length})'),
                                    ],
                                  ),
                                ),
                                Tab(
                                  child: Row(
                                    children: [
                                      const Icon(Icons.account_balance, size: 16),
                                      const SizedBox(width: 6),
                                      Text('Withdrawals (${_withdrawals.length})'),
                                    ],
                                  ),
                                ),
                                Tab(
                                  child: Row(
                                    children: [
                                      const Icon(Icons.analytics_outlined, size: 16),
                                      const SizedBox(width: 6),
                                      Text('Records (${_financeRecords.length})'),
                                    ],
                                  ),
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
                        _buildTransactionsTab(),
                        _buildDemoFundingTab(),
                        _buildWithdrawalsTab(),
                        _buildFinanceRecordsTab(),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _buildBalanceHeader() {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppTheme.primary,
            AppTheme.primary.withAlpha(210),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primary.withAlpha(60),
            blurRadius: 12,
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
              const Expanded(
                child: Row(
                  children: [
                    Icon(Icons.account_balance_wallet, color: Colors.white, size: 20),
                    SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        'Available Demo Balance',
                        style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha(50),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Text(
                  'Spendable',
                  style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              _peso.format(_balances.available),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 32,
                fontWeight: FontWeight.bold,
                letterSpacing: -0.5,
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Divider(color: Colors.white24, height: 1),
          const SizedBox(height: 12),
          // Metric Sub-grid
          Row(
            children: [
              Expanded(
                child: _buildMetricItem(
                  'Pending PHP',
                  _peso.format(_balances.pending),
                  Icons.lock_outline,
                ),
              ),
              Container(width: 1, height: 32, color: Colors.white24),
              Expanded(
                child: _buildMetricItem(
                  'Reserved Promos',
                  _peso.format(_balances.reserved),
                  Icons.local_offer_outlined,
                ),
              ),
              Container(width: 1, height: 32, color: Colors.white24),
              Expanded(
                child: _buildMetricItem(
                  'Withdraw Holds',
                  _peso.format(_balances.held),
                  Icons.receipt_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMetricItem(String label, String value, IconData icon) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 12, color: Colors.white70),
              const SizedBox(width: 3),
              Flexible(
                child: Text(
                  label,
                  style: const TextStyle(color: Colors.white70, fontSize: 10),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 3),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              value,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
              maxLines: 1,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFrozenAlert() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.red.shade50,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.red.shade200),
      ),
      child: Row(
        children: [
          Icon(Icons.warning, color: Colors.red.shade700, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'This account is under financial investigation. Spending is frozen until authorized recovery is resolved.',
              style: TextStyle(color: Colors.red.shade900, fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSandboxDisclaimer() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.blue.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.blue.shade100),
      ),
      child: Row(
        children: [
          Icon(Icons.info_outline, size: 16, color: Colors.blue.shade700),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Sandbox accounting credits. This is not a live e-money or bank account; no real cash-in or cash-out is provided.',
              style: TextStyle(fontSize: 11, color: Colors.blue.shade900),
            ),
          ),
        ],
      ),
    );
  }

  // ================= TAB 1: TRANSACTIONS =================
  Widget _buildTransactionsTab() {
    if (_transactions.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.history_toggle_off, size: 64, color: Colors.grey.shade400),
              const SizedBox(height: 12),
              const Text(
                'No posted transactions',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 6),
              const Text(
                'Wallet payments, demo funding credits, and holds will appear here.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppTheme.muted, fontSize: 13),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      itemCount: _transactions.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final tx = _transactions[index];
        final isCredit = tx.isCredit;

        DateTime? parsedDate;
        if (tx.createdAt != null) {
          parsedDate = DateTime.tryParse(tx.createdAt!);
        }

        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: isCredit ? Colors.green.shade50 : Colors.red.shade50,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  isCredit ? Icons.arrow_downward : Icons.arrow_upward,
                  color: isCredit ? Colors.green.shade700 : Colors.red.shade700,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tx.typeLabel,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      tx.reference,
                      style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (parsedDate != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        DateFormat('MMM dd, yyyy h:mm a').format(parsedDate),
                        style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    '${isCredit ? '+' : ''}${_peso.format(tx.delta)}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                      color: isCredit ? Colors.green.shade700 : Colors.red.shade700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Balance: ${_peso.format(tx.balanceAfter)}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  // ================= TAB 2: ADD DEMO FUNDS =================
  Widget _buildDemoFundingTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Form Card
          Card(
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
                  const Text(
                    'Request Demo Funds',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Choose demo credits or record a demo cash deposit for testing. An independent administrator reviews them.',
                    style: TextStyle(fontSize: 12, color: AppTheme.muted),
                  ),
                  const SizedBox(height: 16),

                  // Kind selector
                  const Text('Request Type', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: ChoiceChip(
                          label: const Center(child: Text('Demo Credits')),
                          selected: _selectedFundingKind == 'demo_credit',
                          onSelected: (val) {
                            if (val) setState(() => _selectedFundingKind = 'demo_credit');
                          },
                          selectedColor: AppTheme.primary.withAlpha(30),
                          labelStyle: TextStyle(
                            color: _selectedFundingKind == 'demo_credit' ? AppTheme.primary : AppTheme.muted,
                            fontWeight: _selectedFundingKind == 'demo_credit' ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: ChoiceChip(
                          label: const Center(child: Text('Cash Deposit')),
                          selected: _selectedFundingKind == 'demo_cash_deposit',
                          onSelected: (val) {
                            if (val) setState(() => _selectedFundingKind = 'demo_cash_deposit');
                          },
                          selectedColor: AppTheme.primary.withAlpha(30),
                          labelStyle: TextStyle(
                            color: _selectedFundingKind == 'demo_cash_deposit' ? AppTheme.primary : AppTheme.muted,
                            fontWeight: _selectedFundingKind == 'demo_cash_deposit' ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  // Amount
                  const Text('Demo Amount (PHP)', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _fundingAmountCtrl,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      prefixText: 'PHP ',
                      hintText: '0.00',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                    ),
                  ),
                  const SizedBox(height: 8),
                  // Preset chips
                  Wrap(
                    spacing: 6,
                    children: [500, 1000, 2500, 5000].map((preset) {
                      return ActionChip(
                        label: Text('+₱$preset', style: const TextStyle(fontSize: 11)),
                        onPressed: () {
                          _fundingAmountCtrl.text = preset.toStringAsFixed(2);
                        },
                        padding: EdgeInsets.zero,
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 14),

                  // Reason
                  const Text('Request Reason', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _fundingReasonCtrl,
                    maxLines: 2,
                    decoration: InputDecoration(
                      hintText: 'e.g. Student demo balance for testing checkout',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // If demo cash deposit: receipt & evidence
                  if (_selectedFundingKind == 'demo_cash_deposit') ...[
                    const Text('Demo Receipt Reference', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                    const SizedBox(height: 6),
                    TextField(
                      controller: _fundingReceiptCtrl,
                      decoration: InputDecoration(
                        hintText: 'e.g. RCPT-2026-001',
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                    ),
                    const SizedBox(height: 14),
                    const Text('Receipt Evidence Photo (Optional)', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                    const SizedBox(height: 6),
                    if (_fundingEvidenceFile != null)
                      Row(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: Image.file(_fundingEvidenceFile!, width: 70, height: 70, fit: BoxFit.cover),
                          ),
                          const SizedBox(width: 12),
                          ElevatedButton.icon(
                            icon: const Icon(Icons.delete, size: 16),
                            label: const Text('Remove'),
                            style: ElevatedButton.styleFrom(backgroundColor: Colors.red.shade50, foregroundColor: Colors.red),
                            onPressed: () => setState(() => _fundingEvidenceFile = null),
                          ),
                        ],
                      )
                    else
                      OutlinedButton.icon(
                        icon: const Icon(Icons.attach_file),
                        label: const Text('Attach Receipt Photo'),
                        onPressed: _pickEvidence,
                      ),
                    const SizedBox(height: 16),
                  ],

                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      onPressed: _isSubmittingFunding || _balances.isFrozen ? null : _submitDemoFunding,
                      child: _isSubmittingFunding
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('Request Demo Funds'),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Requests List
          const Text(
            'Funding Requests & Decisions',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          if (_fundingIntents.isEmpty)
            Container(
              padding: const EdgeInsets.all(24),
              alignment: Alignment.center,
              child: const Text('No demo funding requests submitted yet.', style: TextStyle(color: AppTheme.muted)),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _fundingIntents.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final intent = _fundingIntents[index];
                return _buildFundingIntentCard(intent);
              },
            ),
        ],
      ),
    );
  }

  Widget _buildFundingIntentCard(DemoFundingIntent intent) {
    Color badgeColor;
    Color textColor;
    if (intent.status == 'PAID') {
      badgeColor = Colors.green.shade50;
      textColor = Colors.green.shade800;
    } else if (intent.isExpired) {
      badgeColor = Colors.grey.shade100;
      textColor = Colors.grey.shade700;
    } else if (intent.status == 'REJECTED') {
      badgeColor = Colors.red.shade50;
      textColor = Colors.red.shade800;
    } else {
      badgeColor = Colors.amber.shade50;
      textColor = Colors.amber.shade900;
    }

    DateTime? createdDate;
    if (intent.createdAt != null) createdDate = DateTime.tryParse(intent.createdAt!);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    intent.reference,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: badgeColor,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: textColor.withAlpha(80)),
                  ),
                  child: Text(
                    intent.statusLabel,
                    style: TextStyle(color: textColor, fontWeight: FontWeight.bold, fontSize: 11),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              intent.kindLabel,
              style: const TextStyle(fontSize: 12, color: AppTheme.muted),
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    _peso.format(intent.amount),
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.primary),
                  ),
                ),
                if (createdDate != null)
                  Text(
                    DateFormat('MMM dd, yyyy').format(createdDate),
                    style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                  ),
              ],
            ),
            if (intent.reason.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Reason: ${intent.reason}', style: const TextStyle(fontSize: 12)),
            ],
            if (intent.receiptReference != null) ...[
              const SizedBox(height: 4),
              Text('Receipt Ref: ${intent.receiptReference}', style: const TextStyle(fontSize: 11, color: AppTheme.muted)),
            ],
            if (intent.reviewReason != null && intent.reviewReason!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.grey.shade50,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.admin_panel_settings_outlined, size: 14, color: AppTheme.muted),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Review note: ${intent.reviewReason}',
                        style: const TextStyle(fontSize: 11, color: Colors.black87),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  // ================= TAB 3: WITHDRAWALS =================
  Widget _buildWithdrawalsTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Form Card
          Card(
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
                  const Text(
                    'Request Withdrawal',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Requests reserve available funds immediately. Execution is a sandbox ledger operation, not a bank transfer.',
                    style: TextStyle(fontSize: 12, color: AppTheme.muted),
                  ),
                  const SizedBox(height: 16),

                  // Amount
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Amount (PHP)', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                      InkWell(
                        onTap: () {
                          _withdrawAmountCtrl.text = _balances.available.toStringAsFixed(2);
                        },
                        child: Text(
                          'Max (${_peso.format(_balances.available)})',
                          style: const TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _withdrawAmountCtrl,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      prefixText: 'PHP ',
                      hintText: '0.00',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // Destination
                  const Text('Sandbox Destination Reference', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _withdrawDestinationCtrl,
                    decoration: InputDecoration(
                      hintText: 'e.g. BDO Test Account 12345',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    ),
                  ),
                  const SizedBox(height: 16),

                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      onPressed: _isSubmittingWithdrawal || _balances.isFrozen ? null : _submitWithdrawal,
                      child: _isSubmittingWithdrawal
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('Request Withdrawal'),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Withdrawals List
          const Text(
            'Withdrawal Requests',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          if (_withdrawals.isEmpty)
            Container(
              padding: const EdgeInsets.all(24),
              alignment: Alignment.center,
              child: const Text('No withdrawal requests recorded.', style: TextStyle(color: AppTheme.muted)),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _withdrawals.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final w = _withdrawals[index];
                return _buildWithdrawalCard(w);
              },
            ),
        ],
      ),
    );
  }

  Widget _buildWithdrawalCard(WalletWithdrawal w) {
    Color badgeColor;
    Color textColor;
    if (w.status == 'SETTLED') {
      badgeColor = Colors.green.shade50;
      textColor = Colors.green.shade800;
    } else if (w.status == 'APPROVED') {
      badgeColor = Colors.blue.shade50;
      textColor = Colors.blue.shade800;
    } else if (w.status == 'REJECTED') {
      badgeColor = Colors.red.shade50;
      textColor = Colors.red.shade800;
    } else {
      badgeColor = Colors.amber.shade50;
      textColor = Colors.amber.shade900;
    }

    DateTime? createdDate;
    if (w.createdAt != null) createdDate = DateTime.tryParse(w.createdAt!);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    w.reference,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: badgeColor,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: textColor.withAlpha(80)),
                  ),
                  child: Text(
                    w.statusLabel,
                    style: TextStyle(color: textColor, fontWeight: FontWeight.bold, fontSize: 11),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              'Destination: ${w.destination}',
              style: const TextStyle(fontSize: 12, color: AppTheme.muted),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    _peso.format(w.amount),
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87),
                  ),
                ),
                if (createdDate != null)
                  Text(
                    DateFormat('MMM dd, yyyy').format(createdDate),
                    style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                  ),
              ],
            ),
            if (w.reviewReason != null && w.reviewReason!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                'Review note: ${w.reviewReason}',
                style: const TextStyle(fontSize: 11, color: AppTheme.muted),
              ),
            ],
          ],
        ),
      ),
    );
  }

  // ================= TAB 4: FINANCIAL RECORDS =================
  Widget _buildFinanceRecordsTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Order Payments & Cash Custody',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 4),
          const Text(
            'Order payments, prepaid wallet transactions, and remittance status matching the web.',
            style: TextStyle(fontSize: 12, color: AppTheme.muted),
          ),
          const SizedBox(height: 12),
          if (_financeRecords.isEmpty)
            Container(
              padding: const EdgeInsets.all(24),
              alignment: Alignment.center,
              child: const Text('No order financial records found.', style: TextStyle(color: AppTheme.muted)),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _financeRecords.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final record = _financeRecords[index];
                return Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: const BorderSide(color: AppTheme.line),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                record.orderNumber ?? 'Order #${record.orderId}',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: record.paymentMethod == 'WALLET' ? Colors.purple.shade50 : Colors.green.shade50,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                record.paymentMethod,
                                style: TextStyle(
                                  color: record.paymentMethod == 'WALLET' ? Colors.purple.shade800 : Colors.green.shade800,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 11,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text('Seller: ${record.sellerName}', style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              _peso.format(record.amount),
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                            ),
                            const SizedBox(width: 8),
                            Flexible(
                              child: Text(
                                record.statusNote,
                                textAlign: TextAlign.end,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: record.isAwaitingWalletPayment ? Colors.amber.shade900 : Colors.grey.shade700,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),

          if (_refundRecords.isNotEmpty) ...[
            const SizedBox(height: 24),
            const Text(
              'Refunds',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _refundRecords.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final refund = _refundRecords[index];
                return Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: const BorderSide(color: AppTheme.line),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                refund.reference,
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(_peso.format(refund.amount), style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green)),
                          ],
                        ),
                        if (refund.reason != null) ...[
                          const SizedBox(height: 4),
                          Text('Reason: ${refund.reason}', style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
                        ],
                      ],
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
}

class _SliverAppBarDelegate extends SliverPersistentHeaderDelegate {
  _SliverAppBarDelegate(this._tabBar);

  final TabBar _tabBar;

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
