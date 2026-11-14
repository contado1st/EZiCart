// ignore_for_file: deprecated_member_use
import 'dart:async';
import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/product_model.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import 'product_details_page.dart';
import 'shop_page.dart';

enum SearchMode { history, suggestions, results }

class SearchPage extends StatefulWidget {
  final String? initialQuery;
  final String? initialCategory;

  const SearchPage({
    super.key,
    this.initialQuery,
    this.initialCategory,
  });

  @override
  State<SearchPage> createState() => _SearchPageState();
}

class _SearchPageState extends State<SearchPage> {
  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();
  final TextEditingController _searchCtrl = TextEditingController();
  final FocusNode _focusNode = FocusNode();
  final NumberFormat _currencyFormat = NumberFormat.currency(locale: 'en_PH', symbol: '₱');

  SearchMode _mode = SearchMode.history;
  Timer? _debounceTimer;

  // Recent searches
  List<String> _recentSearches = [];
  static const String _historyKey = 'buyer_recent_searches';

  // Trending / popular searches
  final List<String> _trendingSearches = [
    'Electronics and Gadgets',
    'Wireless Earbuds',
    'Pet Supplies',
    'Women\'s Apparel',
    'Home and Garden',
    'Food and Gourmet',
    'Laguna Delicacies',
    'Shoes',
  ];

  // Canonical categories for filter drawer
  final List<String> _canonicalCategories = [
    'All',
    'Pet Supplies',
    'Electronics and Gadgets',
    'Women\'s Apparel',
    'Men\'s Apparel',
    'Kids and Baby',
    'Home and Garden',
    'Sports and Outdoors',
    'Health and Beauty',
    'Books and Media',
    'Food and Gourmet',
    'Furniture and Office Equipment',
    'Jewelry and Watches',
  ];

  // Suggestions state
  bool _isSuggestionsLoading = false;
  List<Map<String, dynamic>> _productSuggestions = [];
  List<Map<String, dynamic>> _shopSuggestions = [];

  // Results state
  bool _isResultsLoading = false;
  List<ProductModel> _results = [];
  List<Map<String, dynamic>> _relatedShops = [];
  String _selectedSort = 'relevance'; // relevance, latest, top_sales, price_asc, price_desc
  int _currentPage = 1;
  int _totalResults = 0;

  // Filter Drawer State
  String _selectedCategory = 'All';
  final TextEditingController _minPriceCtrl = TextEditingController();
  final TextEditingController _maxPriceCtrl = TextEditingController();
  int? _selectedRating; // null = all, 5, 4, 3, 2, 1
  String? _selectedProvince; // null = all locations
  final List<String> _availableProvinces = [
    'Laguna',
    'Metro Manila',
    'Cavite',
    'Batangas',
    'Rizal',
    'Bulacan',
    'Pampanga',
    'Quezon',
  ];

  int get _activeFilterCount {
    int count = 0;
    if (_selectedCategory != 'All') count++;
    if (_minPriceCtrl.text.trim().isNotEmpty || _maxPriceCtrl.text.trim().isNotEmpty) count++;
    if (_selectedRating != null && _selectedRating! > 0) count++;
    if (_selectedProvince != null && _selectedProvince!.isNotEmpty && _selectedProvince != 'All' && _selectedProvince != 'All locations') count++;
    return count;
  }

  bool get _hasActiveFilters => _activeFilterCount > 0;

