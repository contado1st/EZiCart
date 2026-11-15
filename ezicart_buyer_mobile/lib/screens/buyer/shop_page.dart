import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/product_model.dart';
import '../../models/shop_model.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import 'messages_page.dart';
import 'product_details_page.dart';

/// Preset color palette matching EZiCart Web profile theme definitions:
/// (rose, wine, navy, teal)
class _ShopThemePalette {
  final Color accent;
  final Color soft;
  final Color cover;
  final Color ink;

  const _ShopThemePalette({
    required this.accent,
    required this.soft,
    required this.cover,
    required this.ink,
  });

  static _ShopThemePalette of(String themeKey) {
    switch (themeKey.toLowerCase()) {
      case 'wine':
        return const _ShopThemePalette(
          accent: Color(0xFF71384F),
          soft: Color(0xFFF3EDF4),
          cover: Color(0xFFD7C4D5),
          ink: Color(0xFF4D2538),
        );
      case 'navy':
        return const _ShopThemePalette(
          accent: Color(0xFF294765),
          soft: Color(0xFFEDF3F8),
          cover: Color(0xFFC6D5E5),
          ink: Color(0xFF233B54),
        );
      case 'teal':
        return const _ShopThemePalette(
          accent: Color(0xFF23685F),
          soft: Color(0xFFEDF7F5),
          cover: Color(0xFFBCDCD4),
          ink: Color(0xFF1D514B),
        );
      case 'rose':
      default:
        return const _ShopThemePalette(
          accent: Color(0xFFBE2854),
          soft: Color(0xFFFFF0F4),
          cover: Color(0xFFF4C6D5),
          ink: Color(0xFF662139),
        );
    }
  }
}

class ShopPage extends StatefulWidget {
  final int sellerId;
  final String? initialShopName;

  const ShopPage({
    super.key,
    required this.sellerId,
    this.initialShopName,
  });

  @override
  State<ShopPage> createState() => _ShopPageState();
}

class _ShopPageState extends State<ShopPage> {
  ShopModel? _shop;
  List<ProductModel> _products = [];
  bool _isLoading = true;
  bool _isFollowingLoading = false;

  // Filters matching Web
  String _searchQuery = '';
  String _selectedCategory = 'All';
  String _selectedSort = 'latest';
  final TextEditingController _searchController = TextEditingController();

  // Tab index: 0 = Products, 1 = About this shop
  int _currentTab = 0;

  final currencyFormat = NumberFormat.currency(locale: 'en_PH', symbol: '₱');

  static const Map<String, String> _sortOptions = {
    'latest': 'Newest first',
    'price_asc': 'Price: low to high',
    'price_desc': 'Price: high to low',
    'rating': 'Top rated',
  };

