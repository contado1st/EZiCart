import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/category_model.dart';
import '../../models/product_model.dart';
import '../../providers/auth_provider.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import 'cart_page.dart';
import 'categories_page.dart';
import 'addresses_page.dart';
import 'for_you_page.dart';
import 'messages_page.dart';
import 'orders_page.dart';
import 'product_details_page.dart';
import 'profile_page.dart';
import 'search_page.dart';
import 'stock_watchlist_page.dart';
import 'vouchers_page.dart';
import 'notifications_page.dart';

class BuyerDashboard extends StatefulWidget {
  const BuyerDashboard({super.key});

  @override
  State<BuyerDashboard> createState() => _BuyerDashboardState();
}

class _BuyerDashboardState extends State<BuyerDashboard> {
  int _currentIndex = 0;
  int _unreadNotificationCount = 0;
  final GlobalKey<MessagesPageState> _messagesKey = GlobalKey<MessagesPageState>();
  List<ProductModel> _products = [];
  List<CategoryModel> _categories = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadStorefrontData();
  }

  Future<void> _loadStorefrontData() async {
    setState(() => _isLoading = true);
    await Future.wait([
      _fetchProducts(),
      _fetchCategories(),
      _fetchNotificationCount(),
    ]);
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _fetchNotificationCount() async {
    try {
      final res = await ApiService.get(ApiConfig.notificationsUnreadCountEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['unread_count'] is int && mounted) {
          setState(() => _unreadNotificationCount = body['unread_count']);
        }
      }
    } catch (_) {}
  }

  Future<void> _fetchProducts() async {
    try {
      // Daily discoveries shows random products on each load/refresh
      final uri = Uri.parse(ApiConfig.productsEndpoint).replace(queryParameters: {'sort': 'random'});
      final res = await ApiService.get(uri.toString());

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List && mounted) {
          final list = (body['data'] as List)
              .map((p) => ProductModel.fromJson(p as Map<String, dynamic>))
              .toList();
          setState(() => _products = list);
        }
      }
    } catch (_) {}
  }

  Future<void> _fetchCategories() async {
    try {
      final res = await ApiService.get(ApiConfig.categoriesEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List && mounted) {
          final list = (body['data'] as List)
              .map((c) => CategoryModel.fromJson(c as Map<String, dynamic>))
              .toList();
          setState(() => _categories = list);
          return;
        }
      }
    } catch (_) {}

    // Fallback canonical categories
    if (mounted) {
      setState(() {
        _categories = [
          CategoryModel(name: 'Pet Supplies', icon: 'pets', productCount: 0),
          CategoryModel(name: 'Electronics and Gadgets', icon: 'devices', productCount: 0),
          CategoryModel(name: "Women's Apparel", icon: 'woman', productCount: 0),
          CategoryModel(name: "Men's Apparel", icon: 'man', productCount: 0),
          CategoryModel(name: 'Kids and Baby', icon: 'child_care', productCount: 0),
          CategoryModel(name: 'Home and Garden', icon: 'yard', productCount: 0),
          CategoryModel(name: 'Sports and Outdoors', icon: 'sports_basketball', productCount: 0),
          CategoryModel(name: 'Health and Beauty', icon: 'spa', productCount: 0),
          CategoryModel(name: 'Books and Media', icon: 'menu_book', productCount: 0),
          CategoryModel(name: 'Food and Gourmet', icon: 'restaurant', productCount: 0),
          CategoryModel(name: 'Furniture and Office Equipment', icon: 'chair', productCount: 0),
          CategoryModel(name: 'Jewelry and Watches', icon: 'watch', productCount: 0),
        ];
      });
    }
  }

  void _onCategorySelected(String category) {
    _navigateToSearch(category: category);
  }

  void _navigateToSearch({String? category, String? query}) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SearchPage(initialCategory: category, initialQuery: query),
      ),
    ).then((_) => _fetchProducts());
  }

  Widget _buildHeroBanner() {
    final auth = context.watch<AuthProvider>();
    final firstName = auth.user?.firstName;
    final greeting = (firstName != null && firstName.trim().isNotEmpty)
        ? 'Good finds,\n${firstName.trim()}.'
        : 'Good finds\nstart here.';
    final featuredProduct = _products.isNotEmpty ? _products.first : null;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 6, 16, 10),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFF7F9), Color(0xFFFFEEF3), Color(0xFFFFD4E2)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFFDDE7)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFBE2854).withValues(alpha: 0.08),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          // Background content row
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 6, 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                // Left copy
                Expanded(
                  flex: 6,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'THE EZICART MARKETPLACE',
                        style: TextStyle(
                          fontSize: 9.5,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 1.0,
                          color: Color(0xFFBE2854),
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        greeting,
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF1D2129),
                          height: 1.15,
                          letterSpacing: -0.4,
                        ),
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'Browse approved, in-stock products from EZiCart sellers. Compare details and prices before you choose.',
                        style: TextStyle(
                          fontSize: 10.5,
                          color: Color(0xFF5A6270),
                          height: 1.3,
                        ),
                      ),
                      const SizedBox(height: 12),
                      InkWell(
                        onTap: () => _navigateToSearch(),
                        borderRadius: BorderRadius.circular(4),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                          decoration: BoxDecoration(
                            color: const Color(0xFFE26A7A),
                            borderRadius: BorderRadius.circular(4),
                            boxShadow: [
                              BoxShadow(
                                color: const Color(0xFFE26A7A).withValues(alpha: 0.35),
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'EXPLORE PRODUCTS',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 9.5,
                                  fontWeight: FontWeight.bold,
                                  letterSpacing: 0.8,
                                ),
                              ),
                              SizedBox(width: 4),
                              Icon(Icons.south_east, color: Colors.white, size: 11),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(width: 4),

                // Right scooter asset
                Expanded(
                  flex: 5,
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: Image.asset(
                      'assets/images/hero-asset.png',
                      height: 170,
                      fit: BoxFit.contain,
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Floating "Recently listed" pill at bottom-right
          if (featuredProduct != null)
            Positioned(
              right: 12,
              bottom: 10,
              child: InkWell(
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ProductDetailsPage(product: featuredProduct),
                    ),
                  );
                },
                borderRadius: BorderRadius.circular(6),
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 165),
                  padding: const EdgeInsets.all(5),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.95),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.pink.shade100),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.08),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (featuredProduct.imagePath != null &&
                          ApiConfig.resolveImageUrl(featuredProduct.imagePath) != null)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: CachedNetworkImage(
                            imageUrl: ApiConfig.resolveImageUrl(featuredProduct.imagePath)!,
                            width: 28,
                            height: 28,
                            fit: BoxFit.cover,
                            errorWidget: (_, __, ___) => const Icon(Icons.inventory_2, size: 16, color: AppTheme.muted),
                          ),
                        ),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Text(
                              'Recently listed',
                              style: TextStyle(fontSize: 8.5, color: AppTheme.muted, height: 1.1),
                            ),
                            Text(
                              featuredProduct.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: AppTheme.ink),
                            ),
                            Text(
                              '₱${featuredProduct.price.toStringAsFixed(2)}',
                              style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFFBE2854)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildStorefront() {
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);
    final cart = context.read<CartProvider>();

    return RefreshIndicator(
      onRefresh: _loadStorefrontData,
      child: CustomScrollView(
        slivers: [
          // Single Clean Search Header Bar
          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
              child: InkWell(
                onTap: () => _navigateToSearch(),
                borderRadius: BorderRadius.circular(24),
                child: Container(
                  height: 44,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade100,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: AppTheme.line),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.search, size: 22, color: AppTheme.primary),
                      const SizedBox(width: 10),
                      const Expanded(
                        child: Text(
                          'Search products, shops, Laguna merchants...',
                          style: TextStyle(color: AppTheme.muted, fontSize: 13),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppTheme.primary,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Text(
                          'Search',
                          style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          // Web-Style Hero Banner with hero-asset.png
          SliverToBoxAdapter(
            child: _buildHeroBanner(),
          ),

          // Quick Action Shortcut Tiles
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              child: Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const VouchersPage()),
                        );
                      },
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        decoration: BoxDecoration(
                          color: Colors.indigo.shade50,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.indigo.shade100),
                        ),
                        child: Column(
                          children: [
                            Icon(Icons.confirmation_number_outlined, color: Colors.indigo.shade800, size: 22),
                            const SizedBox(height: 4),
                            Text(
                              'Vouchers',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.indigo.shade900),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const ForYouPage()),
                        );
                      },
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        decoration: BoxDecoration(
                          color: Colors.teal.shade50,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.teal.shade100),
                        ),
                        child: Column(
                          children: [
                            Icon(Icons.auto_awesome_outlined, color: Colors.teal.shade800, size: 22),
                            const SizedBox(height: 4),
                            Text(
                              'Deals For You',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.teal.shade900),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const StockWatchlistPage()),
                        );
                      },
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        decoration: BoxDecoration(
                          color: Colors.amber.shade50,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.amber.shade200),
                        ),
                        child: Column(
                          children: [
                            Icon(Icons.notifications_active_outlined, color: Colors.amber.shade900, size: 22),
                            const SizedBox(height: 4),
                            Text(
                              'Alerts',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.amber.shade900),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const AddressesPage()),
                        );
                      },
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        decoration: BoxDecoration(
                          color: Colors.orange.shade50,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.orange.shade200),
                        ),
                        child: Column(
                          children: [
                            Icon(Icons.pin_drop_outlined, color: Colors.orange.shade900, size: 22),
                            const SizedBox(height: 4),
                            Text(
                              'Addresses',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.orange.shade900),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Shopee-style Category Showcase Grid
          SliverToBoxAdapter(
            child: Container(
              margin: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.line),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Categories',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.ink),
                      ),
                      InkWell(
                        onTap: () => setState(() => _currentIndex = 1),
                        borderRadius: BorderRadius.circular(4),
                        child: const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                          child: Row(
                            children: [
                              Text('See All', style: TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.w600)),
                              Icon(Icons.chevron_right, size: 16, color: AppTheme.primary),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  // Horizontal 2-row scrollable grid of categories
                  SizedBox(
                    height: 180,
                    child: GridView.builder(
                      scrollDirection: Axis.horizontal,
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        childAspectRatio: 0.95,
                        mainAxisSpacing: 10,
                        crossAxisSpacing: 10,
                      ),
                      itemCount: _categories.length,
                      itemBuilder: (context, i) {
                        final cat = _categories[i];
                        final iconData = CategoryModel.getIcon(cat.name);
                        final iconColor = CategoryModel.getColor(cat.name);

                        return InkWell(
                          onTap: () => _navigateToSearch(category: cat.name),
                          borderRadius: BorderRadius.circular(10),
                          child: Container(
                            padding: const EdgeInsets.all(6),
                            decoration: BoxDecoration(
                              color: Colors.grey.shade50,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: Colors.grey.shade200),
                            ),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(8),
                                  decoration: BoxDecoration(
                                    color: iconColor.withValues(alpha: 0.12),
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(iconData, color: iconColor, size: 20),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  cat.name,
                                  textAlign: TextAlign.center,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w600,
                                    color: AppTheme.ink,
                                    height: 1.1,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Daily Discoveries Section Title (Random items on each load)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Daily Discoveries',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
                  ),
                  Text(
                    '${_products.length} Products',
                    style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                  ),
                ],
              ),
            ),
          ),

          // Product Grid
          if (_isLoading)
            const SliverFillRemaining(
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_products.isEmpty)
            SliverFillRemaining(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.search_off_outlined, size: 56, color: Colors.grey.shade400),
                    const SizedBox(height: 12),
                    const Text('No products found', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    const SizedBox(height: 4),
                    const Text('Pull down to refresh and discover new items.', style: TextStyle(color: AppTheme.muted, fontSize: 13)),
                  ],
                ),
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  childAspectRatio: 0.61,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                ),
                delegate: SliverChildBuilderDelegate(
                  (context, i) {
                    final product = _products[i];
                    return Card(
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                        side: const BorderSide(color: AppTheme.line),
                      ),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => ProductDetailsPage(product: product),
                            ),
                          ).then((_) => _fetchProducts());
                        },
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Product Image
                            Expanded(
                              flex: 11,
                              child: ClipRRect(
                                borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                                child: Stack(
                                  fit: StackFit.expand,
                                  children: [
                                    Container(
                                      color: Colors.grey.shade100,
                                      child: ApiConfig.resolveImageUrl(product.imagePath) != null
                                          ? CachedNetworkImage(
                                              imageUrl: ApiConfig.resolveImageUrl(product.imagePath)!,
                                              fit: BoxFit.cover,
                                              errorWidget: (_, __, ___) => const Center(
                                                child: Icon(Icons.inventory_2_outlined, color: AppTheme.muted),
                                              ),
                                            )
                                          : const Center(
                                              child: Icon(Icons.inventory_2_outlined, color: AppTheme.muted),
                                            ),
                                    ),
                                    if (product.category.isNotEmpty)
                                      Positioned(
                                        top: 6,
                                        left: 6,
                                        child: Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: Colors.black.withValues(alpha: 0.6),
                                            borderRadius: BorderRadius.circular(4),
                                          ),
                                          child: Text(
                                            product.category,
                                            style: const TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w500),
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),

                            // Product details
                            Expanded(
                              flex: 10,
                              child: Padding(
                                padding: const EdgeInsets.all(10),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          product.sellerName,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.w600),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          product.name,
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, height: 1.2),
                                        ),
                                      ],
                                    ),
                                    Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                          crossAxisAlignment: CrossAxisAlignment.center,
                                          children: [
                                            Expanded(
                                              child: Text(
                                                currency.format(product.price),
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                                style: const TextStyle(
                                                  fontSize: 14,
                                                  fontWeight: FontWeight.bold,
                                                  color: AppTheme.primary,
                                                ),
                                              ),
                                            ),
                                            IconButton(
                                              padding: EdgeInsets.zero,
                                              constraints: const BoxConstraints(),
                                              icon: const Icon(Icons.add_shopping_cart, size: 20, color: AppTheme.primary),
                                              onPressed: () {
                                                cart.addItem(product);
                                                ScaffoldMessenger.of(context).showSnackBar(
                                                  SnackBar(
                                                    content: Text('Added ${product.name} to cart!'),
                                                    duration: const Duration(seconds: 1),
                                                    backgroundColor: AppTheme.primary,
                                                  ),
                                                );
                                              },
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 3),
                                        Row(
                                          children: [
                                            if (product.reviewsCount > 0 && product.rating > 0.0) ...[
                                              const Icon(Icons.star, color: Colors.amber, size: 12),
                                              const SizedBox(width: 2),
                                              Text(
                                                product.rating.toStringAsFixed(1),
                                                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
                                              ),
                                            ] else ...[
                                              const Icon(Icons.star_border, color: AppTheme.muted, size: 12),
                                              const SizedBox(width: 2),
                                              const Text('0.0', style: TextStyle(fontSize: 10, color: AppTheme.muted)),
                                            ],
                                            const SizedBox(width: 6),
                                            Text(
                                              product.salesCount > 0 ? '${product.salesCount} sold' : '0 sold',
                                              style: const TextStyle(fontSize: 10, color: AppTheme.muted),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                  childCount: _products.length,
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

    final pages = [
      _buildStorefront(),
      CategoriesPage(onSelectCategory: _onCategorySelected),
      const OrdersPage(),
      MessagesPage(key: _messagesKey),
      const ProfilePage(),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: AppTheme.primarySoft,
                borderRadius: BorderRadius.circular(6),
              ),
              child: const Text('EZ', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 14)),
            ),
            const SizedBox(width: 8),
            const Text('EZiCart', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          ],
        ),
        actions: [
          Stack(
            children: [
              IconButton(
                icon: const Icon(Icons.notifications_outlined),
                onPressed: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const NotificationsPage()),
                  );
                  _fetchNotificationCount();
                },
              ),
              if (_unreadNotificationCount > 0)
                Positioned(
                  right: 8,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: Colors.redAccent,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '$_unreadNotificationCount',
                      style: const TextStyle(fontSize: 10, color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
            ],
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
      body: IndexedStack(
        index: _currentIndex,
        children: pages,
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) {
          setState(() => _currentIndex = index);
          if (index == 3) {
            _messagesKey.currentState?.fetchThreads();
          }
        },
        type: BottomNavigationBarType.fixed,
        selectedItemColor: AppTheme.primary,
        unselectedItemColor: AppTheme.muted,
        selectedFontSize: 11,
        unselectedFontSize: 11,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.storefront_outlined), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(Icons.category_outlined), label: 'Categories'),
          BottomNavigationBarItem(icon: Icon(Icons.receipt_long_outlined), label: 'Orders'),
          BottomNavigationBarItem(icon: Icon(Icons.chat_bubble_outline), label: 'Messages'),
          BottomNavigationBarItem(icon: Icon(Icons.person_outline), label: 'Profile'),
        ],
      ),
    );
  }
}
