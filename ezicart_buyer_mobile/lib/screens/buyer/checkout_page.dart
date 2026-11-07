import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/address_model.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import '../common/psgc_address_form.dart';
import 'addresses_page.dart';

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

  final _notesCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _contactCtrl = TextEditingController();
  AddressFormData _customAddressData = AddressFormData();

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    _nameCtrl.dispose();
    _contactCtrl.dispose();
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
          setState(() {
            _savedAddresses = list;
            _isLoadingAddresses = false;
            if (list.isNotEmpty) {
              final def = list.firstWhere((a) => a.isDefault, orElse: () => list.first);
              _selectedAddressId = def.id;
            }
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoadingAddresses = false);
  }

  Future<void> _placeOrder() async {
    final cart = context.read<CartProvider>();

    if (cart.checkoutToken == null || cart.quoteHash == null) {
      final refreshed = await cart.fetchAuthoritativeQuote();
      if (!mounted) return;
      if (!refreshed || cart.checkoutToken == null) {
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
      'cart': cart.items.values.map((i) => i.toLineMap()).toList(),
      if (cart.voucherId != null) 'voucher_id': cart.voucherId,
      if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
    };

    if (_selectedAddressId != null) {
      payload['saved_address_id'] = _selectedAddressId;
    } else {
      if (_nameCtrl.text.trim().isEmpty || _contactCtrl.text.trim().isEmpty) {
        setState(() => _isSubmitting = false);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please complete delivery recipient name and mobile number.')),
        );
        return;
      }
      if (!_customAddressData.hasLocation) {
        setState(() => _isSubmitting = false);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please select or complete the region, province, city, and barangay location details.')),
        );
        return;
      }
      payload['recipient_name'] = _nameCtrl.text.trim();
      payload['recipient_contact'] = _contactCtrl.text.trim();
      payload['region'] = _customAddressData.region;
      payload['province'] = _customAddressData.province;
      payload['municipality'] = _customAddressData.municipality;
      payload['barangay'] = _customAddressData.barangay;
      if (_customAddressData.streetAddress != null) payload['street_address'] = _customAddressData.streetAddress;
      if (_customAddressData.houseNumber != null) payload['house_number'] = _customAddressData.houseNumber;
      if (_customAddressData.postalCode != null) payload['postal_code'] = _customAddressData.postalCode;
    }

    try {
      final res = await ApiService.post(ApiConfig.checkoutEndpoint, payload);
      final body = jsonDecode(res.body);
      setState(() => _isSubmitting = false);

      if (res.statusCode == 201 && body['success'] == true && mounted) {
        cart.clearCart();
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
                child: const Text('Go to Dashboard'),
              ),
            ],
          ),
        );
      } else if (mounted) {
        final msg = body['message'] ?? 'Unable to place order.';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
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

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Delivery Address Section
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
                          TextButton(
                            onPressed: () async {
                              final selected = await Navigator.of(context).push<AddressModel>(
                                MaterialPageRoute(builder: (_) => const AddressesPage(selectMode: true)),
                              );
                              if (selected != null) {
                                await _loadAddresses();
                                setState(() => _selectedAddressId = selected.id);
                              }
                            },
                            child: const Text('Address Book', style: TextStyle(fontSize: 12, color: AppTheme.primary)),
                          ),
                          TextButton(
                            onPressed: () => setState(() => _selectedAddressId = null),
                            child: const Text('+ New', style: TextStyle(fontSize: 12, color: AppTheme.primary)),
                          ),
                        ],
                      ),
                      const Divider(height: 16),

                      if (_isLoadingAddresses)
                        const Padding(
                          padding: EdgeInsets.all(12),
                          child: Center(child: CircularProgressIndicator()),
                        )
                      else if (_savedAddresses.isNotEmpty && _selectedAddressId != null) ...[
                        ..._savedAddresses.map((addr) {
                          final isSelected = _selectedAddressId == addr.id;
                          return Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            decoration: BoxDecoration(
                              border: Border.all(color: isSelected ? AppTheme.primary : AppTheme.line),
                              borderRadius: BorderRadius.circular(8),
                              color: isSelected ? AppTheme.primarySoft.withValues(alpha: 0.4) : null,
                            ),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(8),
                              onTap: () => setState(() => _selectedAddressId = addr.id),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                child: Row(
                                  children: [
                                    Icon(
                                      isSelected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                                      color: isSelected ? AppTheme.primary : AppTheme.muted,
                                      size: 20,
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              Text(addr.label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                              if (addr.isDefault)
                                                Container(
                                                  margin: const EdgeInsets.only(left: 8),
                                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                  decoration: BoxDecoration(
                                                    color: Colors.grey.shade200,
                                                    borderRadius: BorderRadius.circular(4),
                                                  ),
                                                  child: const Text('DEFAULT', style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold)),
                                                ),
                                            ],
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            '${addr.recipientName} (${addr.recipientContact})\n${addr.fullAddress}',
                                            style: const TextStyle(fontSize: 12, color: AppTheme.muted, height: 1.3),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        }),
                      ] else ...[
                        const Text('Enter Delivery Details:', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 10),
                        TextField(
                          controller: _nameCtrl,
                          decoration: const InputDecoration(labelText: 'Recipient Full Name *'),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _contactCtrl,
                          keyboardType: TextInputType.phone,
                          decoration: const InputDecoration(labelText: 'Recipient Mobile (09XXXXXXXXX) *'),
                        ),
                        const SizedBox(height: 12),
                        PsgcAddressForm(
                          initialData: _customAddressData,
                          onChanged: (data) => _customAddressData = data,
                        ),
                        if (_savedAddresses.isNotEmpty)
                          TextButton(
                            onPressed: () => setState(() => _selectedAddressId = _savedAddresses.first.id),
                            child: const Text('Use Saved Address Instead'),
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
                  subtitle: Text('Pay in cash directly to the courier upon delivery.', style: TextStyle(fontSize: 12, color: AppTheme.muted)),
                  trailing: Icon(Icons.check_circle, color: Colors.green),
                ),
              ),
              const SizedBox(height: 12),

              // Items Summary
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
                      Text('Ordered Items (${cart.itemCount})', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                      const Divider(height: 16),
                      ...cart.items.values.map((item) {
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
                              Text(currency.format(item.total), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Order Notes
              TextField(
                controller: _notesCtrl,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Delivery Notes (Optional)',
                  hintText: 'e.g. Leave package with guard if absent',
                ),
              ),
              const SizedBox(height: 16),

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
