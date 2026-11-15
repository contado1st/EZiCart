import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/cart_item_model.dart';
import '../../models/product_model.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import 'checkout_page.dart';

class CartPage extends StatefulWidget {
  const CartPage({super.key});

  @override
  State<CartPage> createState() => _CartPageState();
}

class _CartPageState extends State<CartPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().fetchCart();
    });
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
              Icon(
                Icons.remove_shopping_cart_outlined,
                size: 64,
                color: Colors.grey.shade400,
              ),
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

    // Group items by seller (shop)
    final Map<int, List<MapEntry<String, CartItemModel>>> groupedByShop = {};
    for (final entry in cart.items.entries) {
      final sellerId = entry.value.product.sellerId ?? 0;
      groupedByShop.putIfAbsent(sellerId, () => []).add(entry);
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
                  content: const Text(
                    'Remove all items from your shopping cart?',
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(ctx),
                      child: const Text('Cancel'),
                    ),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.red,
                      ),
                      onPressed: () {
                        cart.clearCart();
                        Navigator.pop(ctx);
                      },
                      child: const Text('Clear All'),
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
            if (cart.isLoading || cart.isSyncing)
              const LinearProgressIndicator(
                minHeight: 2,
                backgroundColor: AppTheme.line,
                color: AppTheme.primary,
              ),

            // Multiple shops warning banner
            if (cart.hasMultipleShopsSelected)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
                color: Colors.amber.shade50,
                child: Row(
                  children: [
                    Icon(
                      Icons.info_outline,
                      color: Colors.amber.shade900,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Choose products from one shop per checkout. Items from other shops stay saved in your cart.',
                        style: TextStyle(
                          color: Colors.amber.shade900,
                          fontSize: 12,
                          height: 1.25,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

            Expanded(
              child: RefreshIndicator(
                onRefresh: () => cart.fetchCart(),
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
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
                            Icon(
                              Icons.error_outline,
                              color: Colors.red.shade700,
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                cart.errorMessage!,
                                style: TextStyle(
                                  color: Colors.red.shade900,
                                  fontSize: 12,
                                  height: 1.3,
                                ),
                              ),
                            ),
                            TextButton(
                              onPressed: () => cart.fetchAuthoritativeQuote(),
                              child: const Text(
                                'Retry',
                                style: TextStyle(fontSize: 12),
                              ),
                            ),
                          ],
                        ),
                      ),

                    // Shop groups
                    ...groupedByShop.entries.map((shopGroup) {
                      final sellerId = shopGroup.key;
                      final shopItems = shopGroup.value;
                      final sellerName =
                          shopItems.first.value.product.sellerName;
                      final isShopAllSelected = shopItems.every(
                        (e) => cart.isSelected(e.key),
                      );

                      return Card(
                        margin: const EdgeInsets.only(bottom: 14),
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: AppTheme.line),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Shop Header with Select All for Shop
                            InkWell(
                              onTap: () =>
                                  cart.selectShop(sellerId, !isShopAllSelected),
                              borderRadius: const BorderRadius.vertical(
                                top: Radius.circular(12),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 8,
                                ),
                                child: Row(
                                  children: [
                                    SizedBox(
                                      width: 28,
                                      height: 28,
                                      child: Checkbox(
                                        value: isShopAllSelected,
                                        activeColor: AppTheme.primary,
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(
                                            4,
                                          ),
                                        ),
                                        onChanged: (v) => cart.selectShop(
                                          sellerId,
                                          v ?? false,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 4),
                                    const Icon(
                                      Icons.storefront_outlined,
                                      size: 18,
                                      color: AppTheme.primary,
                                    ),
                                    const SizedBox(width: 6),
                                    Expanded(
                                      child: Text(
                                        sellerName,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 13,
                                          color: AppTheme.ink,
                                        ),
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const Divider(height: 1),

                            // Items in this shop
                            ...shopItems.map((entry) {
                              final key = entry.key;
                              final item = entry.value;
                              final imgPath = item.displayImage;
                              final isSelected = cart.isSelected(key);
                              final canIncrement =
                                  item.quantity < item.availableStock &&
                                  item.quantity < 20;
                              final canDecrement = item.quantity > 1;

                              return Padding(
                                padding: const EdgeInsets.all(10),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.center,
                                  children: [
                                    // Item Checkbox Toggle
                                    SizedBox(
                                      width: 28,
                                      height: 28,
                                      child: Checkbox(
                                        value: isSelected,
                                        activeColor: AppTheme.primary,
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(
                                            4,
                                          ),
                                        ),
                                        onChanged: (_) =>
                                            cart.toggleSelect(key),
                                      ),
                                    ),
                                    const SizedBox(width: 6),

                                    // Thumbnail
                                    Container(
                                      width: 64,
                                      height: 64,
                                      decoration: BoxDecoration(
                                        color: Colors.grey.shade100,
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(
                                          color: AppTheme.line,
                                        ),
                                      ),
                                      child: ClipRRect(
                                        borderRadius: BorderRadius.circular(8),
                                        child:
                                            (imgPath != null &&
                                                ApiConfig.resolveImageUrl(
                                                      imgPath,
                                                    ) !=
                                                    null)
                                            ? CachedNetworkImage(
                                                imageUrl:
                                                    ApiConfig.resolveImageUrl(
                                                      imgPath,
                                                    )!,
                                                fit: BoxFit.cover,
                                                placeholder: (_, __) =>
                                                    const Center(
                                                      child: SizedBox(
                                                        width: 16,
                                                        height: 16,
                                                        child:
                                                            CircularProgressIndicator(
                                                              strokeWidth: 2,
                                                            ),
                                                      ),
                                                    ),
                                                errorWidget: (_, __, ___) =>
                                                    const Icon(
                                                      Icons
                                                          .inventory_2_outlined,
                                                      color: AppTheme.muted,
                                                      size: 24,
                                                    ),
                                              )
                                            : const Icon(
                                                Icons.inventory_2_outlined,
                                                color: AppTheme.muted,
                                                size: 24,
                                              ),
                                      ),
                                    ),
                                    const SizedBox(width: 10),

                                    // Details
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            item.product.name,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w600,
                                              fontSize: 13,
                                            ),
                                          ),
                                          if (item.variation != null ||
                                              item.product.variations.isNotEmpty) ...[
                                            const SizedBox(height: 3),
                                            InkWell(
                                              onTap: () => _showVariationPicker(
                                                item,
                                                key,
                                              ),
                                              borderRadius:
                                                  BorderRadius.circular(4),
                                              child: Container(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                      horizontal: 6,
                                                      vertical: 2.5,
                                                    ),
                                                decoration: BoxDecoration(
                                                  color: Colors.grey.shade100,
                                                  borderRadius:
                                                      BorderRadius.circular(4),
                                                  border: Border.all(
                                                    color: Colors.grey.shade300,
                                                  ),
                                                ),
                                                child: Row(
                                                  mainAxisSize:
                                                      MainAxisSize.min,
                                                  children: [
                                                    Flexible(
                                                      child: Text(
                                                        item.variation != null
                                                            ? '${item.variation!.type}: ${item.variation!.value}'
                                                            : 'Select variation',
                                                        style: const TextStyle(
                                                          fontSize: 10.5,
                                                          color: AppTheme.ink,
                                                          fontWeight:
                                                              FontWeight.w500,
                                                        ),
                                                        maxLines: 1,
                                                        overflow:
                                                            TextOverflow.ellipsis,
                                                      ),
                                                    ),
                                                    const SizedBox(width: 3),
                                                    const Icon(
                                                      Icons.keyboard_arrow_down,
                                                      size: 14,
                                                      color: AppTheme.muted,
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ),
                                          ],
                                          const SizedBox(height: 6),
                                          Text(
                                            currency.format(item.unitPrice),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                              color: AppTheme.primary,
                                              fontSize: 14,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),

                                    // Quantity controls & Remove
                                    Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.end,
                                      children: [
                                        IconButton(
                                          icon: const Icon(
                                            Icons.close,
                                            size: 16,
                                            color: AppTheme.muted,
                                          ),
                                          padding: EdgeInsets.zero,
                                          constraints: const BoxConstraints(),
                                          onPressed: () => cart.removeItem(key),
                                        ),
                                        const SizedBox(height: 12),
                                        Container(
                                          decoration: BoxDecoration(
                                            border: Border.all(
                                              color: AppTheme.line,
                                            ),
                                            borderRadius: BorderRadius.circular(
                                              6,
                                            ),
                                          ),
                                          child: Row(
                                            children: [
                                              InkWell(
                                                onTap: canDecrement
                                                    ? () =>
                                                        cart.updateQuantity(
                                                          key,
                                                          item.quantity - 1,
                                                        )
                                                    : null,
                                                child: Padding(
                                                  padding: const EdgeInsets.symmetric(
                                                    horizontal: 6,
                                                    vertical: 2,
                                                  ),
                                                  child: Icon(
                                                    Icons.remove,
                                                    size: 14,
                                                    color: canDecrement
                                                        ? AppTheme.ink
                                                        : Colors.grey.shade300,
                                                  ),
                                                ),
                                              ),
                                              Padding(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                      horizontal: 6,
                                                    ),
                                                child: Text(
                                                  '${item.quantity}',
                                                  style: const TextStyle(
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 12,
                                                  ),
                                                ),
                                              ),
                                              InkWell(
                                                onTap: canIncrement
                                                    ? () => cart.updateQuantity(
                                                        key,
                                                        item.quantity + 1,
                                                      )
                                                    : null,
                                                child: Padding(
                                                  padding:
                                                      const EdgeInsets.symmetric(
                                                        horizontal: 6,
                                                        vertical: 2,
                                                      ),
                                                  child: Icon(
                                                    Icons.add,
                                                    size: 14,
                                                    color: canIncrement
                                                        ? AppTheme.ink
                                                        : Colors.grey.shade300,
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
                              );
                            }),
                          ],
                        ),
                      );
                    }),
                  ],
                ),
              ),
            ),

            // Bottom bar with Select All, Selected Total, and Checkout
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: const BoxDecoration(
                color: AppTheme.surface,
                border: Border(top: BorderSide(color: AppTheme.line)),
              ),
              child: Row(
                children: [
                  // Select All toggle
                  InkWell(
                    onTap: () => cart.selectAll(!cart.isAllSelected),
                    borderRadius: BorderRadius.circular(6),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(
                          width: 24,
                          height: 24,
                          child: Checkbox(
                            value: cart.isAllSelected,
                            activeColor: AppTheme.primary,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(4),
                            ),
                            onChanged: (v) => cart.selectAll(v ?? false),
                          ),
                        ),
                        const SizedBox(width: 4),
                        const Text(
                          'All',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),

                  // Selected Subtotal
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'Total:',
                        style: TextStyle(color: AppTheme.muted, fontSize: 11),
                      ),
                      Text(
                        currency.format(cart.selectedSubtotal),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.primary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(width: 12),

                  // Checkout button
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 12,
                      ),
                      minimumSize: const Size(120, 44),
                    ),
                    onPressed:
                        (cart.selectedItems.isEmpty ||
                            cart.hasMultipleShopsSelected ||
                            cart.isLoading)
                        ? null
                        : () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const CheckoutPage(),
                              ),
                            );
                          },
                    child: cart.isLoading
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : Text('Checkout (${cart.selectedItemCount})'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _showVariationPicker(
    CartItemModel item,
    String key,
  ) async {
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);
    final cart = context.read<CartProvider>();

    // If item.product.variations has only 1 or 0 variations, attempt to fetch the complete variations from product API
    List<ProductVariation> availableVariations = List.from(item.product.variations);
    if (availableVariations.length <= 1) {
      try {
        final res = await ApiService.get('${ApiConfig.productsEndpoint}/${item.product.id}');
        if (res.statusCode == 200) {
          final body = jsonDecode(res.body);
          if (body['product'] != null) {
            final fullProduct = ProductModel.fromJson(body['product'] as Map<String, dynamic>);
            if (fullProduct.variations.isNotEmpty) {
              availableVariations = fullProduct.variations;
            }
          }
        }
      } catch (_) {}
    }

    if (!mounted) return;

    if (availableVariations.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No other variations available for this product'),
          duration: Duration(seconds: 2),
        ),
      );
      return;
    }

    ProductVariation? currentSelected = item.variation;
    if (currentSelected != null) {
      final match = availableVariations.where((v) => v.id == currentSelected!.id);
      if (match.isNotEmpty) {
        currentSelected = match.first;
      }
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (modalCtx, setModalState) {
            final currentPrice = item.product.price + (currentSelected?.priceAdjustment ?? 0.0);
            final currentStock = currentSelected?.stock ?? item.product.stock;
            final isSameVariation = currentSelected?.id == item.variation?.id;
            final selectedImg = (currentSelected?.imagePath != null &&
                    currentSelected!.imagePath!.trim().isNotEmpty)
                ? currentSelected!.imagePath
                : item.displayImage;
            final resolvedImg = ApiConfig.resolveImageUrl(selectedImg);

            return Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              ),
              padding: EdgeInsets.fromLTRB(
                16,
                16,
                16,
                MediaQuery.of(modalCtx).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Header
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          width: 68,
                          height: 68,
                          color: Colors.grey.shade100,
                          child: resolvedImg != null
                              ? CachedNetworkImage(
                                  imageUrl: resolvedImg,
                                  fit: BoxFit.cover,
                                  errorWidget: (_, __, ___) => const Icon(
                                    Icons.inventory_2_outlined,
                                    color: AppTheme.muted,
                                  ),
                                )
                              : const Icon(
                                  Icons.inventory_2_outlined,
                                  color: AppTheme.muted,
                                ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.product.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              currency.format(currentPrice),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                                color: AppTheme.primary,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              currentStock > 0
                                  ? 'Stock: $currentStock available'
                                  : 'Out of stock',
                              style: TextStyle(
                                fontSize: 12,
                                color: currentStock > 0 ? AppTheme.muted : Colors.red,
                                fontWeight: currentStock > 0 ? FontWeight.normal : FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close, size: 20),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const Divider(height: 24),

                  // Variation chips
                  const Text(
                    'Choose Variation',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                      color: AppTheme.ink,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: availableVariations.map((v) {
                      final isSelected = currentSelected?.id == v.id;
                      final isOutOfStock = v.stock <= 0;

                      return ChoiceChip(
                        label: Text(
                          '${v.type}: ${v.value}${v.priceAdjustment != 0 ? " (+${currency.format(v.priceAdjustment)})" : ""}${isOutOfStock ? " (Out of stock)" : ""}',
                          style: TextStyle(
                            color: isSelected
                                ? Colors.white
                                : (isOutOfStock ? AppTheme.muted : AppTheme.ink),
                            fontSize: 12,
                            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                            decoration: isOutOfStock ? TextDecoration.lineThrough : null,
                          ),
                        ),
                        selected: isSelected,
                        selectedColor: AppTheme.primary,
                        backgroundColor: AppTheme.surface,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(7),
                          side: BorderSide(
                            color: isSelected ? AppTheme.primary : AppTheme.line,
                          ),
                        ),
                        onSelected: isOutOfStock
                            ? null
                            : (_) {
                                setModalState(() {
                                  currentSelected = v;
                                });
                              },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 20),

                  // Confirm button
                  SizedBox(
                    width: double.infinity,
                    height: 44,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primary,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      onPressed: (currentSelected != null && !isSameVariation && currentSelected!.stock > 0)
                          ? () async {
                              final chosen = currentSelected!;
                              Navigator.pop(ctx);
                              await cart.changeVariation(key, chosen);
                              if (mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text('Variation updated to ${chosen.value}'),
                                    backgroundColor: AppTheme.primary,
                                    duration: const Duration(seconds: 2),
                                  ),
                                );
                              }
                            }
                          : null,
                      child: Text(
                        isSameVariation
                            ? 'Currently Selected'
                            : (currentSelected != null && currentSelected!.stock <= 0
                                ? 'Selected Variation Out of Stock'
                                : 'Confirm Variation'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
