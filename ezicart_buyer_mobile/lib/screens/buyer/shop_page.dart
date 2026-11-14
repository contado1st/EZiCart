import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/product_model.dart';
import '../../models/shop_model.dart';
import '../../services/api_service.dart';
import 'messages_page.dart';
import 'product_details_page.dart';

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

  String _searchQuery = '';
  String _selectedCategory = 'All';
  final String _selectedSort = 'latest';
  final TextEditingController _searchController = TextEditingController();

  final currencyFormat = NumberFormat.currency(locale: 'en_PH', symbol: '₱');

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
              content: Text(body['message'] ?? (targetState ? 'Followed shop' : 'Unfollowed shop')),
              duration: const Duration(seconds: 2),
            ),
          );
          return;
        }
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isFollowingLoading = false);
    }
  }

  Future<void> _startChat() async {
    try {
      final res = await ApiService.post(ApiConfig.chatStartEndpoint(widget.sellerId), {});
      if (res.statusCode == 200 && mounted) {
        final body = jsonDecode(res.body);
        final conversationId = body['conversation_id'];
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ChatConversationScreen(
              conversationId: conversationId,
              partnerName: _shop?.name ?? 'Merchant',
            ),
          ),
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

  @override
  Widget build(BuildContext context) {
    final title = _shop?.name ?? widget.initialShopName ?? 'Merchant Store';

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: [
          IconButton(
            icon: const Icon(Icons.chat_outlined),
            tooltip: 'Message Store',
            onPressed: _startChat,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _fetchShopDetails,
              child: CustomScrollView(
                slivers: [
                  // Store Header Card
                  SliverToBoxAdapter(
                    child: Container(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        color: AppTheme.surface,
                        border: const Border(bottom: BorderSide(color: AppTheme.line)),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.02),
                            blurRadius: 4,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              CircleAvatar(
                                radius: 30,
                                backgroundColor: AppTheme.primarySoft,
                                child: Text(
                                  (_shop?.name.isNotEmpty == true ? _shop!.name[0] : 'S').toUpperCase(),
                                  style: const TextStyle(
                                    fontSize: 26,
                                    fontWeight: FontWeight.bold,
                                    color: AppTheme.primary,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            _shop?.name ?? '',
                                            style: const TextStyle(
                                              fontSize: 18,
                                              fontWeight: FontWeight.bold,
                                              color: AppTheme.ink,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    Row(
                                      children: [
                                        const Icon(Icons.verified, size: 14, color: AppTheme.primary),
                                        const SizedBox(width: 4),
                                        const Text(
                                          'Verified Merchant',
                                          style: TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.w600),
                                        ),
                                        if (_shop?.location.isNotEmpty == true) ...[
                                          const Text(' • ', style: TextStyle(color: AppTheme.muted)),
                                          Expanded(
                                            child: Text(
                                              _shop!.location,
                                              overflow: TextOverflow.ellipsis,
                                              style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                    const SizedBox(height: 8),
                                    // Ratings & Followers
                                    Wrap(
                                      crossAxisAlignment: WrapCrossAlignment.center,
                                      spacing: 10,
                                      runSpacing: 4,
                                      children: [
                                        Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const Icon(Icons.star, size: 14, color: Colors.amber),
                                            const SizedBox(width: 3),
                                            Text(
                                              _shop?.rating.toStringAsFixed(1) ?? '0.0',
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                                            ),
                                            Text(
                                              ' (${_shop?.reviewCount ?? 0})',
                                              style: const TextStyle(color: AppTheme.muted, fontSize: 12),
                                            ),
                                          ],
                                        ),
                                        Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const Icon(Icons.people_alt_outlined, size: 14, color: AppTheme.muted),
                                            const SizedBox(width: 4),
                                            Text(
                                              '${_shop?.followerCount ?? 0} followers',
                                              style: const TextStyle(color: AppTheme.muted, fontSize: 12),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),
                          // Follow / Unfollow & Chat Action Bar
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    backgroundColor: _shop?.isFollowing == true ? AppTheme.primarySoft : Colors.transparent,
                                    side: BorderSide(
                                      color: _shop?.isFollowing == true ? AppTheme.primary : AppTheme.primary,
                                      width: 1.5,
                                    ),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                  ),
                                  icon: _isFollowingLoading
                                      ? const SizedBox(
                                          width: 16,
                                          height: 16,
                                          child: CircularProgressIndicator(strokeWidth: 2),
                                        )
                                      : Icon(
                                          _shop?.isFollowing == true ? Icons.check : Icons.add,
                                          size: 16,
                                          color: AppTheme.primary,
                                        ),
                                  label: Text(
                                    _shop?.isFollowing == true ? 'Following' : 'Follow Shop',
                                    style: const TextStyle(
                                      color: AppTheme.primary,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 13,
                                    ),
                                  ),
                                  onPressed: _toggleFollow,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: ElevatedButton.icon(
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppTheme.primary,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                  ),
                                  icon: const Icon(Icons.chat_bubble_outline, size: 16),
                                  label: const Text(
                                    'Contact Seller',
                                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                  ),
                                  onPressed: _startChat,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),

                  // Search & Category Filters
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                      child: Column(
                        children: [
                          TextField(
                            controller: _searchController,
                            decoration: InputDecoration(
                              hintText: 'Search products in this store...',
                              prefixIcon: const Icon(Icons.search, size: 20),
                              suffixIcon: _searchQuery.isNotEmpty
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
                              fillColor: AppTheme.surface,
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(8),
                                borderSide: const BorderSide(color: AppTheme.line),
                              ),
                            ),
                            onSubmitted: (val) {
                              setState(() => _searchQuery = val);
                              _fetchShopDetails();
                            },
                          ),
                          if (_shop?.categories.isNotEmpty == true) ...[
                            const SizedBox(height: 10),
                            SingleChildScrollView(
                              scrollDirection: Axis.horizontal,
                              child: Row(
                                children: [
                                  ChoiceChip(
                                    label: const Text('All'),
                                    selected: _selectedCategory == 'All',
                                    selectedColor: AppTheme.primary,
                                    labelStyle: TextStyle(
                                      color: _selectedCategory == 'All' ? Colors.white : AppTheme.ink,
                                      fontSize: 12,
                                    ),
                                    onSelected: (selected) {
                                      if (selected) {
                                        setState(() => _selectedCategory = 'All');
                                        _fetchShopDetails();
                                      }
                                    },
                                  ),
                                  const SizedBox(width: 8),
                                  ..._shop!.categories.map((c) {
                                    final isSel = _selectedCategory == c;
                                    return Padding(
                                      padding: const EdgeInsets.only(right: 8),
                                      child: ChoiceChip(
                                        label: Text(c),
                                        selected: isSel,
                                        selectedColor: AppTheme.primary,
                                        labelStyle: TextStyle(
                                          color: isSel ? Colors.white : AppTheme.ink,
                                          fontSize: 12,
                                        ),
                                        onSelected: (selected) {
                                          if (selected) {
                                            setState(() => _selectedCategory = c);
                                            _fetchShopDetails();
                                          }
                                        },
                                      ),
                                    );
                                  }),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),

                  // Products Grid or Empty
                  if (_products.isEmpty)
                    SliverFillRemaining(
                      hasScrollBody: false,
                      child: Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.inventory_2_outlined, size: 54, color: Colors.grey.shade300),
                            const SizedBox(height: 12),
                            const Text(
                              'No products found in this store',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'Try clearing your search query or choosing another category.',
                              style: TextStyle(color: AppTheme.muted, fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                    )
                  else
                    SliverPadding(
                      padding: const EdgeInsets.all(16),
                      sliver: SliverGrid(
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          childAspectRatio: 0.63,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                        ),
                        delegate: SliverChildBuilderDelegate(
                          (context, index) {
                            final product = _products[index];
                            final imageUrl = ApiConfig.resolveImageUrl(product.imagePath);

                            return InkWell(
                              onTap: () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => ProductDetailsPage(product: product),
                                  ),
                                );
                              },
                              borderRadius: BorderRadius.circular(10),
                              child: Container(
                                decoration: BoxDecoration(
                                  color: AppTheme.surface,
                                  borderRadius: BorderRadius.circular(10),
                                  border: Border.all(color: AppTheme.line),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Expanded(
                                      child: ClipRRect(
                                        borderRadius: const BorderRadius.vertical(top: Radius.circular(9)),
                                        child: imageUrl != null
                                            ? CachedNetworkImage(
                                                imageUrl: imageUrl,
                                                width: double.infinity,
                                                fit: BoxFit.cover,
                                                placeholder: (_, __) => Container(
                                                  color: Colors.grey.shade100,
                                                  child: const Center(
                                                    child: SizedBox(
                                                      width: 24,
                                                      height: 24,
                                                      child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.primary),
                                                    ),
                                                  ),
                                                ),
                                                errorWidget: (_, __, ___) => Container(
                                                  color: Colors.grey.shade100,
                                                  child: const Icon(Icons.inventory_2_outlined, color: AppTheme.muted),
                                                ),
                                              )
                                            : Container(
                                                color: Colors.grey.shade100,
                                                child: const Icon(Icons.shopping_bag_outlined, color: Colors.grey),
                                              ),
                                      ),
                                    ),
                                    Padding(
                                      padding: const EdgeInsets.all(10),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            product.name,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            currencyFormat.format(product.price),
                                            style: const TextStyle(
                                              color: AppTheme.primary,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 14,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          Row(
                                            children: [
                                              const Icon(Icons.star, size: 12, color: Colors.amber),
                                              const SizedBox(width: 3),
                                              Text(
                                                product.rating.toStringAsFixed(1),
                                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                                              ),
                                              const Spacer(),
                                              Text(
                                                '${product.stock} left',
                                                style: const TextStyle(fontSize: 11, color: AppTheme.muted),
                                              ),
                                            ],
                                          ),
                                        ],
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
            ),
    );
  }
}

