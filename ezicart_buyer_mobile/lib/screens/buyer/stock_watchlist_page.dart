import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/product_model.dart';
import '../../models/stock_alert_model.dart';
import '../../services/api_service.dart';
import 'product_details_page.dart';

class StockWatchlistPage extends StatefulWidget {
  const StockWatchlistPage({super.key});

  @override
  State<StockWatchlistPage> createState() => _StockWatchlistPageState();
}

class _StockWatchlistPageState extends State<StockWatchlistPage> {
  List<StockAlertProduct> _items = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchWatchlist();
  }

  Future<void> _fetchWatchlist() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.stockAlertsEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((p) => StockAlertProduct.fromJson(p as Map<String, dynamic>))
              .toList();
          setState(() {
            _items = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() => _isLoading = false);
  }

  Future<void> _removeAlert(StockAlertProduct item) async {
    try {
      final res = await ApiService.delete(ApiConfig.unwatchStockEndpoint(item.id));
      if (res.statusCode == 200 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Stock alert removed for ${item.name}.')),
        );
        _fetchWatchlist();
      }
    } catch (_) {}
  }

  Color _getStatusColor(StockAlertProduct item) {
    if (item.isOutOfStock) {
      return Colors.red.shade700;
    }
    if (item.isLowStock) {
      return Colors.orange.shade800;
    }
    return Colors.green.shade700;
  }

  String _getStatusLabel(StockAlertProduct item) {
    if (item.isOutOfStock) {
      return 'OUT OF STOCK';
    }
    if (item.isLowStock) {
      return 'LOW STOCK';
    }
    return 'AVAILABLE';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Stock Alerts Watchlist'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.notifications_paused_outlined, size: 54, color: Colors.grey.shade400),
                      const SizedBox(height: 12),
                      const Text(
                        'No stock alerts watched',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                      const SizedBox(height: 4),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 32),
                        child: Text(
                          'Tap the bell icon on any product page to watch for stock restocks or inventory changes.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: AppTheme.muted, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _fetchWatchlist,
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, i) {
                      final item = _items[i];
                      final imgUrl = ApiConfig.resolveImageUrl(item.imagePath);
                      final stateColor = _getStatusColor(item);

                      return Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: AppTheme.line),
                        ),
                        child: InkWell(
                          onTap: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => ProductDetailsPage(
                                  product: ProductModel(
                                    id: item.id,
                                    name: item.name,
                                    description: '',
                                    price: item.price,
                                    displayPriceLabel: item.displayPriceLabel,
                                    stock: item.stock,
                                    category: item.category ?? 'General',
                                    imagePath: item.imagePath,
                                    sellerName: item.sellerName,
                                    isWatched: true,
                                    variations: item.variations,
                                  ),
                                ),
                              ),
                            ).then((_) => _fetchWatchlist());
                          },
                          borderRadius: BorderRadius.circular(12),
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Row(
                              children: [
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(8),
                                  child: Container(
                                    width: 70,
                                    height: 70,
                                    color: Colors.grey.shade100,
                                    child: imgUrl != null
                                        ? CachedNetworkImage(
                                            imageUrl: imgUrl,
                                            fit: BoxFit.cover,
                                            errorWidget: (_, __, ___) => const Icon(Icons.image_not_supported_outlined),
                                          )
                                        : const Icon(Icons.shopping_bag_outlined),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        item.name,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                      ),
                                      const SizedBox(height: 3),
                                      Text(
                                        item.formattedPrice,
                                        style: const TextStyle(
                                          color: AppTheme.primary,
                                          fontWeight: FontWeight.bold,
                                          fontSize: 14,
                                        ),
                                      ),
                                      const SizedBox(height: 6),
                                      Row(
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
                                            decoration: BoxDecoration(
                                              color: stateColor.withAlpha(25),
                                              borderRadius: BorderRadius.circular(4),
                                              border: Border.all(color: stateColor.withAlpha(70)),
                                            ),
                                            child: Text(
                                              _getStatusLabel(item),
                                              style: TextStyle(
                                                color: stateColor,
                                                fontSize: 10,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Text(
                                            item.stock > 0 ? '${item.stock} in stock' : 'Out of stock',
                                            style: TextStyle(
                                              fontSize: 11,
                                              fontWeight: FontWeight.w500,
                                              color: item.stock > 0 ? AppTheme.muted : Colors.red.shade700,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.notifications_off_outlined, color: Colors.red),
                                  tooltip: 'Remove Alert',
                                  onPressed: () => _removeAlert(item),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}

