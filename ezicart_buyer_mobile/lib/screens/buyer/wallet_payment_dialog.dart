import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/order_model.dart';
import '../../models/wallet_model.dart';
import '../../services/api_service.dart';
import 'wallet_page.dart';

class WalletPaymentDialog extends StatefulWidget {
  final OrderModel order;
  final VoidCallback onPaymentSuccess;

  const WalletPaymentDialog({
    super.key,
    required this.order,
    required this.onPaymentSuccess,
  });

  static Future<void> show(
    BuildContext context, {
    required OrderModel order,
    required VoidCallback onPaymentSuccess,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => WalletPaymentDialog(
        order: order,
        onPaymentSuccess: onPaymentSuccess,
      ),
    );
  }

  @override
  State<WalletPaymentDialog> createState() => _WalletPaymentDialogState();
}

class _WalletPaymentDialogState extends State<WalletPaymentDialog> {
  final NumberFormat _peso = NumberFormat.currency(locale: 'en_PH', symbol: 'PHP ');
  final _passwordCtrl = TextEditingController();

  bool _isLoadingWallet = true;
  bool _isSubmitting = false;
  bool _isCancelling = false;
  bool _obscurePassword = true;
  String? _errorMessage;

  WalletBalances _balances = WalletBalances();

  @override
  void initState() {
    super.initState();
    _fetchWalletBalance();
  }

  @override
  void dispose() {
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _fetchWalletBalance() async {
    setState(() => _isLoadingWallet = true);
    try {
      final res = await ApiService.get(ApiConfig.walletEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data']?['balances'] is Map<String, dynamic>) {
          _balances = WalletBalances.fromJson(
            body['data']['balances'] as Map<String, dynamic>,
            demoEnabled: body['data']['demo_enabled'] == true,
          );
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingWallet = false);
  }

  Future<void> _processPayment() async {
    final password = _passwordCtrl.text.trim();
    if (password.isEmpty) {
      setState(() => _errorMessage = 'Please enter your account password to confirm payment.');
      return;
    }

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiService.post(
        ApiConfig.orderWalletPayEndpoint(widget.order.id),
        {'password': password},
      );

      final body = jsonDecode(res.body);
      if (res.statusCode == 200 && body['success'] == true) {
        if (!mounted) return;
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Demo wallet payment recorded!'),
            backgroundColor: Colors.green,
          ),
        );
        widget.onPaymentSuccess();
      } else {
        if (!mounted) return;
        setState(() {
          _errorMessage = body['message'] ?? 'Failed to process wallet payment.';
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _errorMessage = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _cancelReservation() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel unpaid reservation?'),
        content: const Text(
          'Reserved stock and eligible voucher usage will be released. No wallet funds have been charged.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep Reservation')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Cancel Reservation'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() {
      _isCancelling = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiService.post(
        ApiConfig.orderWalletCancelEndpoint(widget.order.id),
        {},
      );

      final body = jsonDecode(res.body);
      if (res.statusCode == 200 && body['success'] == true) {
        if (!mounted) return;
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(body['message'] ?? 'Reservation cancelled.'),
            backgroundColor: Colors.orange,
          ),
        );
        widget.onPaymentSuccess();
      } else {
        if (!mounted) return;
        setState(() => _errorMessage = body['message'] ?? 'Unable to cancel reservation.');
      }
    } catch (e) {
      if (mounted) {
        setState(() => _errorMessage = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _isCancelling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final total = order.totalAmount;
    final available = _balances.available;
    final difference = (total - available).clamp(0.0, double.infinity);
    final hasEnoughBalance = available >= total && !_balances.isFrozen;

    return Container(
      padding: EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Expanded(
                child: Text(
                  'Pay with EZiCart Wallet',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              IconButton(
                icon: const Icon(Icons.close),
                onPressed: () => Navigator.pop(context),
              ),
            ],
          ),
          Text(
            '${order.orderNumber ?? 'Order #${order.id}'} · School demo funds only',
            style: const TextStyle(fontSize: 12, color: AppTheme.muted),
          ),
          const SizedBox(height: 16),

          // Order & Balance summary
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppTheme.line),
            ),
            child: Column(
              children: [
                _buildRow('Order total', _peso.format(total), isBold: true),
                const SizedBox(height: 6),
                _buildRow(
                  'Available demo balance',
                  _isLoadingWallet ? 'Loading...' : _peso.format(available),
                  color: hasEnoughBalance ? Colors.green.shade800 : Colors.red.shade800,
                ),
                if (difference > 0 && !_isLoadingWallet) ...[
                  const SizedBox(height: 6),
                  _buildRow('Additional funds needed', _peso.format(difference), color: Colors.red),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),

          if (_balances.isFrozen)
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                'Your wallet is frozen. Spending is on hold.',
                style: TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.bold),
              ),
            ),

          if (!hasEnoughBalance && !_isLoadingWallet && !_balances.isFrozen) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.amber.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.amber.shade200),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.info_outline, size: 16, color: Colors.amber.shade900),
                      const SizedBox(width: 8),
                      Text(
                        'Insufficient available demo balance',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.amber.shade900),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.add, size: 16),
                      label: const Text('Request Demo Funds'),
                      onPressed: () {
                        Navigator.pop(context);
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const WalletPage(initialTabIndex: 1),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],

          if (hasEnoughBalance) ...[
            const Text(
              'Confirm Account Password',
              style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            ),
            const SizedBox(height: 6),
            TextField(
              controller: _passwordCtrl,
              obscureText: _obscurePassword,
              decoration: InputDecoration(
                hintText: 'Enter your password',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                suffixIcon: IconButton(
                  icon: Icon(_obscurePassword ? Icons.visibility : Icons.visibility_off),
                  onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                ),
              ),
            ),
            const SizedBox(height: 14),
          ],

          if (_errorMessage != null) ...[
            Text(_errorMessage!, style: const TextStyle(color: Colors.red, fontSize: 12)),
            const SizedBox(height: 10),
          ],

          ElevatedButton(
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 14),
              backgroundColor: AppTheme.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: (!hasEnoughBalance || _isSubmitting || _isLoadingWallet) ? null : _processPayment,
            child: _isSubmitting
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text('Pay ${_peso.format(total)}'),
          ),
          const SizedBox(height: 8),

          OutlinedButton(
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 12),
              foregroundColor: Colors.red.shade700,
              side: BorderSide(color: Colors.red.shade200),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: _isCancelling || _isSubmitting ? null : _cancelReservation,
            child: _isCancelling
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.red, strokeWidth: 2))
                : const Text('Cancel Unpaid Reservation'),
          ),
        ],
      ),
    );
  }

  Widget _buildRow(String label, String value, {bool isBold = false, Color? color}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(fontSize: 13, color: AppTheme.muted),
            overflow: TextOverflow.ellipsis,
          ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: TextStyle(
              fontSize: 13,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: color ?? (isBold ? Colors.black87 : Colors.black87),
            ),
          ),
        ),
      ],
    );
  }
}
