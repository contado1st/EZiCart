import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/product_model.dart';
import '../../services/api_service.dart';
import 'product_details_page.dart';

class ForYouPage extends StatefulWidget {
  const ForYouPage({super.key});

  @override
  State<ForYouPage> createState() => _ForYouPageState();
}

class _ForYouPageState extends State<ForYouPage> {
  List<ProductModel> _products = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchRecommendations();
  }

  Future<void> _fetchRecommendations() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.discoveryForYouEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((p) => ProductModel.fromJson(p as Map<String, dynamic>))
              .toList();
          setState(() {
            _products = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  void _confirmResetHistory() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reset Recommendation History?'),
        content: const Text(
          'This will clear your browsing view history. Your recommendations will refresh and prioritize your completed purchases.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () async {
              Navigator.pop(ctx);
              try {
                final res = await ApiService.delete(ApiConfig.discoveryResetHistoryEndpoint);
                if (res.statusCode == 200 && mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Browsing history cleared.'), backgroundColor: Colors.green),
                  );
                  _fetchRecommendations();
                }
              } catch (_) {}
            },
            child: const Text('Clear History'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final currency = NumberFormat.currency(symbol: '₱', decimalDigits: 2);

    return Scaffold(
      appBar: AppBar(
        title: const Text('For You Recommendations'),
        actions: [
          IconButton(
            icon: const Icon(Icons.history_outlined),
            tooltip: 'Clear Recommendation History',
            onPressed: _confirmResetHistory,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _products.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.auto_awesome_outlined, size: 54, color: Colors.grey.shade400),
                      const SizedBox(height: 12),
                      const Text(
                        'No recommendations yet',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                      const SizedBox(height: 4),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 32),
                        child: Text(
                          'Browse products and explore categories to receive personalized recommendations tailored for you.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: AppTheme.muted, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _fetchRecommendations,
                  child: GridView.builder(
                    padding: const EdgeInsets.all(16),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      childAspectRatio: 0.68,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                    ),
                    itemCount: _products.length,
                    itemBuilder: (context, i) {
                      final product = _products[i];
                      final imgUrl = ApiConfig.resolveImageUrl(product.imagePath);

                      return InkWell(
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(builder: (_) => ProductDetailsPage(product: product)),
                          );
                        },
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppTheme.line),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              ClipRRect(
                                borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                                child: AspectRatio(
                                  aspectRatio: 1.1,
                                  child: imgUrl != null
                                      ? CachedNetworkImage(
                                          imageUrl: imgUrl,
                                          fit: BoxFit.cover,
                                          placeholder: (_, __) => Container(color: Colors.grey.shade100),
                                          errorWidget: (_, __, ___) => const Center(
                                            child: Icon(Icons.image_not_supported_outlined, color: Colors.grey),
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
                                      currency.format(product.price),
                                      style: const TextStyle(
                                        color: AppTheme.primary,
                                        fontWeight: FontWeight.bold,
                                        fontSize: 14,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Row(
                                      children: [
                                        const Icon(Icons.star, size: 13, color: Colors.amber),
                                        const SizedBox(width: 2),
                                        Text(
                                          product.rating.toStringAsFixed(1),
                                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                                        ),
                                        const SizedBox(width: 4),
                                        Text(
                                          '(${product.reviewCount})',
                                          style: const TextStyle(fontSize: 10, color: AppTheme.muted),
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
                  ),
                ),
    );
  }
}

