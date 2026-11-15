import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/address_model.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import 'addresses_page.dart';
import 'vouchers_page.dart';

class CheckoutPage extends StatefulWidget {
  const CheckoutPage({super.key});

  @override
  State<CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends State<CheckoutPage> {
  List<AddressModel> _savedAddresses = [];
  int? _selectedAddressId;
  bool _isLoadingAddresses = true;
  bool _isSubmitting = false;
  bool _isApplyingVoucher = false;

  final _notesCtrl = TextEditingController();
  final _voucherCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    _voucherCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadAddresses() async {
    try {
      final res = await ApiService.get(ApiConfig.addressesEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((a) => AddressModel.fromJson(a as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _savedAddresses = list;
              _isLoadingAddresses = false;
              if (list.isNotEmpty && _selectedAddressId == null) {
                final def = list.firstWhere((a) => a.isDefault, orElse: () => list.first);
                _selectedAddressId = def.id;
              }
            });
            _refreshQuoteForSelectedAddress();
          }
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingAddresses = false);
  }

  AddressModel? get _currentAddress {
    if (_selectedAddressId == null || _savedAddresses.isEmpty) return null;
    try {
      return _savedAddresses.firstWhere((a) => a.id == _selectedAddressId);
    } catch (_) {
      return _savedAddresses.first;
    }
  }

  Future<void> _refreshQuoteForSelectedAddress() async {
    final addr = _currentAddress;
    final cart = context.read<CartProvider>();
    if (addr != null) {
      final dest = <String, dynamic>{
        'province': addr.province,
        'municipality': addr.municipality,
        'barangay': addr.barangay,
      };
      await cart.fetchAuthoritativeQuote(destination: dest);
    } else {
      await cart.fetchAuthoritativeQuote();
    }
  }

  Future<void> _applyVoucher() async {
    final code = _voucherCtrl.text.trim();
    if (code.isEmpty) return;

    setState(() => _isApplyingVoucher = true);
    final cart = context.read<CartProvider>();
    final addr = _currentAddress;
    final dest = addr != null
        ? <String, dynamic>{'province': addr.province, 'municipality': addr.municipality, 'barangay': addr.barangay}
        : null;

    final success = await cart.applyVoucher(code, destination: dest);
    if (mounted) {
      setState(() => _isApplyingVoucher = false);
      if (success) {
        _voucherCtrl.clear();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Voucher $code applied successfully!'), backgroundColor: Colors.green),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(cart.errorMessage ?? 'Invalid voucher code.'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _removeVoucher() async {
    final cart = context.read<CartProvider>();
    cart.removeVoucher();
    await _refreshQuoteForSelectedAddress();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Voucher removed.')),
      );
    }
  }

  Future<void> _placeOrder() async {
    final cart = context.read<CartProvider>();
    final addr = _currentAddress;

    if (addr == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select or add a delivery address.')),
      );
      return;
    }

    if (cart.checkoutItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No items selected for checkout.')),
      );
      return;
    }

