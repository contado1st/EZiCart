import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/wallet_model.dart';
import '../../services/api_service.dart';

class WalletPage extends StatefulWidget {
  const WalletPage({super.key});

  @override
  State<WalletPage> createState() => _WalletPageState();
}

class _WalletPageState extends State<WalletPage> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = true;
  String? _errorMessage;

  WalletBalances _balances = WalletBalances();
  List<WalletTransaction> _transactions = [];
  List<WalletFundingIntentModel> _fundingIntents = [];
  List<WalletWithdrawalModel> _withdrawals = [];

  List<RemittanceBatchModel> _remittanceBatches = [];
  List<PendingCollectionModel> _pendingCollections = [];
  List<Map<String, dynamic>> _hubs = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadAllData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadAllData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      await Future.wait([
        _loadWalletData(),
        _loadRemittanceData(),
      ]);
    } catch (e) {
      if (mounted) {
        setState(() => _errorMessage = e.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _loadWalletData() async {
    final res = await ApiService.get(ApiConfig.walletEndpoint);
    if (res.statusCode == 200) {
      final data = jsonDecode(res.body);
      if (data['balances'] is Map) {
        _balances = WalletBalances.fromJson(
          data['balances'] as Map<String, dynamic>,
          demoEnabled: data['demo_enabled'] == true,
        );
      }

      if (data['transactions'] is Map && data['transactions']['data'] is List) {
        _transactions = (data['transactions']['data'] as List)
            .map((item) => WalletTransaction.fromJson(item as Map<String, dynamic>))
            .toList();
      }

      if (data['funding_intents'] is Map && data['funding_intents']['data'] is List) {
        _fundingIntents = (data['funding_intents']['data'] as List)
            .map((item) => WalletFundingIntentModel.fromJson(item as Map<String, dynamic>))
            .toList();
      }

      if (data['withdrawals'] is Map && data['withdrawals']['data'] is List) {
        _withdrawals = (data['withdrawals']['data'] as List)
            .map((item) => WalletWithdrawalModel.fromJson(item as Map<String, dynamic>))
            .toList();
      }
    } else {
      throw Exception('Failed to load wallet data (${res.statusCode})');
    }
  }

  Future<void> _loadRemittanceData() async {
    final res = await ApiService.get(ApiConfig.courierRemittanceBatchesEndpoint);
    if (res.statusCode == 200) {
      final data = jsonDecode(res.body);
      if (data['batches'] is Map && data['batches']['data'] is List) {
        _remittanceBatches = (data['batches']['data'] as List)
            .map((item) => RemittanceBatchModel.fromJson(item as Map<String, dynamic>))
            .toList();
      }
      if (data['pending_collections'] is List) {
        _pendingCollections = (data['pending_collections'] as List)
            .map((item) => PendingCollectionModel.fromJson(item as Map<String, dynamic>))
            .toList();
      }
      if (data['hubs'] is List) {
        _hubs = List<Map<String, dynamic>>.from(data['hubs'] as List);
      }
    }
  }

  // Demo Top-up Dialog
  void _showTopUpDialog() {
    final amountCtrl = TextEditingController(text: '500.00');
    final reasonCtrl = TextEditingController(text: 'Courier float top-up for delivery operations');
    String kind = 'demo_credit';
    bool submitting = false;

    showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.add_card, color: AppTheme.primary),
              SizedBox(width: 8),
              Text('Top Up Wallet (Demo)', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Credit demo funds directly to your courier wallet for testing cash flows and operational balances.',
                  style: TextStyle(fontSize: 12, color: Colors.grey),
                ),
                const SizedBox(height: 16),
                const Text('Amount (₱)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(height: 6),
                TextField(
                  controller: amountCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.payments_outlined, size: 20),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                ),
                const SizedBox(height: 12),
                const Text('Funding Kind', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(height: 6),
                DropdownButtonFormField<String>(
                  initialValue: kind,
                  decoration: InputDecoration(
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'demo_credit', child: Text('Demo Credit (Instant)')),
                    DropdownMenuItem(value: 'demo_cash_deposit', child: Text('Demo Cash Float')),
                  ],
                  onChanged: (val) {
                    if (val != null) setDialogState(() => kind = val);
                  },
                ),
                const SizedBox(height: 12),
                const Text('Reason / Note', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(height: 6),
                TextField(
                  controller: reasonCtrl,
                  maxLines: 2,
                  decoration: InputDecoration(
                    hintText: 'Purpose of top-up',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: submitting ? null : () => Navigator.pop(dialogCtx),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: submitting
                  ? null
                  : () async {
                      final amountVal = double.tryParse(amountCtrl.text.trim()) ?? 0;
                      if (amountVal <= 0) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Please enter a valid amount.')),
                        );
                        return;
                      }
                      if (reasonCtrl.text.trim().length < 5) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Reason must be at least 5 characters.')),
                        );
                        return;
                      }

                      setDialogState(() => submitting = true);
                      try {
                        final res = await ApiService.post(ApiConfig.walletDemoFundingEndpoint, {
                          'amount': amountVal,
                          'kind': kind,
                          'reason': reasonCtrl.text.trim(),
                        });

                        final body = jsonDecode(res.body);
                        if (res.statusCode == 200 && body['success'] == true) {
                          if (dialogCtx.mounted) Navigator.pop(dialogCtx);
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Demo funds successfully credited!'),
                                backgroundColor: AppTheme.success,
                              ),
                            );
                            _loadAllData();
                          }
                        } else {
                          final msg = body['message'] ?? 'Failed to credit wallet.';
                          if (dialogCtx.mounted) {
                            ScaffoldMessenger.of(dialogCtx).showSnackBar(
                              SnackBar(content: Text(msg), backgroundColor: AppTheme.danger),
                            );
                          }
                        }
                      } catch (e) {
                        if (dialogCtx.mounted) {
                          ScaffoldMessenger.of(dialogCtx).showSnackBar(
                            SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.danger),
                          );
                        }
                      } finally {
                        setDialogState(() => submitting = false);
                      }
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: submitting
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Top Up Now'),
            ),
          ],
        ),
      ),
    );
  }

  // Withdrawal Request Dialog
  void _showWithdrawDialog() {
    final amountCtrl = TextEditingController(text: '100.00');
    final destinationCtrl = TextEditingController(text: 'GCash 0917-000-0000');
    bool submitting = false;

    showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.output, color: AppTheme.primary),
              SizedBox(width: 8),
              Text('Request Withdrawal', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Available: ₱${_balances.available.toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary),
                ),
                const SizedBox(height: 12),
                const Text('Amount (₱)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(height: 6),
                TextField(
                  controller: amountCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.payments_outlined, size: 20),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                ),
                const SizedBox(height: 12),
                const Text('Payout Destination', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(height: 6),
                TextField(
                  controller: destinationCtrl,
                  decoration: InputDecoration(
                    hintText: 'e.g. GCash 0917xxxxxxx / BDO 1234567890',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: submitting ? null : () => Navigator.pop(dialogCtx),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: submitting
                  ? null
                  : () async {
                      final amountVal = double.tryParse(amountCtrl.text.trim()) ?? 0;
                      if (amountVal <= 0 || amountVal > _balances.available) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Invalid amount or exceeds available balance.')),
                        );
                        return;
                      }
                      if (destinationCtrl.text.trim().length < 5) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Please specify a valid destination.')),
                        );
                        return;
                      }

                      setDialogState(() => submitting = true);
                      try {
                        final res = await ApiService.post(ApiConfig.walletWithdrawEndpoint, {
                          'amount': amountVal,
                          'destination': destinationCtrl.text.trim(),
                        });

                        final body = jsonDecode(res.body);
                        if (res.statusCode == 200 && body['success'] == true) {
                          if (dialogCtx.mounted) Navigator.pop(dialogCtx);
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Withdrawal request submitted successfully!'),
                                backgroundColor: AppTheme.success,
                              ),
                            );
                            _loadAllData();
                          }
                        } else {
                          final msg = body['message'] ?? 'Failed to submit withdrawal.';
                          if (dialogCtx.mounted) {
                            ScaffoldMessenger.of(dialogCtx).showSnackBar(
                              SnackBar(content: Text(msg), backgroundColor: AppTheme.danger),
                            );
                          }
                        }
                      } catch (e) {
                        if (dialogCtx.mounted) {
                          ScaffoldMessenger.of(dialogCtx).showSnackBar(
                            SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.danger),
                          );
                        }
                      } finally {
                        setDialogState(() => submitting = false);
                      }
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: submitting
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Submit Request'),
            ),
          ],
        ),
      ),
    );
  }

  // Submit Remittance Batch Modal
  void _showSubmitRemittanceBatchModal() {
    if (_pendingCollections.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No pending COD cash collections available to remit.')),
      );
      return;
    }

    final selectedIds = <int>{..._pendingCollections.map((c) => c.id)};
    int? selectedHubId = _hubs.isNotEmpty ? _hubs.first['id'] as int? : null;
    final reasonCtrl = TextEditingController(text: 'Physical COD cash handover to Sorting Hub');
    String? pickedEvidencePath;
    bool submitting = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (bottomSheetCtx) => StatefulBuilder(
        builder: (ctx, setModalState) {
          final totalSelectedPesos = _pendingCollections
              .where((c) => selectedIds.contains(c.id))
              .fold<double>(0.0, (sum, c) => sum + c.collectedPesos);

          return Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(ctx).viewInsets.bottom,
              top: 16,
              left: 16,
              right: 16,
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Submit Remittance Batch',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(bottomSheetCtx),
                      ),
                    ],
                  ),
                  const Text(
                    'Select collected COD orders to physically remit to your assigned sorting hub.',
                    style: TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                  const SizedBox(height: 12),

                  // Hub Selection
                  const Text('Destination Sorting Hub', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  const SizedBox(height: 6),
                  if (_hubs.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: Colors.amber.shade50,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Text('No sorting hubs registered.', style: TextStyle(color: Colors.amber)),
                    )
                  else
                    DropdownButtonFormField<int>(
                      initialValue: selectedHubId,
                      decoration: InputDecoration(
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      ),
                      items: _hubs.map((hub) {
                        return DropdownMenuItem<int>(
                          value: hub['id'] as int,
                          child: Text(
                            '${hub['name']} (${hub['hub_code'] ?? 'HUB'})',
                            overflow: TextOverflow.ellipsis,
                          ),
                        );
                      }).toList(),
                      onChanged: (val) {
                        if (val != null) setModalState(() => selectedHubId = val);
                      },
                    ),
                  const SizedBox(height: 14),

                  // Pending Collections Multi-select
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Collections (${selectedIds.length}/${_pendingCollections.length})',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      TextButton(
                        onPressed: () {
                          setModalState(() {
                            if (selectedIds.length == _pendingCollections.length) {
                              selectedIds.clear();
                            } else {
                              selectedIds.addAll(_pendingCollections.map((c) => c.id));
                            }
                          });
                        },
                        child: Text(
                          selectedIds.length == _pendingCollections.length ? 'Deselect All' : 'Select All',
                          style: const TextStyle(fontSize: 12),
                        ),
                      ),
                    ],
                  ),
                  Container(
                    constraints: const BoxConstraints(maxHeight: 160),
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade300),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: _pendingCollections.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (cCtx, i) {
                        final item = _pendingCollections[i];
                        final isSelected = selectedIds.contains(item.id);
                        return CheckboxListTile(
                          value: isSelected,
                          dense: true,
                          title: Text(item.orderNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          subtitle: Text('Collected: ₱${item.collectedPesos.toStringAsFixed(2)}'),
                          onChanged: (checked) {
                            setModalState(() {
                              if (checked == true) {
                                selectedIds.add(item.id);
                              } else {
                                selectedIds.remove(item.id);
                              }
                            });
                          },
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Remittance Amount Summary
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppTheme.primary.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Expanded(
                          child: Text(
                            'Total Remittance Amount:',
                            style: TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '₱${totalSelectedPesos.toStringAsFixed(2)}',
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.primary),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Reason Note
                  const Text('Handover Notes / Memo', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  const SizedBox(height: 6),
                  TextField(
                    controller: reasonCtrl,
                    maxLines: 2,
                    decoration: InputDecoration(
                      hintText: 'e.g. End of day remittance to hub cashier',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Evidence / Receipt Photo
                  const Text('Proof of Handover / Receipt Photo', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      ElevatedButton.icon(
                        onPressed: () async {
                          final picker = ImagePicker();
                          final file = await picker.pickImage(source: ImageSource.camera, maxWidth: 1200, imageQuality: 85);
                          if (file != null) {
                            setModalState(() => pickedEvidencePath = file.path);
                          }
                        },
                        icon: const Icon(Icons.camera_alt, size: 16),
                        label: const Text('Camera'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.grey.shade200,
                          foregroundColor: Colors.black87,
                          elevation: 0,
                        ),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton.icon(
                        onPressed: () async {
                          final picker = ImagePicker();
                          final file = await picker.pickImage(source: ImageSource.gallery, maxWidth: 1200, imageQuality: 85);
                          if (file != null) {
                            setModalState(() => pickedEvidencePath = file.path);
                          }
                        },
                        icon: const Icon(Icons.photo_library, size: 16),
                        label: const Text('Gallery'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.grey.shade200,
                          foregroundColor: Colors.black87,
                          elevation: 0,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          pickedEvidencePath != null ? 'Photo attached' : 'No photo chosen',
                          style: TextStyle(
                            fontSize: 11,
                            color: pickedEvidencePath != null ? AppTheme.success : Colors.grey,
                            fontWeight: pickedEvidencePath != null ? FontWeight.bold : FontWeight.normal,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Submit Button
                  ElevatedButton(
                    onPressed: submitting
                        ? null
                        : () async {
                            if (selectedHubId == null) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Please select a destination sorting hub.')),
                              );
                              return;
                            }
                            if (selectedIds.isEmpty) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Please select at least one collected order.')),
                              );
                              return;
                            }
                            if (pickedEvidencePath == null) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Please attach a photo of the receipt or cash handover.')),
                              );
                              return;
                            }

                            setModalState(() => submitting = true);
                            try {
                              final fields = <String, String>{
                                'hub_id': selectedHubId.toString(),
                                'amount': totalSelectedPesos.toStringAsFixed(2),
                                'reason': reasonCtrl.text.trim(),
                              };
                              int idx = 0;
                              for (final id in selectedIds) {
                                fields['finance_ids[$idx]'] = id.toString();
                                idx++;
                              }

                              final res = await ApiService.multipartPost(
                                url: ApiConfig.courierSubmitRemittanceBatchEndpoint,
                                fields: fields,
                                fileField: 'evidence',
                                filePath: pickedEvidencePath!,
                              );

                              final body = jsonDecode(res.body);
                              if (res.statusCode == 200 && body['success'] == true) {
                                if (bottomSheetCtx.mounted) Navigator.pop(bottomSheetCtx);
                                if (mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(
                                      content: Text('Remittance batch successfully submitted!'),
                                      backgroundColor: AppTheme.success,
                                    ),
                                  );
                                  _loadAllData();
                                }
                              } else {
                                final msg = body['message'] ?? 'Failed to submit remittance batch.';
                                if (bottomSheetCtx.mounted) {
                                  ScaffoldMessenger.of(bottomSheetCtx).showSnackBar(
                                    SnackBar(content: Text(msg), backgroundColor: AppTheme.danger),
                                  );
                                }
                              }
                            } catch (e) {
                              if (bottomSheetCtx.mounted) {
                                ScaffoldMessenger.of(bottomSheetCtx).showSnackBar(
                                  SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.danger),
                                );
                              }
                            } finally {
                              setModalState(() => submitting = false);
                            }
                          },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: submitting
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Confirm & Submit Batch', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Finance & Wallet'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh Wallet',
            onPressed: _loadAllData,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: AppTheme.danger),
                        const SizedBox(height: 12),
                        Text(_errorMessage!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadAllData,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : Column(
                  children: [
                    // Frozen Banner if applicable
                    if (_balances.isFrozen)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        color: Colors.red.shade700,
                        child: const Row(
                          children: [
                            Icon(Icons.lock, color: Colors.white, size: 18),
                            SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Wallet is currently frozen. Outgoing withdrawals are temporarily restricted.',
                                style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                      ),

                    // Top Wallet Card
                    _buildBalanceHeader(),

                    // Tab Navigation
                    TabBar(
                      controller: _tabController,
                      labelColor: AppTheme.primary,
                      unselectedLabelColor: Colors.grey,
                      indicatorColor: AppTheme.primary,
                      tabs: [
                        const Tab(text: 'Transactions'),
                        Tab(
                          text: _pendingCollections.isNotEmpty
                              ? 'Remittances (${_pendingCollections.length})'
                              : 'Remittances',
                        ),
                        const Tab(text: 'Payouts & Top-ups'),
                      ],
                    ),

                    // Tab Content
                    Expanded(
                      child: TabBarView(
                        controller: _tabController,
                        children: [
                          _buildTransactionsTab(),
                          _buildRemittancesTab(),
                          _buildPayoutsTab(),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _buildBalanceHeader() {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFD63363), Color(0xFFBE2854)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primary.withValues(alpha: 0.25),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Available Wallet Balance',
                style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.white24,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  'SYNCED WITH WEB',
                  style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            '₱${_balances.available.toStringAsFixed(2)}',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 14),

          // Secondary balances (Pending, Held, Reserved)
          Row(
            children: [
              Expanded(
                child: _buildSubBalance('Pending', '₱${_balances.pending.toStringAsFixed(2)}'),
              ),
              Expanded(
                child: _buildSubBalance('Held Escrow', '₱${_balances.held.toStringAsFixed(2)}'),
              ),
              Expanded(
                child: _buildSubBalance('Reserved', '₱${_balances.reserved.toStringAsFixed(2)}'),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Action Buttons
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _showTopUpDialog,
                  icon: const Icon(Icons.add, size: 16),
                  label: const Text('Top Up (Demo)', style: TextStyle(fontSize: 12)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: AppTheme.primary,
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _balances.available > 0 ? _showWithdrawDialog : null,
                  icon: const Icon(Icons.arrow_upward, size: 16),
                  label: const Text('Withdraw', style: TextStyle(fontSize: 12)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white24,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSubBalance(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Colors.white60, fontSize: 11)),
        const SizedBox(height: 2),
        Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
      ],
    );
  }

  // Transactions Tab
  Widget _buildTransactionsTab() {
    if (_transactions.isEmpty) {
      return const Center(
        child: Text('No transaction history found.', style: TextStyle(color: Colors.grey)),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: _transactions.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (ctx, i) {
        final tx = _transactions[i];
        final isPositive = tx.delta >= 0;

        return Card(
          elevation: 0.5,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: BorderSide(color: Colors.grey.shade200),
          ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: isPositive
                      ? AppTheme.success.withValues(alpha: 0.12)
                      : AppTheme.danger.withValues(alpha: 0.12),
                  child: Icon(
                    isPositive ? Icons.arrow_downward : Icons.arrow_upward,
                    color: isPositive ? AppTheme.success : AppTheme.danger,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        tx.typeLabel.toUpperCase(),
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        tx.reference,
                        style: TextStyle(color: Colors.grey.shade600, fontSize: 11),
                      ),
                      if (tx.createdAt != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          tx.createdAt!,
                          style: TextStyle(color: Colors.grey.shade500, fontSize: 10),
                        ),
                      ],
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '${isPositive ? '+' : ''}₱${tx.delta.toStringAsFixed(2)}',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 15,
                        color: isPositive ? AppTheme.success : AppTheme.danger,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Bal: ₱${tx.balanceAfter.toStringAsFixed(2)}',
                      style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // Remittances Tab
  Widget _buildRemittancesTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Pending Collections Card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.amber.shade50,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.amber.shade300),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.handshake, color: Colors.amber, size: 20),
                      SizedBox(width: 8),
                      Text('COD Cash In-Hand', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    ],
                  ),
                  ElevatedButton(
                    onPressed: _showSubmitRemittanceBatchModal,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.amber.shade800,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    child: const Text('Remit to Hub', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'You have ${_pendingCollections.length} delivered COD order(s) awaiting cash handover to the sorting center.',
                style: TextStyle(fontSize: 12, color: Colors.brown.shade800),
              ),
              if (_pendingCollections.isNotEmpty) ...[
                const SizedBox(height: 12),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: _pendingCollections.take(5).map((c) {
                    return Chip(
                      label: Text('${c.orderNumber}: ₱${c.collectedPesos.toStringAsFixed(2)}', style: const TextStyle(fontSize: 11)),
                      backgroundColor: Colors.white,
                      padding: EdgeInsets.zero,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    );
                  }).toList(),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 20),

        const Text('Remittance Batches History', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        const SizedBox(height: 8),

        if (_remittanceBatches.isEmpty)
          const Padding(
            padding: EdgeInsets.all(24),
            child: Center(
              child: Text('No remittance batches submitted yet.', style: TextStyle(color: Colors.grey)),
            ),
          )
        else
          ..._remittanceBatches.map((batch) {
            Color statusColor = Colors.orange;
            if (batch.status == 'RECEIVED' || batch.status == 'CONFIRMED') {
              statusColor = AppTheme.success;
            } else if (batch.status == 'DISPUTED') {
              statusColor = AppTheme.danger;
            }

            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(color: Colors.grey.shade200),
              ),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(batch.batchReference, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: statusColor.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            batch.status,
                            style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 11),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text('Sorting Hub: ${batch.hubName}', style: TextStyle(color: Colors.grey.shade700, fontSize: 12)),
                    const SizedBox(height: 4),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Submitted: ₱${batch.submittedPesos.toStringAsFixed(2)}',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        Text('Orders: ${batch.itemsCount}', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      ],
                    ),
                    if (batch.receivedPesos != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        'Verified by Hub: ₱${batch.receivedPesos!.toStringAsFixed(2)}',
                        style: const TextStyle(color: AppTheme.success, fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                    ],
                    if (batch.notes != null && batch.notes!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('Note: ${batch.notes!}', style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
                    ],
                  ],
                ),
              ),
            );
          }),
      ],
    );
  }

  // Payouts & Top-ups Tab
  Widget _buildPayoutsTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text('Withdrawal Requests', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        const SizedBox(height: 8),
        if (_withdrawals.isEmpty)
          const Text('No withdrawal requests.', style: TextStyle(color: Colors.grey, fontSize: 12))
        else
          ..._withdrawals.map((w) {
            Color statusColor = Colors.orange;
            if (w.status == 'COMPLETED' || w.status == 'PROCESSED') statusColor = AppTheme.success;
            if (w.status == 'REJECTED') statusColor = AppTheme.danger;

            return Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                dense: true,
                title: Text('₱${w.amount.toStringAsFixed(2)} to ${w.destination}', style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text('Ref: ${w.reference} • ${w.createdAt ?? ''}', style: const TextStyle(fontSize: 11)),
                trailing: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(w.status, style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 11)),
                ),
              ),
            );
          }),
        const SizedBox(height: 24),

        const Text('Funding Top-up Requests', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        const SizedBox(height: 8),
        if (_fundingIntents.isEmpty)
          const Text('No funding requests.', style: TextStyle(color: Colors.grey, fontSize: 12))
        else
          ..._fundingIntents.map((f) {
            Color statusColor = Colors.orange;
            if (f.status == 'CREDITED' || f.status == 'APPROVED') statusColor = AppTheme.success;
            if (f.status == 'REJECTED') statusColor = AppTheme.danger;

            return Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                dense: true,
                title: Text('₱${f.amount.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text('Ref: ${f.reference} • ${f.createdAt ?? ''}', style: const TextStyle(fontSize: 11)),
                trailing: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(f.status, style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 11)),
                ),
              ),
            );
          }),
      ],
    );
  }
}