  @override
  void initState() {
    super.initState();
    _fetchShopDetails();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchShopDetails() async {
    setState(() => _isLoading = true);

    var url = '${ApiConfig.shopDetailsEndpoint(widget.sellerId)}?sort=$_selectedSort';
    if (_searchQuery.trim().isNotEmpty) {
      url += '&q=${Uri.encodeComponent(_searchQuery.trim())}';
    }
    if (_selectedCategory != 'All') {
      url += '&category=${Uri.encodeComponent(_selectedCategory)}';
    }

    try {
      final res = await ApiService.get(url);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['shop'] != null) {
          final s = ShopModel.fromJson(body['shop'] as Map<String, dynamic>);
          List<ProductModel> prods = [];
          if (body['products']?['data'] is List) {
            prods = (body['products']['data'] as List)
                .map((p) => ProductModel.fromJson(p as Map<String, dynamic>))
                .toList();
          }

          if (mounted) {
            setState(() {
              _shop = s;
              _products = prods;
              _isLoading = false;
            });
            return;
          }
        }
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _toggleFollow() async {
    if (_shop == null || _isFollowingLoading) return;

    final targetState = !_shop!.isFollowing;
    setState(() => _isFollowingLoading = true);

    try {
      final res = await ApiService.post(
        ApiConfig.shopFollowEndpoint(widget.sellerId),
        {'following': targetState},
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final newFollowerCount = body['follower_count'] is int
            ? body['follower_count']
            : (_shop!.followerCount + (targetState ? 1 : -1));

        if (mounted) {
          setState(() {
            _shop = _shop!.copyWith(
              isFollowing: targetState,
              followerCount: newFollowerCount,
            );
            _isFollowingLoading = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(body['message'] ?? (targetState ? 'You are now following this shop.' : 'You have unfollowed this shop.')),
              duration: const Duration(seconds: 2),
            ),
          );
          return;
        }
      } else {
        final err = jsonDecode(res.body);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(err['message'] ?? 'Could not update follow status')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }

    if (mounted) {
      setState(() => _isFollowingLoading = false);
    }
  }

  Future<void> _startChat() async {
    try {
      final res = await ApiService.post(ApiConfig.chatStartEndpoint(widget.sellerId), {});
      if ((res.statusCode == 200 || res.statusCode == 201) && mounted) {
        final body = jsonDecode(res.body);
        final conversationId = body['conversation_id'];
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ChatConversationScreen(
              conversationId: conversationId,
              partnerName: _shop?.displayName ?? _shop?.name ?? 'Merchant',
              partnerId: widget.sellerId,
              partnerRole: 'seller',
            ),
          ),
        );
      } else if (mounted) {
        final err = jsonDecode(res.body);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(err['message'] ?? 'Could not start chat.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not start chat: $e')),
        );
      }
    }
  }

  void _resetFilters() {
    _searchController.clear();
    setState(() {
      _searchQuery = '';
      _selectedCategory = 'All';
      _selectedSort = 'latest';
    });
    _fetchShopDetails();
  }

  bool get _hasActiveFilters =>
      _searchQuery.isNotEmpty || _selectedCategory != 'All' || _selectedSort != 'latest';

  @override
  Widget build(BuildContext context) {
    final theme = _ShopThemePalette.of(_shop?.profileTheme ?? 'rose');
    final title = _shop?.displayName ?? _shop?.name ?? widget.initialShopName ?? 'Merchant Store';

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(
        title: Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.chat_bubble_outline, size: 20),
            tooltip: 'Chat with seller',
            onPressed: _startChat,
          ),
        ],
      ),
      body: _isLoading && _shop == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _fetchShopDetails,
              child: CustomScrollView(
                slivers: [
                  // Cover Banner & Overlapping Avatar Header
                  SliverToBoxAdapter(
                    child: _buildShopHeader(theme),
                  ),

                  // Inactivity Warning (if applicable)
                  if (_shop?.inactivityWarning != null)
                    SliverToBoxAdapter(
                      child: Container(
                        margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF3C7),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.4)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.info_outline, size: 18, color: Color(0xFFB45309)),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _shop!.inactivityWarning!,
                                style: const TextStyle(fontSize: 12, color: Color(0xFF92400E)),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                  // Shop Stats (Available Products, Product Rating, Followers, On EZiCart Since)
                  SliverToBoxAdapter(
                    child: _buildShopStats(theme),
                  ),

                  // Section Navigation Tabs (Products / About this shop)
                  SliverToBoxAdapter(
                    child: _buildSectionTabs(theme),
                  ),

                  // Tab 0: Products Section
                  if (_currentTab == 0) ...[
                    // Search in this shop, Category, Sort by
                    SliverToBoxAdapter(
                      child: _buildFilterBar(theme),
                    ),

                    // Products Heading & Matching Count
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                        child: Row(
                          children: [
                            const Text(
                              'Shop products',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.ink),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              '•  ${_products.length} matching ${_products.length == 1 ? 'product' : 'products'}',
                              style: const TextStyle(fontSize: 13, color: AppTheme.muted),
                            ),
                          ],
                        ),
                      ),
                    ),

                    // Product Grid or Empty State
                    if (_products.isEmpty)
                      SliverToBoxAdapter(
                        child: _buildEmptyProducts(theme),
                      )
                    else
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                        sliver: SliverGrid(
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            childAspectRatio: 0.53,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                          ),
                          delegate: SliverChildBuilderDelegate(
                            (context, index) => _buildProductCard(_products[index], theme),
                            childCount: _products.length,
                          ),
                        ),
                      ),
                  ] else ...[
                    // Tab 1: About this shop Section
                    SliverToBoxAdapter(
                      child: _buildAboutSection(theme),
                    ),
                  ],
                ],
              ),
            ),
    );
  }

  /// Cover banner with overlapping avatar, identity and follow action
  Widget _buildShopHeader(_ShopThemePalette theme) {
    final bannerUrl = ApiConfig.resolveImageUrl(_shop?.bannerUrl);
    final avatarUrl = ApiConfig.resolveImageUrl(_shop?.avatarUrl);
    final isFollowing = _shop?.isFollowing == true;

    return Container(
      color: Colors.white,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Banner
          Stack(
            clipBehavior: Clip.none,
            children: [
              Container(
                height: 135,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: theme.cover,
                  gradient: LinearGradient(
                    colors: [theme.cover, theme.soft],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: bannerUrl != null
                    ? CachedNetworkImage(
                        imageUrl: bannerUrl,
                        fit: BoxFit.cover,
                        width: double.infinity,
                        height: 135,
                        placeholder: (_, __) => Container(color: theme.cover),
                        errorWidget: (_, __, ___) => _buildBannerFallback(theme),
                      )
                    : _buildBannerFallback(theme),
              ),

              // Overlapping Avatar
              Positioned(
                left: 18,
                bottom: -36,
                child: Container(
                  width: 82,
                  height: 82,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: theme.soft,
                    border: Border.all(color: Colors.white, width: 4),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.08),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: ClipOval(
                    child: avatarUrl != null
                        ? CachedNetworkImage(
                            imageUrl: avatarUrl,
                            fit: BoxFit.cover,
                            placeholder: (_, __) => Container(color: theme.soft),
                            errorWidget: (_, __, ___) => _buildAvatarFallback(theme),
                          )
                        : _buildAvatarFallback(theme),
                  ),
                ),
              ),
            ],
          ),

          // Spacing below avatar overlap
          const SizedBox(height: 42),

          // Shop Identity Copy
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Display Name
                Text(
                  _shop?.displayName ?? _shop?.name ?? 'Seller shop',
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.ink,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(height: 3),

                // Business Name & Location
                Row(
                  children: [
                    if (_shop?.businessName != null && _shop!.businessName!.isNotEmpty) ...[
                      Flexible(
                        child: Text(
                          _shop!.businessName!,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 13, color: AppTheme.muted, fontWeight: FontWeight.w500),
                        ),
                      ),
                      if (_shop?.location.isNotEmpty == true) ...[
                        const Text(' · ', style: TextStyle(color: AppTheme.muted)),
                      ],
                    ],
                    if (_shop?.location.isNotEmpty == true)
                      Flexible(
                        child: Text(
                          _shop!.location,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 13, color: AppTheme.muted),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 8),

                // Badges & Activity Status
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    // Approved Seller Badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: theme.soft,
                        borderRadius: BorderRadius.circular(5),
                      ),
                      child: Text(
                        'Approved seller',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: theme.accent,
                        ),
                      ),
                    ),

                    // Activity Status with Dot
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: _shop?.isActiveRecently == true ? const Color(0xFF10B981) : Colors.grey.shade400,
                          ),
                        ),
                        const SizedBox(width: 5),
                        Text(
                          _shop?.lastActive ?? 'Activity not recorded yet',
                          style: TextStyle(
                            fontSize: 12,
                            color: _shop?.isActiveRecently == true ? const Color(0xFF065F46) : AppTheme.muted,
                            fontWeight: _shop?.isActiveRecently == true ? FontWeight.w600 : FontWeight.normal,
                          ),
                        ),
                      ],
                    ),

                    // Shop Availability
                    if (_shop?.availabilityNote != null && _shop!.availabilityNote.isNotEmpty)
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Text('•', style: TextStyle(color: AppTheme.muted, fontSize: 12)),
                          const SizedBox(width: 6),
                          Text(
                            _shop!.availabilityNote,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                              color: _shop?.shopAvailability == 'open' ? const Color(0xFF047857) : const Color(0xFFB45309),
                            ),
                          ),
                        ],
                      ),
                  ],
                ),
                const SizedBox(height: 16),

                // Action Buttons: Follow shop & Contact seller
                Row(
                  children: [
                    // Follow / Following Button
                    Expanded(
                      child: SizedBox(
                        height: 40,
                        child: isFollowing
                            ? OutlinedButton.icon(
                                style: OutlinedButton.styleFrom(
                                  side: BorderSide(color: theme.accent, width: 1.2),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  backgroundColor: theme.soft,
                                ),
                                icon: _isFollowingLoading
                                    ? SizedBox(
                                        width: 14,
                                        height: 14,
                                        child: CircularProgressIndicator(strokeWidth: 2, color: theme.accent),
                                      )
                                    : Icon(Icons.check, size: 16, color: theme.accent),
                                label: Text(
                                  'Following · Unfollow',
                                  style: TextStyle(color: theme.accent, fontWeight: FontWeight.bold, fontSize: 12),
                                ),
                                onPressed: _toggleFollow,
                              )
                            : ElevatedButton.icon(
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: theme.accent,
                                  foregroundColor: Colors.white,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  elevation: 0,
                                ),
                                icon: _isFollowingLoading
                                    ? const SizedBox(
                                        width: 14,
                                        height: 14,
                                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                      )
                                    : const Icon(Icons.person_add_alt_1_outlined, size: 16),
                                label: const Text(
                                  'Follow shop',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                ),
                                onPressed: _toggleFollow,
                              ),
                      ),
                    ),
                    const SizedBox(width: 10),

                    // Chat with Seller Button
                    Expanded(
                      child: SizedBox(
                        height: 40,
                        child: OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: AppTheme.line),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            backgroundColor: Colors.white,
                          ),
                          icon: const Icon(Icons.chat_bubble_outline, size: 16, color: AppTheme.ink),
                          label: const Text(
                            'Chat with seller',
                            style: TextStyle(color: AppTheme.ink, fontWeight: FontWeight.w600, fontSize: 12),
                          ),
                          onPressed: _startChat,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBannerFallback(_ShopThemePalette theme) {
    return Container(
      color: theme.cover,
      alignment: Alignment.bottomRight,
      padding: const EdgeInsets.only(right: 18, bottom: 14),
      child: Text(
        'EZiCart',
        style: TextStyle(
          color: theme.ink.withValues(alpha: 0.6),
          fontSize: 22,
          fontWeight: FontWeight.bold,
          letterSpacing: -0.5,
        ),
      ),
    );
  }

  Widget _buildAvatarFallback(_ShopThemePalette theme) {
    final name = _shop?.displayName ?? _shop?.name ?? 'S';
    final initial = name.isNotEmpty ? name[0].toUpperCase() : 'S';
    return Center(
      child: Text(
        initial,
        style: TextStyle(
          fontSize: 32,
          fontWeight: FontWeight.bold,
          color: theme.accent,
        ),
      ),
    );
  }

  /// Shop Stats Bar (Available Products, Product Rating, Followers, On EZiCart Since)
  Widget _buildShopStats(_ShopThemePalette theme) {
    final ratingText = _shop?.reviewCount != null && _shop!.reviewCount > 0
        ? '${_shop!.rating.toStringAsFixed(1)} / 5'
        : 'Not rated yet';
    final reviewText = _shop?.reviewCount != null && _shop!.reviewCount > 0
        ? '${_shop!.reviewCount} ${_shop!.reviewCount == 1 ? 'review' : 'reviews'}'
        : '0 reviews';

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      margin: const EdgeInsets.only(top: 1),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          color: const Color(0xFFFAFBFC),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppTheme.line),
        ),
        child: Row(
          children: [
            _buildStatItem('Available products', '${_shop?.productCount ?? 0}', null),
            _buildVerticalDivider(),
            _buildStatItem('Product rating', ratingText, reviewText),
            _buildVerticalDivider(),
            _buildStatItem('Followers', '${_shop?.followerCount ?? 0}', null),
            _buildVerticalDivider(),
            _buildStatItem('On EZiCart since', _shop?.memberSince ?? 'Not recorded', null),
          ],
        ),
      ),
    );
  }

  Widget _buildStatItem(String label, String value, String? sub) {
    return Expanded(
      child: Column(
        children: [
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 10, color: AppTheme.muted, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.ink),
          ),
          if (sub != null) ...[
            const SizedBox(height: 2),
            Text(
              sub,
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 9, color: AppTheme.muted),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildVerticalDivider() {
    return Container(
      height: 30,
      width: 1,
      color: AppTheme.line,
    );
  }

  /// Section Switcher Tabs: [Products] [About this shop]
  Widget _buildSectionTabs(_ShopThemePalette theme) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Container(
        decoration: BoxDecoration(
          color: const Color(0xFFF1F3F6),
          borderRadius: BorderRadius.circular(8),
        ),
        padding: const EdgeInsets.all(3),
        child: Row(
          children: [
            Expanded(
              child: GestureDetector(
                onTap: () => setState(() => _currentTab = 0),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: _currentTab == 0 ? Colors.white : Colors.transparent,
                    borderRadius: BorderRadius.circular(6),
                    boxShadow: _currentTab == 0
                        ? [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              blurRadius: 4,
                              offset: const Offset(0, 1),
                            ),
                          ]
                        : null,
                  ),
                  child: Center(
                    child: Text(
                      'Products (${_shop?.productCount ?? _products.length})',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: _currentTab == 0 ? theme.accent : AppTheme.muted,
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Expanded(
              child: GestureDetector(
                onTap: () => setState(() => _currentTab = 1),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: _currentTab == 1 ? Colors.white : Colors.transparent,
                    borderRadius: BorderRadius.circular(6),
                    boxShadow: _currentTab == 1
                        ? [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              blurRadius: 4,
                              offset: const Offset(0, 1),
                            ),
                          ]
                        : null,
                  ),
                  child: Center(
                    child: Text(
                      'About this shop',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: _currentTab == 1 ? theme.accent : AppTheme.muted,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Search in the shop, Category chips, Sort by selector, Reset button
  Widget _buildFilterBar(_ShopThemePalette theme) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Search in this shop input
          TextField(
            controller: _searchController,
            textInputAction: TextInputAction.search,
            onSubmitted: (val) {
              setState(() => _searchQuery = val.trim());
              _fetchShopDetails();
            },
            decoration: InputDecoration(
              hintText: 'Search products in this shop...',
              hintStyle: const TextStyle(fontSize: 13, color: AppTheme.muted),
              prefixIcon: const Icon(Icons.search, size: 20, color: AppTheme.muted),
              suffixIcon: _searchController.text.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () {
                        _searchController.clear();
                        setState(() => _searchQuery = '');
                        _fetchShopDetails();
                      },
                    )
                  : null,
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              filled: true,
              fillColor: const Color(0xFFF9FAFB),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: AppTheme.line),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: AppTheme.line),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: BorderSide(color: theme.accent, width: 1.5),
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Categories & Sort Row
          Row(
            children: [
              // Sort by dropdown button
              Container(
                height: 34,
                padding: const EdgeInsets.symmetric(horizontal: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF9FAFB),
                  borderRadius: BorderRadius.circular(7),
                  border: Border.all(color: AppTheme.line),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<String>(
                    value: _selectedSort,
                    icon: const Icon(Icons.keyboard_arrow_down, size: 18, color: AppTheme.ink),
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.ink),
                    items: _sortOptions.entries.map((entry) {
                      return DropdownMenuItem<String>(
                        value: entry.key,
                        child: Text(entry.value),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null && val != _selectedSort) {
                        setState(() => _selectedSort = val);
                        _fetchShopDetails();
                      }
                    },
                  ),
                ),
              ),
              const SizedBox(width: 8),

              // Categories Chips (Horizontal scrolling)
              Expanded(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      // "All categories" chip
                      _buildCategoryChip('All', theme),
                      if (_shop?.categories != null)
                        ..._shop!.categories.map((c) => _buildCategoryChip(c, theme)),
                    ],
                  ),
                ),
              ),

              // Reset Button (if active filters)
              if (_hasActiveFilters) ...[
                const SizedBox(width: 6),
                GestureDetector(
                  onTap: _resetFilters,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
                    decoration: BoxDecoration(
                      color: theme.soft,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      'Reset',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: theme.accent,
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryChip(String label, _ShopThemePalette theme) {
    final isSelected = _selectedCategory == label;
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: GestureDetector(
        onTap: () {
          if (_selectedCategory != label) {
            setState(() => _selectedCategory = label);
            _fetchShopDetails();
          }
        },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
          decoration: BoxDecoration(
            color: isSelected ? theme.accent : const Color(0xFFF9FAFB),
            borderRadius: BorderRadius.circular(7),
            border: Border.all(
              color: isSelected ? theme.accent : AppTheme.line,
            ),
          ),
          child: Text(
            label == 'All' ? 'All categories' : label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
              color: isSelected ? Colors.white : AppTheme.ink,
            ),
          ),
        ),
      ),
    );
  }

  /// Product Card
  Widget _buildProductCard(ProductModel product, _ShopThemePalette theme) {
    final imageUrl = ApiConfig.resolveImageUrl(product.imagePath);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppTheme.line),
      ),
      child: InkWell(
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ProductDetailsPage(product: product),
            ),
          );
        },
        borderRadius: BorderRadius.circular(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image
            Expanded(
              flex: 9,
              child: ClipRRect(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(10)),
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Container(
                      color: const Color(0xFFF3F4F6),
                      child: imageUrl != null
                          ? CachedNetworkImage(
                              imageUrl: imageUrl,
                              fit: BoxFit.cover,
                              placeholder: (_, __) => const Center(
                                child: SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                ),
                              ),
                              errorWidget: (_, __, ___) => const Center(
                                child: Icon(Icons.inventory_2_outlined, color: AppTheme.muted, size: 30),
                              ),
                            )
                          : const Center(
                              child: Icon(Icons.inventory_2_outlined, color: AppTheme.muted, size: 30),
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
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9.5,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),

            // Divider line between Product image and Title
            const Divider(height: 1, thickness: 1, color: AppTheme.line),

            // Product Details
            Expanded(
              flex: 11,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 6, 8, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          product.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 12.5,
                            height: 1.2,
                          ),
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
                                product.formattedPrice,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: theme.accent,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 13,
                                ),
                              ),
                            ),
                            InkWell(
                              onTap: () {
                                final cart = Provider.of<CartProvider>(context, listen: false);
                                cart.addItem(product);
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text('Added ${product.name} to cart!'),
                                    duration: const Duration(seconds: 1),
                                    backgroundColor: theme.accent,
                                  ),
                                );
                              },
                              borderRadius: BorderRadius.circular(6),
                              child: Container(
                                padding: const EdgeInsets.all(4),
                                decoration: BoxDecoration(
                                  color: theme.soft,
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Icon(
                                  Icons.add_shopping_cart,
                                  size: 16,
                                  color: theme.accent,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 3),
                        Row(
                          children: [
                            if (product.rating > 0.0) ...[
                              const Icon(Icons.star, size: 12, color: Colors.amber),
                              const SizedBox(width: 2),
                              Text(
                                product.rating.toStringAsFixed(1),
                                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
                              ),
                            ] else ...[
                              const Icon(Icons.star_border, size: 12, color: AppTheme.muted),
                              const SizedBox(width: 2),
                              const Text('0.0', style: TextStyle(fontSize: 10, color: AppTheme.muted)),
                            ],
                            const SizedBox(width: 6),
                            Text(
                              product.salesCount > 0
                                  ? '${product.salesCount} sold'
                                  : '${product.availableStock} in stock',
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
  }

  /// Empty Products State
  Widget _buildEmptyProducts(_ShopThemePalette theme) {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.line),
      ),
      child: Center(
        child: Column(
          children: [
            Icon(Icons.inventory_2_outlined, size: 48, color: theme.accent.withValues(alpha: 0.6)),
            const SizedBox(height: 14),
            const Text(
              'No matching products',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.ink),
            ),
            const SizedBox(height: 6),
            const Text(
              'Try another keyword or category, or check back when this shop adds available products.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppTheme.muted, fontSize: 13, height: 1.5),
            ),
            if (_hasActiveFilters) ...[
              const SizedBox(height: 16),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  side: BorderSide(color: theme.accent),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                icon: const Icon(Icons.refresh, size: 16),
                label: const Text('Reset filters'),
                onPressed: _resetFilters,
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Tab 1: About this shop Section (matching Web)
  Widget _buildAboutSection(_ShopThemePalette theme) {
    final aboutText = (_shop?.about != null && _shop!.about.isNotEmpty)
        ? _shop!.about
        : 'This seller has not added a shop introduction yet.';

    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.storefront_outlined, size: 20, color: theme.accent),
              const SizedBox(width: 8),
              const Text(
                'About this shop',
                style: TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.ink,
                  letterSpacing: -0.2,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Seller Bio / Intro
          Text(
            aboutText,
            style: const TextStyle(
              fontSize: 14,
              color: Color(0xFF454F60),
              height: 1.6,
            ),
          ),
          const SizedBox(height: 16),

          // Line of Business (if available)
          if (_shop?.lineOfBusiness != null && _shop!.lineOfBusiness!.isNotEmpty) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: theme.soft,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(Icons.business_center_outlined, size: 16, color: theme.accent),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _shop!.lineOfBusiness!,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: theme.ink,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Registration & Location Info
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppTheme.line),
            ),
            child: Column(
              children: [
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 16, color: AppTheme.muted),
                    const SizedBox(width: 8),
                    const Text('Location', style: TextStyle(fontSize: 12, color: AppTheme.muted)),
                    const Spacer(),
                    Text(
                      _shop?.location.isNotEmpty == true ? _shop!.location : 'Philippines',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.ink),
                    ),
                  ],
                ),
                const Divider(height: 16, color: AppTheme.line),
                Row(
                  children: [
                    const Icon(Icons.calendar_today_outlined, size: 15, color: AppTheme.muted),
                    const SizedBox(width: 8),
                    const Text('On EZiCart since', style: TextStyle(fontSize: 12, color: AppTheme.muted)),
                    const Spacer(),
                    Text(
                      _shop?.memberSince ?? 'Not recorded',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.ink),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Disclaimer note matching Web
          Text(
            _shop?.ratingNote ?? 'Rating includes reviews of approved, non-archived products, including items currently out of stock.',
            style: const TextStyle(
              fontSize: 11,
              color: AppTheme.muted,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }
}