    if (cart.checkoutToken == null || cart.quoteHash == null) {
      await _refreshQuoteForSelectedAddress();
      if (!mounted) return;
      if (cart.checkoutToken == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please wait while totals are verified with the server.')),
        );
        return;
      }
    }

    setState(() => _isSubmitting = true);

    final payload = <String, dynamic>{
      'checkout_token': cart.checkoutToken,
      'quote_hash': cart.quoteHash,
      'payment_method': 'COD',
      'cart': cart.checkoutItems.map((i) => i.toLineMap()).toList(),
      'saved_address_id': addr.id,
      if (cart.voucherId != null) 'voucher_id': cart.voucherId,
      if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
    };

    final dest = <String, dynamic>{
      'province': addr.province,
      'municipality': addr.municipality,
      'barangay': addr.barangay,
    };
    final previousTotal = cart.total;
    final refreshed = await cart.fetchAuthoritativeQuote(destination: dest);
    if (!mounted) return;

    if (!refreshed || (previousTotal - cart.total).abs() > 0.005) {
      setState(() => _isSubmitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(refreshed
              ? 'Delivery pricing updated for this address. Please review the updated total before placing order.'
              : (cart.errorMessage ?? 'Unable to verify delivery pricing.')),
        ),
      );
      return;
    }

    payload['checkout_token'] = cart.checkoutToken;
    payload['quote_hash'] = cart.quoteHash;

    try {
      final res = await ApiService.post(ApiConfig.checkoutEndpoint, payload);
      final body = jsonDecode(res.body);
      setState(() => _isSubmitting = false);

      if (res.statusCode == 201 && body['success'] == true) {
        // Remove only the purchased items from database cart and local state!
        cart.removePurchasedItems();

        if (!mounted) return;
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (ctx) => AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.check_circle, color: Colors.green),
                SizedBox(width: 8),
                Text('Order Placed!'),
              ],
            ),
            content: Text(body['message'] ?? 'Your order has been recorded. Sellers will prepare your shipment.'),
            actions: [
              ElevatedButton(
                onPressed: () {
                  Navigator.pop(ctx);
                  Navigator.of(context).popUntil((route) => route.isFirst);
                },
                child: const Text('Back to Home'),
              ),
            ],
          ),
        );
      } else if (mounted) {
        final msg = body['message'] ?? 'Unable to place order.';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
      }
    } catch (e) {
      setState(() => _isSubmitting = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);
    final addr = _currentAddress;

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Delivery Address Section (Clean & without +New)
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.location_on_outlined, color: AppTheme.primary, size: 20),
                          const SizedBox(width: 8),
                          const Text('Delivery Address', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                          const Spacer(),
                          TextButton.icon(
                            onPressed: () async {
                              final selected = await Navigator.of(context).push<AddressModel>(
                                MaterialPageRoute(builder: (_) => const AddressesPage(selectMode: true)),
                              );
                              await _loadAddresses();
                              if (selected != null) {
                                setState(() => _selectedAddressId = selected.id);
                                _refreshQuoteForSelectedAddress();
                              }
                            },
                            icon: const Icon(Icons.menu_book_outlined, size: 16),
                            label: Text(
                              _savedAddresses.isEmpty ? 'Add Address' : 'Address Book',
                              style: const TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                      const Divider(height: 16),

                      if (_isLoadingAddresses)
                        const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(child: CircularProgressIndicator()),
                        )
                      else if (addr != null) ...[
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: AppTheme.primarySoft,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                addr.label.toUpperCase(),
                                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.primary),
                              ),
                            ),
                            if (addr.isDefault) ...[
                              const SizedBox(width: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.grey.shade200,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: const Text('DEFAULT', style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold)),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          '${addr.recipientName} (${addr.recipientContact})',
                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          addr.fullAddress,
                          style: const TextStyle(fontSize: 12.5, color: AppTheme.muted, height: 1.3),
                        ),
                      ] else ...[
                        Center(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            child: Column(
                              children: [
                                const Icon(Icons.location_off_outlined, color: AppTheme.muted, size: 36),
                                const SizedBox(height: 8),
                                const Text('No delivery address selected.', style: TextStyle(fontSize: 13, color: AppTheme.muted)),
                                const SizedBox(height: 10),
                                ElevatedButton.icon(
                                  onPressed: () async {
                                    final selected = await Navigator.of(context).push<AddressModel>(
                                      MaterialPageRoute(builder: (_) => const AddressesPage(selectMode: true)),
                                    );
                                    await _loadAddresses();
                                    if (selected != null) {
                                      setState(() => _selectedAddressId = selected.id);
                                      _refreshQuoteForSelectedAddress();
                                    }
                                  },
                                  icon: const Icon(Icons.add, size: 16),
                                  label: const Text('Select or Add Address'),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Payment Method Section
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: const ListTile(
                  leading: Icon(Icons.payments_outlined, color: Colors.green),
                  title: Text('Cash on Delivery (COD)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                  subtitle: Text('Pay cash upon delivery.', style: TextStyle(fontSize: 12, color: AppTheme.muted)),
                  trailing: Icon(Icons.check_circle, color: Colors.green),
                ),
              ),
              const SizedBox(height: 12),

              // Ordered Items Summary (Selected Items only)
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Ordered Items (${cart.selectedItemCount})',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const Divider(height: 16),
                      ...cart.checkoutItems.map((item) {
                        return Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Text(
                                  '${item.quantity}× ${item.product.name}${item.variation != null ? " (${item.variation!.value})" : ""}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 13),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                currency.format(item.total),
                                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                              ),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Voucher Section (Vouchers apply on Checkout page only)
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Row(
                            children: [
                              Icon(Icons.confirmation_number_outlined, color: AppTheme.primary, size: 18),
                              SizedBox(width: 6),
                              Text('Platform / Shop Voucher', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                            ],
                          ),
                          InkWell(
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => const VouchersPage()),
                              );
                            },
                            child: const Text('Browse Vouchers', style: TextStyle(fontSize: 11.5, color: AppTheme.primary, fontWeight: FontWeight.w600)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      if (cart.appliedVoucherCode != null) ...[
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: Colors.green.shade50,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.green.shade200),
                          ),
                          child: Row(
                            children: [
                              Icon(Icons.check_circle, color: Colors.green.shade700, size: 18),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Voucher "${cart.appliedVoucherCode}" applied (-${currency.format(cart.discount)})',
                                  style: TextStyle(color: Colors.green.shade900, fontWeight: FontWeight.w600, fontSize: 12),
                                ),
                              ),
                              IconButton(
                                icon: const Icon(Icons.close, size: 16),
                                constraints: const BoxConstraints(),
                                padding: EdgeInsets.zero,
                                onPressed: _removeVoucher,
                              ),
                            ],
                          ),
                        ),
                      ] else ...[
                        Row(
                          children: [
                            Expanded(
                              child: SizedBox(
                                height: 42,
                                child: TextField(
                                  controller: _voucherCtrl,
                                  textCapitalization: TextCapitalization.characters,
                                  decoration: const InputDecoration(
                                    hintText: 'Enter Voucher Code',
                                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                                minimumSize: const Size(70, 42),
                              ),
                              onPressed: _isApplyingVoucher ? null : _applyVoucher,
                              child: _isApplyingVoucher
                                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                  : const Text('Apply'),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Order Notes
              TextField(
                controller: _notesCtrl,
                maxLines: 2,
                maxLength: 200,
                decoration: const InputDecoration(
                  labelText: 'Delivery Notes (Optional, max 200 chars)',
                  hintText: 'e.g. Leave package with guard if absent',
                ),
              ),
              const SizedBox(height: 12),

              // Total Breakdown Card
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Subtotal', style: TextStyle(color: AppTheme.muted)),
                          Text(currency.format(cart.subtotal), style: const TextStyle(fontWeight: FontWeight.w600)),
                        ],
                      ),
                      if (cart.discount > 0) ...[
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Voucher (${cart.appliedVoucherCode})', style: const TextStyle(color: Colors.green)),
                            Text('-${currency.format(cart.discount)}', style: const TextStyle(color: Colors.green, fontWeight: FontWeight.w600)),
                          ],
                        ),
                      ],
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Shipping Fee', style: TextStyle(color: AppTheme.muted)),
                          Text(currency.format(cart.shippingFee), style: const TextStyle(fontWeight: FontWeight.w600)),
                        ],
                      ),
                      const Divider(height: 18),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Total Amount', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                          Text(currency.format(cart.total), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppTheme.primary)),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),

              ElevatedButton(
                style: ElevatedButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
                onPressed: _isSubmitting ? null : _placeOrder,
                child: _isSubmitting
                    ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Text('Place Order (COD)'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
