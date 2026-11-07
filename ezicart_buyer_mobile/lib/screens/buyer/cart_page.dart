import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/cart_provider.dart';
import 'checkout_page.dart';
import 'vouchers_page.dart';

class CartPage extends StatefulWidget {
  const CartPage({super.key});

  @override
  State<CartPage> createState() => _CartPageState();
}

class _CartPageState extends State<CartPage> {
  final _voucherCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().fetchAuthoritativeQuote();
    });
  }

  @override
  void dispose() {
    _voucherCtrl.dispose();
    super.dispose();
  }

  void _applyVoucher() async {
    final code = _voucherCtrl.text.trim();
    if (code.isEmpty) return;

    final cart = context.read<CartProvider>();
    final success = await cart.applyVoucher(code);

    if (!mounted) return;

    if (success) {
      _voucherCtrl.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Voucher $code applied successfully!'), backgroundColor: Colors.green),
      );
    } else if (cart.errorMessage != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(cart.errorMessage!)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);

    if (cart.items.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Shopping Cart')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.remove_shopping_cart_outlined, size: 64, color: Colors.grey.shade400),
              const SizedBox(height: 16),
              const Text(
                'Your cart is currently empty',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 6),
              const Text(
                'Explore the marketplace and discover great products.',
                style: TextStyle(color: AppTheme.muted, fontSize: 13),
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  minimumSize: const Size(180, 42),
                ),
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Browse Products'),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text('Shopping Cart (${cart.itemCount})'),
        actions: [
          IconButton(
            icon: const Icon(Icons.delete_sweep_outlined),
            tooltip: 'Clear Cart',
            onPressed: () {
              showDialog(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Clear Cart'),
                  content: const Text('Are you sure you want to remove all items from your cart?'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
                    TextButton(
                      onPressed: () {
                        cart.clearCart();
                        Navigator.pop(ctx);
                      },
                      child: const Text('Clear All', style: TextStyle(color: Colors.red)),
                    ),
                  ],
                ),
              );
            },
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            if (cart.isLoading)
              const LinearProgressIndicator(
                minHeight: 2,
                backgroundColor: AppTheme.line,
                color: AppTheme.primary,
              ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (cart.errorMessage != null)
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.red.shade200),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.error_outline, color: Colors.red.shade700, size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              cart.errorMessage!,
                              style: TextStyle(color: Colors.red.shade900, fontSize: 12, height: 1.3),
                            ),
                          ),
                          TextButton(
                            onPressed: () => cart.fetchAuthoritativeQuote(),
                            child: const Text('Retry', style: TextStyle(fontSize: 12)),
                          ),
                        ],
                      ),
                    ),
                  ...cart.items.entries.map((entry) {
                    final key = entry.key;
                    final item = entry.value;
                    final imgPath = item.displayImage;
                    final canIncrement = item.quantity < item.availableStock && item.quantity < 20;

                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                        side: const BorderSide(color: AppTheme.line),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Thumbnail
                            Container(
                              width: 64,
                              height: 64,
                              decoration: BoxDecoration(
                                color: Colors.grey.shade100,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: ApiConfig.resolveImageUrl(imgPath) != null
                                  ? ClipRRect(
                                      borderRadius: BorderRadius.circular(8),
                                      child: CachedNetworkImage(
                                        imageUrl: ApiConfig.resolveImageUrl(imgPath)!,
                                        fit: BoxFit.cover,
                                        placeholder: (_, __) => const Center(
                                          child: SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(strokeWidth: 2),
                                          ),
                                        ),
                                        errorWidget: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: AppTheme.muted),
                                      ),
                                    )
                                  : const Icon(Icons.inventory_2_outlined, color: AppTheme.muted),
                            ),
                            const SizedBox(width: 12),

                            // Details
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    item.product.sellerName,
                                    style: const TextStyle(fontSize: 11, color: AppTheme.muted, fontWeight: FontWeight.w600),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    item.product.name,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                  ),
                                  if (item.variation != null)
                                    Padding(
                                      padding: const EdgeInsets.only(top: 2),
                                      child: Text(
                                        '${item.variation!.type}: ${item.variation!.value}',
                                        style: const TextStyle(color: AppTheme.primary, fontSize: 11, fontWeight: FontWeight.w600),
                                      ),
                                    ),
                                  const SizedBox(height: 6),
                                  Text(
                                    currency.format(item.unitPrice),
                                    style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.ink, fontSize: 13),
                                  ),
                                ],
                              ),
                            ),

                            // Quantity controls & Remove
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                IconButton(
                                  icon: const Icon(Icons.close, size: 16, color: AppTheme.muted),
                                  padding: EdgeInsets.zero,
                                  constraints: const BoxConstraints(),
                                  onPressed: () => cart.removeItem(key),
                                ),
                                const SizedBox(height: 12),
                                Container(
                                  decoration: BoxDecoration(
                                    border: Border.all(color: AppTheme.line),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(
                                    children: [
                                      InkWell(
                                        onTap: () => cart.updateQuantity(key, item.quantity - 1),
                                        child: const Padding(
                                          padding: EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          child: Icon(Icons.remove, size: 14),
                                        ),
                                      ),
                                      Padding(
                                        padding: const EdgeInsets.symmetric(horizontal: 8),
                                        child: Text('${item.quantity}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                      ),
                                      InkWell(
                                        onTap: canIncrement ? () => cart.updateQuantity(key, item.quantity + 1) : null,
                                        child: Padding(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          child: Icon(
                                            Icons.add,
                                            size: 14,
                                            color: canIncrement ? AppTheme.ink : AppTheme.muted,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  }),

                  const SizedBox(height: 8),

                  // Voucher Box
                  Card(
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                      side: const BorderSide(color: AppTheme.line),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: cart.appliedVoucherCode != null
                          ? Row(
                              children: [
                                const Icon(Icons.confirmation_number_outlined, color: Colors.green, size: 20),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    'Voucher ${cart.appliedVoucherCode} Applied (-${currency.format(cart.discount)})',
                                    style: const TextStyle(color: Colors.green, fontWeight: FontWeight.bold, fontSize: 13),
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.close, size: 18, color: Colors.red),
                                  onPressed: () => cart.removeVoucher(),
                                ),
                              ],
                            )
                          : Row(
                              children: [
                                Expanded(
                                  child: TextField(
                                    controller: _voucherCtrl,
                                    textCapitalization: TextCapitalization.characters,
                                    decoration: InputDecoration(
                                      hintText: 'Enter Voucher Code',
                                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                      suffixIcon: IconButton(
                                        icon: const Icon(Icons.confirmation_number_outlined, color: AppTheme.primary, size: 20),
                                        tooltip: 'Select from available vouchers',
                                        onPressed: () async {
                                          final code = await Navigator.of(context).push<String>(
                                            MaterialPageRoute(builder: (_) => const VouchersPage(selectMode: true)),
                                          );
                                          if (code != null) {
                                            _voucherCtrl.text = code;
                                            _applyVoucher();
                                          }
                                        },
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                ElevatedButton(
                                  style: ElevatedButton.styleFrom(
                                    minimumSize: const Size(80, 42),
                                  ),
                                  onPressed: cart.isLoading ? null : _applyVoucher,
                                  child: const Text('Apply'),
                                ),
                              ],
                            ),
                    ),
                  ),

                  const SizedBox(height: 12),

                  // Order Summary Card
                  Card(
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                      side: const BorderSide(color: AppTheme.line),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Order Summary', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                          const SizedBox(height: 12),
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
                                const Text('Voucher Discount', style: TextStyle(color: Colors.green)),
                                Text('-${currency.format(cart.discount)}', style: const TextStyle(color: Colors.green, fontWeight: FontWeight.w600)),
                              ],
                            ),
                          ],
                          const SizedBox(height: 6),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Standard Shipping', style: TextStyle(color: AppTheme.muted)),
                              Text(currency.format(cart.shippingFee), style: const TextStyle(fontWeight: FontWeight.w600)),
                            ],
                          ),
                          const Divider(height: 20),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Estimated Total', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                              Text(currency.format(cart.total), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17, color: AppTheme.primary)),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Checkout button bar
            Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                color: AppTheme.surface,
                border: Border(top: BorderSide(color: AppTheme.line)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text('Total Amount:', style: TextStyle(color: AppTheme.muted, fontSize: 12)),
                        Text(
                          currency.format(cart.total),
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.primary),
                        ),
                      ],
                    ),
                  ),
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                    ),
                    onPressed: (cart.isLoading || cart.errorMessage != null || cart.items.isEmpty)
                        ? null
                        : () {
                            Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const CheckoutPage()),
                            );
                          },
                    child: cart.isLoading
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Text('Checkout'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
