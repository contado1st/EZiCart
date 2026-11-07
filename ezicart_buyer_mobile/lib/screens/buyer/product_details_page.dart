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

  final PageController _pageController = PageController();
  int _currentImageIndex = 0;

  @override
  void initState() {
    super.initState();
    _product = widget.product;
    _selectedVariation = null;
    _loadFreshDetails();
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  List<String> get _displayedImages {
    final images = _product.allImages
        .where((img) => img.trim().isNotEmpty)
        .toList();
    if (_selectedVariation?.imagePath != null && _selectedVariation!.imagePath!.trim().isNotEmpty) {
      final varImg = _selectedVariation!.imagePath!.trim();
      if (!images.contains(varImg)) {
        images.insert(0, varImg);
      }
    }
    return images;
  }

  int get _currentStock {
    if (_selectedVariation != null) {
      return _selectedVariation!.stock;
    }
    return _product.stock;
  }

  double get _currentUnitPrice {
    return _product.price + (_selectedVariation?.priceAdjustment ?? 0.0);
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
            if (_product.variations.isNotEmpty && _selectedVariation != null) {
              final match = _product.variations.where((v) => v.id == _selectedVariation!.id);
              if (match.isNotEmpty) {
                _selectedVariation = match.first;
              } else {
                _selectedVariation = null;
              }
            }
          });
        }
      }
    } catch (_) {}
  }

  void _onVariationSelected(ProductVariation variation) {
    setState(() {
      if (_selectedVariation?.id == variation.id) {
        _selectedVariation = null;
      } else {
        _selectedVariation = variation;
        if (_quantity > variation.stock && variation.stock > 0) {
          _quantity = variation.stock;
        }
      }
    });

    // Animate preview to variation image if present
    if (variation.imagePath != null && variation.imagePath!.isNotEmpty) {
      final images = _displayedImages;
      final idx = images.indexOf(variation.imagePath!);
      if (idx != -1 && _pageController.hasClients) {
        _pageController.animateToPage(
          idx,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeInOut,
        );
      }
    }
  }

  void _openFullScreenImageViewer(int initialIndex) {
    final images = _displayedImages;
    if (images.isEmpty) return;

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => FullScreenImageViewer(
          images: images,
          initialIndex: initialIndex,
          productTitle: _product.name,
        ),
      ),
    );
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

    if (_currentStock <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Selected option is currently out of stock.')),
      );
      return;
    }

    final cart = context.read<CartProvider>();
    final addedQty = _quantity;
    final addedName = _product.name;
    cart.addItem(_product, variation: _selectedVariation, quantity: _quantity);

    if (navigateToCheckout) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const CheckoutPage()),
      );
    } else {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Added $addedQty × $addedName to your cart.'),
            backgroundColor: AppTheme.primary,
            behavior: SnackBarBehavior.floating,
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
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final currencyFormat = NumberFormat.currency(locale: 'en_PH', symbol: '₱');
    final images = _displayedImages;

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
          Consumer<CartProvider>(
            builder: (context, cart, _) => Stack(
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
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Product Image Carousel with Navigation Buttons
              Stack(
                children: [
                  AspectRatio(
                    aspectRatio: 1.22,
                    child: Container(
                      color: Colors.grey.shade100,
                      child: images.isNotEmpty
                          ? PageView.builder(
                              controller: _pageController,
                              itemCount: images.length,
                              onPageChanged: (idx) {
                                setState(() => _currentImageIndex = idx);
                              },
                              itemBuilder: (context, idx) {
                                final resolved = ApiConfig.resolveImageUrl(images[idx]);
                                return GestureDetector(
                                  onTap: () => _openFullScreenImageViewer(idx),
                                  child: resolved != null
                                      ? CachedNetworkImage(
                                          imageUrl: resolved,
                                          fit: BoxFit.cover,
                                          placeholder: (_, __) => const Center(
                                            child: SizedBox(
                                              width: 32,
                                              height: 32,
                                              child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.primary),
                                            ),
                                          ),
                                          errorWidget: (_, __, ___) => const Center(
                                            child: Icon(Icons.inventory_2_outlined, size: 64, color: AppTheme.muted),
                                          ),
                                        )
                                      : const Center(
                                          child: Icon(Icons.inventory_2_outlined, size: 64, color: AppTheme.muted),
                                        ),
                                );
                              },
                            )
                          : const Center(
                              child: Icon(Icons.inventory_2_outlined, size: 64, color: AppTheme.muted),
                            ),
                    ),
                  ),

                  // Left navigation button
                  if (images.length > 1 && _currentImageIndex > 0)
                    Positioned(
                      left: 10,
                      top: 0,
                      bottom: 0,
                      child: Center(
                        child: Material(
                          color: Colors.black.withValues(alpha: 0.35),
                          shape: const CircleBorder(),
                          child: InkWell(
                            customBorder: const CircleBorder(),
                            onTap: () {
                              _pageController.previousPage(
                                duration: const Duration(milliseconds: 250),
                                curve: Curves.easeInOut,
                              );
                            },
                            child: const Padding(
                              padding: EdgeInsets.all(8),
                              child: Icon(Icons.chevron_left, color: Colors.white, size: 28),
                            ),
                          ),
                        ),
                      ),
                    ),

                  // Right navigation button
                  if (images.length > 1 && _currentImageIndex < images.length - 1)
                    Positioned(
                      right: 10,
                      top: 0,
                      bottom: 0,
                      child: Center(
                        child: Material(
                          color: Colors.black.withValues(alpha: 0.35),
                          shape: const CircleBorder(),
                          child: InkWell(
                            customBorder: const CircleBorder(),
                            onTap: () {
                              _pageController.nextPage(
                                duration: const Duration(milliseconds: 250),
                                curve: Curves.easeInOut,
                              );
                            },
                            child: const Padding(
                              padding: EdgeInsets.all(8),
                              child: Icon(Icons.chevron_right, color: Colors.white, size: 28),
                            ),
                          ),
                        ),
                      ),
                    ),

                  // Image counter pill badge (e.g. 1 / 4) & Tap to zoom icon
                  if (images.isNotEmpty)
                    Positioned(
                      bottom: 12,
                      right: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.6),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.fullscreen, color: Colors.white70, size: 14),
                            const SizedBox(width: 4),
                            Text(
                              '${_currentImageIndex + 1} / ${images.length}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),

              // Thumbnail Bar (if multiple images)
              if (images.length > 1)
                Container(
                  height: 68,
                  padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
                  color: AppTheme.surface,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: images.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 10),
                    itemBuilder: (context, idx) {
                      final isSelected = _currentImageIndex == idx;
                      final resolved = ApiConfig.resolveImageUrl(images[idx]);
                      return GestureDetector(
                        onTap: () {
                          _pageController.animateToPage(
                            idx,
                            duration: const Duration(milliseconds: 250),
                            curve: Curves.easeInOut,
                          );
                        },
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          width: 52,
                          height: 52,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: isSelected ? AppTheme.primary : AppTheme.line,
                              width: isSelected ? 2.5 : 1.0,
                            ),
                          ),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: resolved != null
                                ? CachedNetworkImage(
                                    imageUrl: resolved,
                                    fit: BoxFit.cover,
                                    errorWidget: (_, __, ___) => const Icon(Icons.broken_image, size: 18),
                                  )
                                : const Icon(Icons.image, size: 18),
                          ),
                        ),
                      );
                    },
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

                    // Price & Dynamic Stock Display
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
                        const SizedBox(width: 14),
                        // Live stock updates based on selected variation
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: _currentStock > 0 ? Colors.green.shade50 : Colors.red.shade50,
                            borderRadius: BorderRadius.circular(4),
                            border: Border.all(
                              color: _currentStock > 0 ? Colors.green.shade200 : Colors.red.shade200,
                            ),
                          ),
                          child: Text(
                            _currentStock > 0 ? '$_currentStock in stock' : 'Out of stock',
                            style: TextStyle(
                              color: _currentStock > 0 ? Colors.green.shade800 : Colors.red.shade700,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
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

                    // Variations with preview images
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
                          final isOutOfStock = v.stock <= 0;

                          return ChoiceChip(
                            label: Text(
                              '${v.type}: ${v.value}${v.priceAdjustment != 0 ? " (+${currencyFormat.format(v.priceAdjustment)})" : ""}${isOutOfStock ? " (Out of stock)" : ""}',
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
                                : (_) => _onVariationSelected(v),
                          );
                        }).toList(),
                      ),
                      if (_selectedVariation != null) ...[
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: AppTheme.primarySoft.withValues(alpha: 0.35),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.primary.withValues(alpha: 0.3)),
                          ),
                          child: Row(
                            children: [
                              if (_selectedVariation!.imagePath != null &&
                                  _selectedVariation!.imagePath!.trim().isNotEmpty &&
                                  ApiConfig.resolveImageUrl(_selectedVariation!.imagePath) != null) ...[
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(6),
                                  child: CachedNetworkImage(
                                    imageUrl: ApiConfig.resolveImageUrl(_selectedVariation!.imagePath)!,
                                    width: 48,
                                    height: 48,
                                    fit: BoxFit.cover,
                                    placeholder: (_, __) => const SizedBox(
                                      width: 48,
                                      height: 48,
                                      child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                                    ),
                                    errorWidget: (_, __, ___) => Container(
                                      width: 48,
                                      height: 48,
                                      color: Colors.grey.shade200,
                                      child: const Icon(Icons.image_not_supported_outlined, size: 22, color: AppTheme.muted),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                              ],
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Selected: ${_selectedVariation!.type} - ${_selectedVariation!.value}',
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.primary),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      _selectedVariation!.stock > 0
                                          ? '${_selectedVariation!.stock} pieces available'
                                          : 'Currently out of stock',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: _selectedVariation!.stock > 0 ? AppTheme.muted : Colors.red,
                                        fontWeight: _selectedVariation!.stock > 0 ? FontWeight.normal : FontWeight.bold,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              InkWell(
                                onTap: () => setState(() => _selectedVariation = null),
                                child: const Padding(
                                  padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  child: Text('Clear', style: TextStyle(fontSize: 12, color: AppTheme.muted, fontWeight: FontWeight.bold)),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
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
                                onPressed: _quantity < _currentStock && _quantity < 20
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
                onPressed: _currentStock > 0 ? () => _addToCart(navigateToCheckout: false) : null,
                child: const Text('Add to Cart', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(minimumSize: const Size(0, 46)),
                onPressed: _currentStock > 0 ? () => _addToCart(navigateToCheckout: true) : null,
                child: const Text('Buy Now'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class FullScreenImageViewer extends StatefulWidget {
  final List<String> images;
  final int initialIndex;
  final String productTitle;

  const FullScreenImageViewer({
    super.key,
    required this.images,
    required this.initialIndex,
    required this.productTitle,
  });

  @override
  State<FullScreenImageViewer> createState() => _FullScreenImageViewerState();
}

class _FullScreenImageViewerState extends State<FullScreenImageViewer> {
  late PageController _pageController;
  late int _currentIndex;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
    _pageController = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        iconTheme: const IconThemeData(color: Colors.white),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.productTitle,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: 15),
            ),
            Text(
              '${_currentIndex + 1} of ${widget.images.length}',
              style: const TextStyle(color: Colors.white70, fontSize: 12),
            ),
          ],
        ),
      ),
      body: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            itemCount: widget.images.length,
            onPageChanged: (idx) {
              setState(() => _currentIndex = idx);
            },
            itemBuilder: (context, idx) {
              final resolved = ApiConfig.resolveImageUrl(widget.images[idx]);
              return Center(
                child: InteractiveViewer(
                  minScale: 1.0,
                  maxScale: 4.0,
                  child: resolved != null
                      ? CachedNetworkImage(
                          imageUrl: resolved,
                          fit: BoxFit.contain,
                          placeholder: (_, __) => const Center(
                            child: CircularProgressIndicator(color: AppTheme.primary),
                          ),
                          errorWidget: (_, __, ___) => const Center(
                            child: Icon(Icons.broken_image, size: 64, color: Colors.white38),
                          ),
                        )
                      : const Center(
                          child: Icon(Icons.broken_image, size: 64, color: Colors.white38),
                        ),
                ),
              );
            },
          ),

          // Left button
          if (widget.images.length > 1 && _currentIndex > 0)
            Positioned(
              left: 16,
              top: 0,
              bottom: 0,
              child: Center(
                child: IconButton(
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.black45,
                  ),
                  icon: const Icon(Icons.chevron_left, color: Colors.white, size: 36),
                  onPressed: () {
                    _pageController.previousPage(
                      duration: const Duration(milliseconds: 250),
                      curve: Curves.easeInOut,
                    );
                  },
                ),
              ),
            ),

          // Right button
          if (widget.images.length > 1 && _currentIndex < widget.images.length - 1)
            Positioned(
              right: 16,
              top: 0,
              bottom: 0,
              child: Center(
                child: IconButton(
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.black45,
                  ),
                  icon: const Icon(Icons.chevron_right, color: Colors.white, size: 36),
                  onPressed: () {
                    _pageController.nextPage(
                      duration: const Duration(milliseconds: 250),
                      curve: Curves.easeInOut,
                    );
                  },
                ),
              ),
            ),
        ],
      ),
    );
  }
}
