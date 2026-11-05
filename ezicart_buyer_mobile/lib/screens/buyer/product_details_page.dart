import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/product_model.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import 'cart_page.dart';
import 'checkout_page.dart';
import 'messages_page.dart';
import 'shop_page.dart';

class ProductDetailsPage extends StatefulWidget {
  final ProductModel product;

  const ProductDetailsPage({super.key, required this.product});

  @override
  State<ProductDetailsPage> createState() => _ProductDetailsPageState();
}

class _ProductDetailsPageState extends State<ProductDetailsPage> {
  late ProductModel _product;
  ProductVariation? _selectedVariation;
  int _quantity = 1;
  bool _isWatched = false;
  bool _isWatchLoading = false;

  @override
  void initState() {
    super.initState();
    _product = widget.product;
    if (_product.variations.isNotEmpty) {
      _selectedVariation = _product.variations.first;
    }
    _loadFreshDetails();
  }

  Future<void> _toggleStockAlert() async {
    setState(() => _isWatchLoading = true);
    try {
      if (_isWatched) {
        final res = await ApiService.delete(ApiConfig.unwatchStockEndpoint(_product.id));
        if (res.statusCode == 200 && mounted) {
          setState(() => _isWatched = false);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Stock alert removed.')),
          );
        }
      } else {
        final res = await ApiService.post(ApiConfig.watchStockEndpoint(_product.id), {});
        if (res.statusCode == 200 && mounted) {
          setState(() => _isWatched = true);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Stock alert active! We will notify you of inventory changes for ${_product.name}.'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _isWatchLoading = false);
    }
  }

  Future<void> _loadFreshDetails() async {
    try {
      final res = await ApiService.get(ApiConfig.productDetailEndpoint(_product.id));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is Map) {
          setState(() {
            _product = ProductModel.fromJson(body['data'] as Map<String, dynamic>);
            if (_product.variations.isNotEmpty && _selectedVariation == null) {
              _selectedVariation = _product.variations.first;
            }
          });
        }
      }
    } catch (_) {}
  }

  double get _currentUnitPrice {
    return _product.price + (_selectedVariation?.priceAdjustment ?? 0.0);
  }

  void _startChatWithSeller() async {
    if (_product.sellerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Seller contact is not available.')),
      );
      return;
    }

    try {
      final res = await ApiService.post(ApiConfig.chatStartEndpoint(_product.sellerId!), {});
      if (res.statusCode == 200 && mounted) {
        final body = jsonDecode(res.body);
        final conversationId = body['conversation_id'];
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ChatConversationScreen(
              conversationId: conversationId,
              partnerName: _product.sellerName,
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not start chat: ${e.toString()}')),
        );
      }
    }
  }

  void _addToCart({bool navigateToCheckout = false}) {
    if (_product.variations.isNotEmpty && _selectedVariation == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select an option/variation first.')),
      );
      return;
    }

    final cart = context.read<CartProvider>();
    cart.addItem(_product, variation: _selectedVariation, quantity: _quantity);

    if (navigateToCheckout) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const CheckoutPage()),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Added $_quantity × ${_product.name} to your cart.'),
          backgroundColor: AppTheme.primary,
          action: SnackBarAction(
            label: 'View Cart',
            textColor: Colors.white,
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const CartPage()),
              );
            },
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final currencyFormat = NumberFormat.currency(symbol: '₱', decimalDigits: 2);
    final cart = context.watch<CartProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text(_product.name, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            icon: _isWatchLoading
                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                : Icon(_isWatched ? Icons.notifications_active : Icons.notifications_none_outlined),
            color: _isWatched ? Colors.amber.shade800 : null,
            tooltip: _isWatched ? 'Stock alert active' : 'Watch stock changes',
            onPressed: _isWatchLoading ? null : _toggleStockAlert,
          ),
          Stack(
            children: [
              IconButton(
                icon: const Icon(Icons.shopping_cart_outlined),
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const CartPage()),
                  );
                },
              ),
              if (cart.itemCount > 0)
                Positioned(
                  right: 8,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: AppTheme.primary,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '${cart.itemCount}',
                      style: const TextStyle(fontSize: 10, color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Product Image Header
              AspectRatio(
                aspectRatio: 1.25,
                child: Container(
                  color: Colors.grey.shade100,
                  child: ApiConfig.resolveImageUrl(_product.imagePath) != null
                      ? CachedNetworkImage(
                          imageUrl: ApiConfig.resolveImageUrl(_product.imagePath)!,
                          fit: BoxFit.cover,
                          errorWidget: (_, __, ___) => const Center(
                            child: Icon(Icons.inventory_2_outlined, size: 64, color: AppTheme.muted),
                          ),
                        )
                      : const Center(
                          child: Icon(Icons.inventory_2_outlined, size: 64, color: AppTheme.muted),
                        ),
                ),
              ),

              Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Category & Rating
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppTheme.primarySoft,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            _product.category,
                            style: const TextStyle(
                              color: AppTheme.primary,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        const Spacer(),
                        const Icon(Icons.star, color: Colors.amber, size: 16),
                        const SizedBox(width: 4),
                        Text(
                          _product.rating.toStringAsFixed(1),
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '(${_product.reviewsCount} reviews)',
                          style: const TextStyle(color: AppTheme.muted, fontSize: 12),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Product Name
                    Text(
                      _product.name,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 10),

                    // Price & Stock
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.baseline,
                      textBaseline: TextBaseline.alphabetic,
                      children: [
                        Text(
                          currencyFormat.format(_currentUnitPrice),
                          style: const TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.primary,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          _product.stock > 0 ? '${_product.stock} in stock' : 'Out of stock',
                          style: TextStyle(
                            color: _product.stock > 0 ? Colors.green.shade700 : Colors.red,
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 28),

                    // Seller Card with Shop & Chat
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppTheme.surface,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppTheme.line),
                      ),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              CircleAvatar(
                                backgroundColor: AppTheme.primarySoft,
                                radius: 20,
                                child: Text(
                                  _product.sellerName.isNotEmpty ? _product.sellerName[0].toUpperCase() : 'S',
                                  style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _product.sellerName,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                    ),
                                    const Row(
                                      children: [
                                        Icon(Icons.verified, size: 13, color: AppTheme.primary),
                                        SizedBox(width: 4),
                                        Text(
                                          'Verified Merchant',
                                          style: TextStyle(fontSize: 12, color: AppTheme.muted),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              if (_product.sellerId != null) ...[
                                Expanded(
                                  child: OutlinedButton.icon(
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: AppTheme.primary,
                                      side: const BorderSide(color: AppTheme.primary),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                                      padding: const EdgeInsets.symmetric(vertical: 8),
                                    ),
                                    icon: const Icon(Icons.storefront_outlined, size: 15),
                                    label: const Text('Visit Shop', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                    onPressed: () {
                                      Navigator.of(context).push(
                                        MaterialPageRoute(
                                          builder: (_) => ShopPage(
                                            sellerId: _product.sellerId!,
                                            initialShopName: _product.sellerName,
                                          ),
                                        ),
                                      );
                                    },
                                  ),
                                ),
                                const SizedBox(width: 8),
                              ],
                              Expanded(
                                child: ElevatedButton.icon(
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppTheme.primarySoft,
                                    foregroundColor: AppTheme.primary,
                                    elevation: 0,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                                    padding: const EdgeInsets.symmetric(vertical: 8),
                                  ),
                                  icon: const Icon(Icons.chat_bubble_outline, size: 15),
                                  label: const Text('Chat Seller', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                  onPressed: _startChatWithSeller,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Variations (if available)
                    if (_product.variations.isNotEmpty) ...[
                      const Text(
                        'Select Option / Variation:',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: _product.variations.map((v) {
                          final isSelected = _selectedVariation?.id == v.id;
                          return ChoiceChip(
                            label: Text(
                              '${v.type}: ${v.value}${v.priceAdjustment != 0 ? " (+${currencyFormat.format(v.priceAdjustment)})" : ""}',
                              style: TextStyle(
                                color: isSelected ? Colors.white : AppTheme.ink,
                                fontSize: 12,
                                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                              ),
                            ),
                            selected: isSelected,
                            selectedColor: AppTheme.primary,
                            backgroundColor: AppTheme.surface,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(7),
                              side: BorderSide(color: isSelected ? AppTheme.primary : AppTheme.line),
                            ),
                            onSelected: (selected) {
                              if (selected) {
                                setState(() => _selectedVariation = v);
                              }
                            },
                          );
                        }).toList(),
                      ),
                      const Divider(height: 28),
                    ],

                    // Quantity incrementer
                    Row(
                      children: [
                        const Text(
                          'Quantity:',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                        ),
                        const Spacer(),
                        Container(
                          decoration: BoxDecoration(
                            border: Border.all(color: AppTheme.line),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(
                            children: [
                              IconButton(
                                icon: const Icon(Icons.remove, size: 18),
                                onPressed: _quantity > 1 ? () => setState(() => _quantity--) : null,
                              ),
                              Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 12),
                                child: Text(
                                  '$_quantity',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                ),
                              ),
                              IconButton(
                                icon: const Icon(Icons.add, size: 18),
                                onPressed: _quantity < _product.stock && _quantity < 20
                                    ? () => setState(() => _quantity++)
                                    : null,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 28),

                    // Description
                    const Text(
                      'Product Description',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _product.description.isNotEmpty
                          ? _product.description
                          : 'No product description provided.',
                      style: const TextStyle(color: AppTheme.muted, height: 1.5, fontSize: 13),
                    ),
                    const Divider(height: 36),

                    // Customer Reviews Section
                    Row(
                      children: [
                        const Text(
                          'Customer Reviews',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '(${_product.reviews.length})',
                          style: const TextStyle(color: AppTheme.muted, fontSize: 13),
                        ),
                        const Spacer(),
                        const Icon(Icons.star, color: Colors.amber, size: 16),
                        const SizedBox(width: 4),
                        Text(
                          _product.rating.toStringAsFixed(1),
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    if (_product.reviews.isEmpty)
                      Container(
                        padding: const EdgeInsets.all(16),
                        width: double.infinity,
                        decoration: BoxDecoration(
                          color: AppTheme.surface,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: AppTheme.line),
                        ),
                        child: const Center(
                          child: Text(
                            'No customer reviews yet. Be the first to order and leave a review!',
                            style: TextStyle(color: AppTheme.muted, fontSize: 12),
                          ),
                        ),
                      )
                    else
                      Column(
                        children: _product.reviews.map((r) {
                          return Container(
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppTheme.surface,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: AppTheme.line),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    CircleAvatar(
                                      radius: 14,
                                      backgroundColor: AppTheme.primarySoft,
                                      child: Text(
                                        r.userName.isNotEmpty ? r.userName[0].toUpperCase() : 'B',
                                        style: const TextStyle(
                                          color: AppTheme.primary,
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(
                                        r.userName,
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                      ),
                                    ),
                                    Row(
                                      children: List.generate(
                                        5,
                                        (i) => Icon(
                                          i < r.rating ? Icons.star : Icons.star_border,
                                          size: 14,
                                          color: Colors.amber,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                if (r.comment != null && r.comment!.isNotEmpty) ...[
                                  const SizedBox(height: 8),
                                  Text(
                                    r.comment!,
                                    style: const TextStyle(fontSize: 13, color: AppTheme.ink),
                                  ),
                                ],
                              ],
                            ),
                          );
                        }).toList(),
                      ),
                    const SizedBox(height: 30),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: Container(
        padding: const EdgeInsets.all(16),
        decoration: const BoxDecoration(
          color: AppTheme.surface,
          border: Border(top: BorderSide(color: AppTheme.line)),
        ),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 46),
                  side: const BorderSide(color: AppTheme.primary, width: 1.5),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                ),
                onPressed: _product.stock > 0 ? () => _addToCart(navigateToCheckout: false) : null,
                child: const Text('Add to Cart', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(minimumSize: const Size(0, 46)),
                onPressed: _product.stock > 0 ? () => _addToCart(navigateToCheckout: true) : null,
                child: const Text('Buy Now'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