  @override
  void initState() {
    super.initState();
    _loadRecentSearches();

    if (widget.initialCategory != null && widget.initialCategory != 'All') {
      _selectedCategory = widget.initialCategory!;
    }

    if (widget.initialQuery != null && widget.initialQuery!.trim().isNotEmpty) {
      _searchCtrl.text = widget.initialQuery!.trim();
      _executeSearch(widget.initialQuery!.trim());
    } else if (widget.initialCategory != null && widget.initialCategory != 'All') {
      _executeSearch('');
    } else {
      _mode = SearchMode.history;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _focusNode.requestFocus();
      });
    }
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    _searchCtrl.dispose();
    _minPriceCtrl.dispose();
    _maxPriceCtrl.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  // --- Search History ---
  Future<void> _loadRecentSearches() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final list = prefs.getStringList(_historyKey) ?? [];
      if (mounted) {
        setState(() => _recentSearches = list);
      }
    } catch (_) {}
  }

  Future<void> _saveSearchTerm(String term) async {
    final clean = term.trim();
    if (clean.isEmpty) return;

    try {
      final prefs = await SharedPreferences.getInstance();
      final list = List<String>.from(_recentSearches);
      list.removeWhere((item) => item.toLowerCase() == clean.toLowerCase());
      list.insert(0, clean);
      if (list.length > 15) {
        list.removeRange(15, list.length);
      }
      await prefs.setStringList(_historyKey, list);
      if (mounted) {
        setState(() => _recentSearches = list);
      }
    } catch (_) {}
  }

  Future<void> _removeSearchTerm(String term) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final list = List<String>.from(_recentSearches);
      list.removeWhere((item) => item.toLowerCase() == term.toLowerCase());
      await prefs.setStringList(_historyKey, list);
      if (mounted) {
        setState(() => _recentSearches = list);
      }
    } catch (_) {}
  }

  Future<void> _clearAllHistory() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_historyKey);
      if (mounted) {
        setState(() => _recentSearches = []);
      }
    } catch (_) {}
  }

  // --- As-You-Type Suggestions ---
  void _onQueryChanged(String query) {
    _debounceTimer?.cancel();

    if (query.trim().isEmpty) {
      setState(() {
        _mode = SearchMode.history;
        _productSuggestions = [];
        _shopSuggestions = [];
      });
      return;
    }

    setState(() {
      _mode = SearchMode.suggestions;
      _isSuggestionsLoading = true;
    });

    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      _fetchSuggestions(query.trim());
    });
  }

  Future<void> _fetchSuggestions(String query) async {
    if (query.isEmpty) return;

    try {
      final res = await ApiService.get(ApiConfig.searchSuggestionsEndpoint(query));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && mounted) {
          final rawProducts = body['products'] as List<dynamic>? ?? [];
          final rawShops = body['shops'] as List<dynamic>? ?? [];

          setState(() {
            _productSuggestions = rawProducts.map((p) => Map<String, dynamic>.from(p as Map)).toList();
            _shopSuggestions = rawShops.map((s) => Map<String, dynamic>.from(s as Map)).toList();
            _isSuggestionsLoading = false;
          });
          return;
        }
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isSuggestionsLoading = false);
    }
  }

  // --- Execution & Results ---
  void _executeSearch(String query) {
    _focusNode.unfocus();
    final clean = query.trim();

    if (clean.isNotEmpty) {
      _saveSearchTerm(clean);
    }

    setState(() {
      _mode = SearchMode.results;
      _currentPage = 1;
      _isResultsLoading = true;
    });

    _fetchResults();
  }

  Future<void> _fetchResults() async {
    setState(() => _isResultsLoading = true);

    try {
      final queryParams = <String, String>{
        'sort': _selectedSort,
        'page': '$_currentPage',
      };

      final q = _searchCtrl.text.trim();
      if (q.isNotEmpty) {
        queryParams['search'] = q;
      }

      if (_selectedCategory != 'All') {
        queryParams['category'] = _selectedCategory;
      }

      final minP = _minPriceCtrl.text.trim();
      if (minP.isNotEmpty && double.tryParse(minP) != null) {
        queryParams['min_price'] = minP;
      }

      final maxP = _maxPriceCtrl.text.trim();
      if (maxP.isNotEmpty && double.tryParse(maxP) != null) {
        queryParams['max_price'] = maxP;
      }

      if (_selectedRating != null && _selectedRating! > 0) {
        queryParams['rating'] = '$_selectedRating';
      }

      if (_selectedProvince != null &&
          _selectedProvince!.trim().isNotEmpty &&
          _selectedProvince != 'All' &&
          _selectedProvince != 'All locations') {
        queryParams['province'] = _selectedProvince!.trim();
      }

      final uri = Uri.parse(ApiConfig.productsEndpoint).replace(queryParameters: queryParams);
      final res = await ApiService.get(uri.toString());

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List && mounted) {
          final list = (body['data'] as List)
              .map((p) => ProductModel.fromJson(p as Map<String, dynamic>))
              .toList();

          List<Map<String, dynamic>> shops = [];
          if (body['related_shops'] is List) {
            shops = (body['related_shops'] as List)
                .map((s) => Map<String, dynamic>.from(s as Map))
                .toList();
          }

          if (body['provinces'] is List) {
            final fetched = (body['provinces'] as List).map((p) => p.toString()).toList();
            for (var prov in fetched) {
              if (prov.trim().isNotEmpty && !_availableProvinces.contains(prov.trim())) {
                _availableProvinces.add(prov.trim());
              }
            }
          }

          setState(() {
            _results = list;
            _relatedShops = shops;
            _currentPage = body['current_page'] ?? 1;
            _totalResults = body['total'] ?? list.length;
            _isResultsLoading = false;
          });
          return;
        }
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isResultsLoading = false);
    }
  }

  void _onSortChanged(String newSort) {
    if (_selectedSort == newSort) {
      if (newSort == 'price_asc') {
        _selectedSort = 'price_desc';
      } else if (newSort == 'price_desc') {
        _selectedSort = 'price_asc';
      }
    } else {
      _selectedSort = newSort;
    }
    _executeSearch(_searchCtrl.text);
  }

  void _resetAllFilters() {
    setState(() {
      _selectedCategory = 'All';
      _minPriceCtrl.clear();
      _maxPriceCtrl.clear();
      _selectedRating = null;
      _selectedProvince = null;
    });
  }

  void _openFilterDrawer() {
    _scaffoldKey.currentState?.openEndDrawer();
  }

  // --- Views ---

  Widget _buildSearchBar() {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(8, 8, 12, 8),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.arrow_back, color: AppTheme.ink),
            onPressed: () => Navigator.of(context).pop(),
          ),
          Expanded(
            child: Container(
              height: 42,
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(22),
                border: Border.all(color: AppTheme.line),
              ),
              child: TextField(
                controller: _searchCtrl,
                focusNode: _focusNode,
                textInputAction: TextInputAction.search,
                style: const TextStyle(fontSize: 14, color: AppTheme.ink),
                decoration: InputDecoration(
                  hintText: 'Search products or shops...',
                  hintStyle: const TextStyle(color: AppTheme.muted, fontSize: 13),
                  prefixIcon: const Icon(Icons.search, size: 20, color: AppTheme.muted),
                  suffixIcon: _searchCtrl.text.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear, size: 18, color: AppTheme.muted),
                          onPressed: () {
                            _searchCtrl.clear();
                            _onQueryChanged('');
                          },
                        )
                      : null,
                  border: InputBorder.none,
                  contentPadding: const EdgeInsets.symmetric(vertical: 10),
                ),
                onChanged: _onQueryChanged,
                onSubmitted: _executeSearch,
              ),
            ),
          ),
          const SizedBox(width: 8),
          TextButton(
            style: TextButton.styleFrom(
              backgroundColor: AppTheme.primary,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            onPressed: () => _executeSearch(_searchCtrl.text),
            child: const Text('Search', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryView() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (_recentSearches.isNotEmpty) ...[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Recent Searches',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
              ),
              InkWell(
                onTap: _clearAllHistory,
                borderRadius: BorderRadius.circular(6),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  child: Row(
                    children: [
                      Icon(Icons.delete_outline, size: 16, color: AppTheme.muted),
                      SizedBox(width: 4),
                      Text('Clear History', style: TextStyle(color: AppTheme.muted, fontSize: 12)),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _recentSearches.map((term) {
              return InputChip(
                label: Text(term, style: const TextStyle(fontSize: 12, color: AppTheme.ink)),
                backgroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: AppTheme.line),
                ),
                onPressed: () {
                  _searchCtrl.text = term;
                  _executeSearch(term);
                },
                onDeleted: () => _removeSearchTerm(term),
                deleteIconColor: AppTheme.muted,
                deleteIcon: const Icon(Icons.close, size: 14),
              );
            }).toList(),
          ),
          const SizedBox(height: 24),
        ],

        // Trending / Popular Searches
        Row(
          children: [
            Icon(Icons.local_fire_department, size: 20, color: Colors.orange.shade700),
            const SizedBox(width: 6),
            const Text(
              'Popular Discoveries',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: _trendingSearches.map((tag) {
            return ActionChip(
              avatar: const Icon(Icons.trending_up, size: 14, color: AppTheme.primary),
              label: Text(tag, style: const TextStyle(fontSize: 12, color: AppTheme.ink)),
              backgroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: BorderSide(color: Colors.grey.shade300),
              ),
              onPressed: () {
                _searchCtrl.text = tag;
                _executeSearch(tag);
              },
            );
          }).toList(),
        ),
      ],
    );
  }

  Widget _buildSuggestionsView() {
    if (_isSuggestionsLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_productSuggestions.isEmpty && _shopSuggestions.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.search_off, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 10),
            Text(
              'No suggestions for "${_searchCtrl.text}"',
              style: const TextStyle(color: AppTheme.muted, fontSize: 13),
            ),
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 8),
      children: [
        // Shop suggestions
        if (_shopSuggestions.isNotEmpty) ...[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Text(
              'SHOPS',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey.shade600, letterSpacing: 0.5),
            ),
          ),
          ..._shopSuggestions.map((shop) {
            return ListTile(
              leading: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppTheme.primarySoft,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.storefront_outlined, color: AppTheme.primary),
              ),
              title: Text(
                shop['name']?.toString() ?? 'Shop',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
              subtitle: Text(
                '${shop['location'] ?? 'Laguna, Philippines'} • ${shop['product_count'] ?? 0} products',
                style: const TextStyle(fontSize: 12, color: AppTheme.muted),
              ),
              trailing: const Icon(Icons.arrow_forward_ios, size: 14, color: AppTheme.muted),
              onTap: () {
                final sellerId = int.tryParse(shop['id'].toString());
                if (sellerId != null) {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ShopPage(sellerId: sellerId, initialShopName: shop['name']?.toString()),
                    ),
                  );
                }
              },
            );
          }),
          const Divider(height: 16),
        ],

        // Product suggestions
        if (_productSuggestions.isNotEmpty) ...[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Text(
              'PRODUCTS',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey.shade600, letterSpacing: 0.5),
            ),
          ),
          ..._productSuggestions.map((prod) {
            final imgUrl = ApiConfig.resolveImageUrl(prod['image_path']?.toString());
            final price = double.tryParse(prod['price']?.toString() ?? '0') ?? 0.0;
            final category = prod['category']?.toString() ?? '';

            return ListTile(
              leading: ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: Container(
                  width: 44,
                  height: 44,
                  color: Colors.grey.shade100,
                  child: imgUrl != null
                      ? CachedNetworkImage(
                          imageUrl: imgUrl,
                          fit: BoxFit.cover,
                          errorWidget: (_, __, ___) => const Icon(Icons.image_outlined, color: AppTheme.muted),
                        )
                      : const Icon(Icons.image_outlined, color: AppTheme.muted),
                ),
              ),
              title: Text(
                prod['name']?.toString() ?? '',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              ),
              subtitle: Row(
                children: [
                  Text(
                    _currencyFormat.format(price),
                    style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary, fontSize: 12),
                  ),
                  if (category.isNotEmpty) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade100,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        category,
                        style: const TextStyle(fontSize: 10, color: AppTheme.muted),
                      ),
                    ),
                  ],
                ],
              ),
              trailing: IconButton(
                icon: const Icon(Icons.north_west, size: 16, color: AppTheme.muted),
                onPressed: () {
                  _searchCtrl.text = prod['name']?.toString() ?? '';
                  _executeSearch(_searchCtrl.text);
                },
              ),
              onTap: () {
                final pId = int.tryParse(prod['id'].toString());
                if (pId != null) {
                  final dummyProduct = ProductModel(
                    id: pId,
                    name: prod['name']?.toString() ?? '',
                    description: '',
                    category: category,
                    price: price,
                    stock: 99,
                    imagePath: prod['image_path']?.toString(),
                    sellerName: 'Merchant',
                    variations: const [],
                  );
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ProductDetailsPage(product: dummyProduct),
                    ),
                  );
                }
              },
            );
          }),
        ],
      ],
    );
  }

  Widget _buildFilterTabs() {
    final sortTabs = [
      {'key': 'relevance', 'label': 'Relevance'},
      {'key': 'latest', 'label': 'Latest'},
      {'key': 'top_sales', 'label': 'Top Sales'},
    ];

    final isPriceActive = _selectedSort == 'price_asc' || _selectedSort == 'price_desc';

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: AppTheme.line)),
      ),
      child: Row(
        children: [
          ...sortTabs.map((t) {
            final isSel = _selectedSort == t['key'];
            return Expanded(
              child: InkWell(
                onTap: () => _onSortChanged(t['key']!),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    border: Border(
                      bottom: BorderSide(
                        color: isSel ? AppTheme.primary : Colors.transparent,
                        width: 2.5,
                      ),
                    ),
                  ),
                  child: Text(
                    t['label']!,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: isSel ? FontWeight.bold : FontWeight.w500,
                      color: isSel ? AppTheme.primary : AppTheme.ink,
                    ),
                  ),
                ),
              ),
            );
          }),
          // Price sort tab
          Expanded(
            child: InkWell(
              onTap: () {
                if (_selectedSort == 'price_asc') {
                  _onSortChanged('price_desc');
                } else {
                  _onSortChanged('price_asc');
                }
              },
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 12),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  border: Border(
                    bottom: BorderSide(
                      color: isPriceActive ? AppTheme.primary : Colors.transparent,
                      width: 2.5,
                    ),
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Price',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: isPriceActive ? FontWeight.bold : FontWeight.w500,
                        color: isPriceActive ? AppTheme.primary : AppTheme.ink,
                      ),
                    ),
                    const SizedBox(width: 2),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.arrow_drop_up,
                          size: 13,
                          color: _selectedSort == 'price_asc' ? AppTheme.primary : Colors.grey.shade400,
                        ),
                        Icon(
                          Icons.arrow_drop_down,
                          size: 13,
                          color: _selectedSort == 'price_desc' ? AppTheme.primary : Colors.grey.shade400,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
          // Filter sliding tab button
          InkWell(
            onTap: _openFilterDrawer,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              decoration: const BoxDecoration(
                border: Border(left: BorderSide(color: AppTheme.line)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Filter',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: _hasActiveFilters ? FontWeight.bold : FontWeight.w600,
                      color: _hasActiveFilters ? AppTheme.primary : AppTheme.ink,
                    ),
                  ),
                  const SizedBox(width: 4),
                  Icon(
                    Icons.filter_alt_outlined,
                    size: 15,
                    color: _hasActiveFilters ? AppTheme.primary : AppTheme.ink,
                  ),
                  if (_activeFilterCount > 0) ...[
                    const SizedBox(width: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                      decoration: const BoxDecoration(
                        color: AppTheme.primary,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        '$_activeFilterCount',
                        style: const TextStyle(fontSize: 9.5, color: Colors.white, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRelatedShopBanner(Map<String, dynamic> shop) {
    final sellerId = int.tryParse(shop['id']?.toString() ?? '0') ?? 0;
    final shopName = shop['name']?.toString() ?? shop['business_name']?.toString() ?? 'Merchant';
    final location = shop['location']?.toString() ?? 'Laguna, Philippines';
    final rating = double.tryParse(shop['rating']?.toString() ?? '0.0') ?? 0.0;
    final productCount = int.tryParse(shop['product_count']?.toString() ?? '0') ?? 0;
    final reviewCount = int.tryParse(shop['review_count']?.toString() ?? '0') ?? 0;

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 12, 14, 4),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.orange.shade200),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFF9800), Color(0xFFF57C00)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(24),
            ),
            child: const Icon(Icons.storefront, color: Colors.white, size: 26),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        shopName,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: AppTheme.ink),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(4),
                        border: Border.all(color: Colors.red.shade200),
                      ),
                      child: Text(
                        'Shop',
                        style: TextStyle(color: Colors.red.shade700, fontSize: 9.5, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Row(
                  children: [
                    if (rating > 0.0) ...[
                      const Icon(Icons.star, color: Colors.amber, size: 14),
                      const SizedBox(width: 2),
                      Text(
                        rating.toStringAsFixed(1),
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                      if (reviewCount > 0)
                        Text(
                          ' ($reviewCount)',
                          style: const TextStyle(fontSize: 10, color: AppTheme.muted),
                        ),
                    ] else ...[
                      const Icon(Icons.star_border, color: AppTheme.muted, size: 14),
                      const SizedBox(width: 2),
                      const Text(
                        '0.0 (No ratings yet)',
                        style: TextStyle(fontSize: 11, color: AppTheme.muted),
                      ),
                    ],
                    const SizedBox(width: 6),
                    Text(
                      '• $productCount Products',
                      style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                    ),
                  ],
                ),
                Text(
                  location,
                  style: const TextStyle(fontSize: 10.5, color: AppTheme.muted),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            style: OutlinedButton.styleFrom(
              foregroundColor: AppTheme.primary,
              side: const BorderSide(color: AppTheme.primary),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            onPressed: () {
              if (sellerId > 0) {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ShopPage(sellerId: sellerId, initialShopName: shopName),
                  ),
                );
              }
            },
            child: const Text('Visit Shop', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildProductCard(ProductModel product, BuildContext context) {
    final cart = context.read<CartProvider>();
    final imgUrl = ApiConfig.resolveImageUrl(product.imagePath);

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(10),
      elevation: 0,
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ProductDetailsPage(product: product),
            ),
          ).then((_) => _fetchResults());
        },
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppTheme.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Product Image
              Expanded(
                flex: 11,
                child: ClipRRect(
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(10)),
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      Container(
                        color: Colors.grey.shade100,
                        child: imgUrl != null
                            ? CachedNetworkImage(
                                imageUrl: imgUrl,
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

              // Details
              Expanded(
                flex: 9,
                child: Padding(
                  padding: const EdgeInsets.all(8),
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
                            style: const TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.w500),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            product.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5, height: 1.2),
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
                                  _currencyFormat.format(product.price),
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: AppTheme.primary,
                                  ),
                                ),
                              ),
                              InkWell(
                                onTap: () {
                                  cart.addItem(product);
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(
                                      content: Text('Added ${product.name} to cart!'),
                                      duration: const Duration(seconds: 1),
                                      backgroundColor: AppTheme.primary,
                                    ),
                                  );
                                },
                                borderRadius: BorderRadius.circular(16),
                                child: Container(
                                  padding: const EdgeInsets.all(4),
                                  decoration: BoxDecoration(
                                    color: AppTheme.primarySoft,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: const Icon(Icons.add_shopping_cart, size: 16, color: AppTheme.primary),
                                ),
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
      ),
    );
  }

  Widget _buildActiveFiltersBar() {
    if (!_hasActiveFilters) return const SizedBox.shrink();

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            const Text(
              'Selected: ',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.muted),
            ),
            if (_selectedCategory != 'All') ...[
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: InputChip(
                  label: Text(_selectedCategory, style: const TextStyle(fontSize: 11)),
                  onDeleted: () {
                    setState(() => _selectedCategory = 'All');
                    _fetchResults();
                  },
                  visualDensity: VisualDensity.compact,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              ),
            ],
            if (_minPriceCtrl.text.isNotEmpty || _maxPriceCtrl.text.isNotEmpty) ...[
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: InputChip(
                  label: Text(
                    'Price: ₱${_minPriceCtrl.text.isEmpty ? '0' : _minPriceCtrl.text} - ${_maxPriceCtrl.text.isEmpty ? 'Any' : '₱${_maxPriceCtrl.text}'}',
                    style: const TextStyle(fontSize: 11),
                  ),
                  onDeleted: () {
                    setState(() {
                      _minPriceCtrl.clear();
                      _maxPriceCtrl.clear();
                    });
                    _fetchResults();
                  },
                  visualDensity: VisualDensity.compact,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              ),
            ],
            if (_selectedRating != null && _selectedRating! > 0) ...[
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: InputChip(
                  label: Text('$_selectedRating★ & up', style: const TextStyle(fontSize: 11)),
                  onDeleted: () {
                    setState(() => _selectedRating = null);
                    _fetchResults();
                  },
                  visualDensity: VisualDensity.compact,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              ),
            ],
            if (_selectedProvince != null &&
                _selectedProvince!.isNotEmpty &&
                _selectedProvince != 'All' &&
                _selectedProvince != 'All locations') ...[
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: InputChip(
                  label: Text(_selectedProvince!, style: const TextStyle(fontSize: 11)),
                  onDeleted: () {
                    setState(() => _selectedProvince = null);
                    _fetchResults();
                  },
                  visualDensity: VisualDensity.compact,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              ),
            ],
            TextButton(
              onPressed: () {
                _resetAllFilters();
                _fetchResults();
              },
              style: TextButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: const Text('Clear All', style: TextStyle(fontSize: 11, color: AppTheme.primary, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildResultsView() {
    return Column(
      children: [
        _buildFilterTabs(),
        _buildActiveFiltersBar(),
        Expanded(
          child: _isResultsLoading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _fetchResults,
                  child: CustomScrollView(
                    slivers: [
                      // Related Shop Banner if any
                      if (_relatedShops.isNotEmpty)
                        SliverToBoxAdapter(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              ..._relatedShops.map((shop) => _buildRelatedShopBanner(shop)),
                              const SizedBox(height: 8),
                            ],
                          ),
                        ),

                      // Result stats
                      SliverToBoxAdapter(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                          child: Text(
                            '$_totalResults items found',
                            style: const TextStyle(fontSize: 12, color: AppTheme.muted, fontWeight: FontWeight.w600),
                          ),
                        ),
                      ),

                      // Empty state
                      if (_results.isEmpty)
                        SliverFillRemaining(
                          hasScrollBody: false,
                          child: Center(
                            child: Padding(
                              padding: const EdgeInsets.all(24),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.search_off, size: 64, color: Colors.grey.shade400),
                                  const SizedBox(height: 12),
                                  const Text(
                                    'No products found',
                                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    'We couldn\'t find any matches for "${_searchCtrl.text}". Try different keywords, adjusting price range, or clearing filters.',
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(color: AppTheme.muted, fontSize: 13),
                                  ),
                                  const SizedBox(height: 16),
                                  if (_hasActiveFilters)
                                    OutlinedButton(
                                      onPressed: () {
                                        _resetAllFilters();
                                        _fetchResults();
                                      },
                                      child: const Text('Clear All Filters'),
                                    ),
                                ],
                              ),
                            ),
                          ),
                        )
                      else
                        SliverPadding(
                          padding: const EdgeInsets.all(12),
                          sliver: SliverGrid(
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              childAspectRatio: 0.62,
                              crossAxisSpacing: 10,
                              mainAxisSpacing: 10,
                            ),
                            delegate: SliverChildBuilderDelegate(
                              (context, i) => _buildProductCard(_results[i], context),
                              childCount: _results.length,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
        ),
      ],
    );
  }

  // --- Sliding Filter Tab / Drawer (Like Web) ---
  Widget _buildFilterDrawer() {
    return Drawer(
      backgroundColor: Colors.white,
      width: MediaQuery.of(context).size.width * 0.85,
      child: SafeArea(
        child: Column(
          children: [
            // Drawer Header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: AppTheme.line)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.tune, color: AppTheme.primary, size: 22),
                  const SizedBox(width: 8),
                  const Text(
                    'Search Filters',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17, color: AppTheme.ink),
                  ),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.close, color: AppTheme.muted),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),

            // Drawer Content
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // 1. Category Section
                  const Text(
                    'Category',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: _canonicalCategories.map((cat) {
                      final isSel = _selectedCategory == cat;
                      return ChoiceChip(
                        label: Text(cat, style: const TextStyle(fontSize: 11.5)),
                        selected: isSel,
                        selectedColor: AppTheme.primary,
                        backgroundColor: Colors.grey.shade100,
                        labelStyle: TextStyle(
                          color: isSel ? Colors.white : AppTheme.ink,
                          fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                        ),
                        onSelected: (val) {
                          if (val) {
                            setState(() => _selectedCategory = cat);
                          }
                        },
                      );
                    }).toList(),
                  ),
                  const Divider(height: 32),

                  // 2. Price Range Section
                  const Text(
                    'Price Range (₱)',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: Container(
                          height: 42,
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade50,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.line),
                          ),
                          child: Row(
                            children: [
                              const Text('₱', style: TextStyle(color: AppTheme.muted, fontWeight: FontWeight.bold)),
                              const SizedBox(width: 4),
                              Expanded(
                                child: TextField(
                                  controller: _minPriceCtrl,
                                  keyboardType: TextInputType.number,
                                  style: const TextStyle(fontSize: 13),
                                  decoration: const InputDecoration(
                                    hintText: 'Min',
                                    border: InputBorder.none,
                                    isDense: true,
                                    contentPadding: EdgeInsets.zero,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 8),
                        child: Text('–', style: TextStyle(color: AppTheme.muted, fontWeight: FontWeight.bold)),
                      ),
                      Expanded(
                        child: Container(
                          height: 42,
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade50,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.line),
                          ),
                          child: Row(
                            children: [
                              const Text('₱', style: TextStyle(color: AppTheme.muted, fontWeight: FontWeight.bold)),
                              const SizedBox(width: 4),
                              Expanded(
                                child: TextField(
                                  controller: _maxPriceCtrl,
                                  keyboardType: TextInputType.number,
                                  style: const TextStyle(fontSize: 13),
                                  decoration: const InputDecoration(
                                    hintText: 'Max',
                                    border: InputBorder.none,
                                    isDense: true,
                                    contentPadding: EdgeInsets.zero,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  // Quick price shortcuts
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      _buildQuickPriceChip('Under ₱200', '', '200'),
                      _buildQuickPriceChip('₱200 - ₱500', '200', '500'),
                      _buildQuickPriceChip('₱500 - ₱1000', '500', '1000'),
                      _buildQuickPriceChip('Over ₱1000', '1000', ''),
                    ],
                  ),
                  const Divider(height: 32),

                  // 3. Customer Ratings Section
                  const Text(
                    'Customer Ratings',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 10),
                  Column(
                    children: [
                      _buildRatingTile(null, 'All ratings', 0),
                      _buildRatingTile(5, '5 stars only', 5),
                      _buildRatingTile(4, '4 stars & up', 4),
                      _buildRatingTile(3, '3 stars & up', 3),
                      _buildRatingTile(2, '2 stars & up', 2),
                      _buildRatingTile(1, '1 star & up', 1),
                    ],
                  ),
                  const Divider(height: 32),

                  // 4. Ships From Section
                  const Text(
                    'Ships From',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.ink),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      ChoiceChip(
                        label: const Text('All locations', style: TextStyle(fontSize: 11.5)),
                        selected: _selectedProvince == null || _selectedProvince == 'All' || _selectedProvince == 'All locations',
                        selectedColor: AppTheme.primary,
                        backgroundColor: Colors.grey.shade100,
                        labelStyle: TextStyle(
                          color: (_selectedProvince == null || _selectedProvince == 'All' || _selectedProvince == 'All locations')
                              ? Colors.white
                              : AppTheme.ink,
                          fontWeight: (_selectedProvince == null || _selectedProvince == 'All' || _selectedProvince == 'All locations')
                              ? FontWeight.bold
                              : FontWeight.normal,
                        ),
                        onSelected: (val) {
                          if (val) setState(() => _selectedProvince = null);
                        },
                      ),
                      ..._availableProvinces.map((prov) {
                        final isSel = _selectedProvince == prov;
                        return ChoiceChip(
                          label: Text(prov, style: const TextStyle(fontSize: 11.5)),
                          selected: isSel,
                          selectedColor: AppTheme.primary,
                          backgroundColor: Colors.grey.shade100,
                          labelStyle: TextStyle(
                            color: isSel ? Colors.white : AppTheme.ink,
                            fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                          ),
                          onSelected: (val) {
                            setState(() => _selectedProvince = val ? prov : null);
                          },
                        );
                      }),
                    ],
                  ),
                  const SizedBox(height: 20),
                ],
              ),
            ),

            // Bottom Action Buttons (Reset & Apply)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: AppTheme.line)),
              ),
              child: Row(
                children: [
                  Expanded(
                    flex: 4,
                    child: OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppTheme.ink,
                        side: const BorderSide(color: AppTheme.line),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      onPressed: () {
                        _resetAllFilters();
                      },
                      child: const Text('Reset', style: TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 6,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        elevation: 0,
                      ),
                      onPressed: () {
                        Navigator.of(context).pop();
                        _executeSearch(_searchCtrl.text);
                      },
                      child: const Text('Apply', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickPriceChip(String label, String min, String max) {
    return InkWell(
      onTap: () {
        setState(() {
          _minPriceCtrl.text = min;
          _maxPriceCtrl.text = max;
        });
      },
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.grey.shade300),
        ),
        child: Text(label, style: const TextStyle(fontSize: 10.5, color: AppTheme.muted)),
      ),
    );
  }

  Widget _buildRatingTile(int? ratingVal, String label, int starCount) {
    final isSel = _selectedRating == ratingVal;

    return InkWell(
      onTap: () {
        setState(() => _selectedRating = ratingVal);
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            Radio<int?>(
              value: ratingVal,
              groupValue: _selectedRating,
              activeColor: AppTheme.primary,
              onChanged: (v) => setState(() => _selectedRating = v),
              materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
              visualDensity: VisualDensity.compact,
            ),
            const SizedBox(width: 6),
            if (starCount > 0) ...[
              Row(
                children: List.generate(5, (index) {
                  return Icon(
                    index < starCount ? Icons.star : Icons.star_border,
                    size: 16,
                    color: index < starCount ? Colors.amber : Colors.grey.shade300,
                  );
                }),
              ),
              const SizedBox(width: 8),
            ],
            Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                color: isSel ? AppTheme.primary : AppTheme.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: _scaffoldKey,
      endDrawer: _buildFilterDrawer(),
      backgroundColor: AppTheme.bg,
      body: SafeArea(
        child: Column(
          children: [
            _buildSearchBar(),
            Expanded(
              child: switch (_mode) {
                SearchMode.history => _buildHistoryView(),
                SearchMode.suggestions => _buildSuggestionsView(),
                SearchMode.results => _buildResultsView(),
              },
            ),
          ],
        ),
      ),
    );
  }
}
