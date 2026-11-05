import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/category_model.dart';
import '../../models/product_model.dart';
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
import 'stock_watchlist_page.dart';
import 'vouchers_page.dart';

class BuyerDashboard extends StatefulWidget {
  const BuyerDashboard({super.key});

  @override
  State<BuyerDashboard> createState() => _BuyerDashboardState();
}

class _BuyerDashboardState extends State<BuyerDashboard> {
  int _currentIndex = 0;
  List<ProductModel> _products = [];
  List<CategoryModel> _categories = [];
  String _selectedCategory = 'All';
  String _searchKeyword = '';
  bool _isLoading = true;
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadStorefrontData();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadStorefrontData() async {
    setState(() => _isLoading = true);
    await Future.wait([
      _fetchProducts(),
      _fetchCategories(),
    ]);
    setState(() => _isLoading = false);
  }

  Future<void> _fetchProducts() async {
    try {
      final queryParams = <String, String>{};
      if (_selectedCategory != 'All') queryParams['category'] = _selectedCategory;
      if (_searchKeyword.isNotEmpty) queryParams['search'] = _searchKeyword;

      final uri = Uri.parse(ApiConfig.productsEndpoint).replace(queryParameters: queryParams.isNotEmpty ? queryParams : null);
      final res = await ApiService.get(uri.toString());

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
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
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((c) => CategoryModel.fromJson(c as Map<String, dynamic>))
              .toList();
          setState(() => _categories = list);
        }
      }
    } catch (_) {}
  }

  void _onCategorySelected(String category) {
    setState(() {
      _selectedCategory = category;
      _currentIndex = 0;
    });
    _fetchProducts();
  }

  Widget _buildStorefront() {
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);
    final cart = context.read<CartProvider>();

    return RefreshIndicator(
      onRefresh: _loadStorefrontData,
      child: CustomScrollView(
        slivers: [
          // Search & Filter header
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: TextField(
                controller: _searchCtrl,
                decoration: InputDecoration(
                  hintText: 'Search products, sellers, or categories...',
                  prefixIcon: const Icon(Icons.search, size: 20, color: AppTheme.muted),
                  suffixIcon: _searchCtrl.text.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear, size: 18),
                          onPressed: () {
                            _searchCtrl.clear();
                            setState(() => _searchKeyword = '');
                            _fetchProducts();
                          },
                        )
                      : null,
                ),
                onSubmitted: (val) {
                  setState(() => _searchKeyword = val.trim());
                  _fetchProducts();
                },
              ),
            ),
          ),

          // Promo Banner
          SliverToBoxAdapter(
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppTheme.primary, AppTheme.primaryHover],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Doorstep Delivery',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Direct from local Laguna and NCR merchants with Cash on Delivery.',
                          style: TextStyle(color: Colors.white70, fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.local_shipping_outlined, color: Colors.white, size: 28),
                  ),
                ],
              ),
            ),
          ),

          // Buyer Feature Shortcut Tiles
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
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
                              'For You',
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
          const SliverToBoxAdapter(child: SizedBox(height: 8)),

          // Horizontal Category Chips
          SliverToBoxAdapter(
            child: SizedBox(
              height: 44,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  ChoiceChip(
                    label: const Text('All'),
                    selected: _selectedCategory == 'All',
                    selectedColor: AppTheme.primary,
                    backgroundColor: AppTheme.surface,
                    labelStyle: TextStyle(
                      color: _selectedCategory == 'All' ? Colors.white : AppTheme.ink,
                      fontWeight: _selectedCategory == 'All' ? FontWeight.bold : FontWeight.normal,
                      fontSize: 12,
                    ),
                    onSelected: (_) => _onCategorySelected('All'),
                  ),
                  ..._categories.map((c) {
                    final isSel = _selectedCategory == c.name;
                    return Padding(
                      padding: const EdgeInsets.only(left: 8),
                      child: ChoiceChip(
                        label: Text(c.name),
                        selected: isSel,
                        selectedColor: AppTheme.primary,
                        backgroundColor: AppTheme.surface,
                        labelStyle: TextStyle(
                          color: isSel ? Colors.white : AppTheme.ink,
                          fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                          fontSize: 12,
                        ),
                        onSelected: (_) => _onCategorySelected(c.name),
                      ),
                    );
                  }),
                ],
              ),
            ),
          ),

          // Section Title
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    _selectedCategory == 'All' ? 'Featured Marketplace Items' : _selectedCategory,
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
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
                    const Text('Try adjusting your search or category filter.', style: TextStyle(color: AppTheme.muted, fontSize: 13)),
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
                  childAspectRatio: 0.68,
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
                                child: Container(
                                  width: double.infinity,
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
                              ),
                            ),

                            // Product details
                            Expanded(
                              flex: 9,
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
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      crossAxisAlignment: CrossAxisAlignment.center,
                                      children: [
                                        Text(
                                          currency.format(product.price),
                                          style: const TextStyle(
                                            fontSize: 14,
                                            fontWeight: FontWeight.bold,
                                            color: AppTheme.primary,
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
      const MessagesPage(),
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
        onTap: (index) => setState(() => _currentIndex = index),
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
