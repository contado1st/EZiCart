import 'product_model.dart';

class StockAlertProduct {
  final int id;
  final String name;
  final double price;
  final String? displayPriceLabel;
  final String? imagePath;
  final int stock;
  final String? category;
  final String sellerName;
  final String availabilityState; // 'available', 'low', 'unavailable'
  final bool isWatched;
  final List<ProductVariation> variations;

  const StockAlertProduct({
    required this.id,
    required this.name,
    required this.price,
    this.displayPriceLabel,
    this.imagePath,
    required this.stock,
    this.category,
    required this.sellerName,
    required this.availabilityState,
    required this.isWatched,
    this.variations = const [],
  });

  bool get isOutOfStock => stock <= 0 || availabilityState == 'unavailable';
  bool get isLowStock => !isOutOfStock && (stock <= 5 || availabilityState == 'low');
  bool get isAvailable => !isOutOfStock && !isLowStock;

  /// Returns formatted price range or exact price matching Home products
  String get formattedPrice {
    if (displayPriceLabel != null && displayPriceLabel!.trim().isNotEmpty) {
      return displayPriceLabel!.trim();
    }
    if (variations.isNotEmpty) {
      final active = variations.where((v) => v.stock > 0).toList();
      if (active.isNotEmpty) {
        final prices = active.map((v) => price + v.priceAdjustment).toList();
        final minPrice = prices.reduce((a, b) => a < b ? a : b);
        final maxPrice = prices.reduce((a, b) => a > b ? a : b);
        final minStr = minPrice.toStringAsFixed(2);
        final maxStr = maxPrice.toStringAsFixed(2);
        return minStr == maxStr ? '₱$minStr' : '₱$minStr – ₱$maxStr';
      }
    }
    return '₱${price.toStringAsFixed(2)}';
  }

  factory StockAlertProduct.fromJson(Map<String, dynamic> json) {
    var rawVars = json['variations'] as List<dynamic>? ?? [];
    var parsedVars = rawVars
        .map((e) => ProductVariation.fromJson(e as Map<String, dynamic>))
        .toList();

    return StockAlertProduct(
      id: (json['id'] is num) ? (json['id'] as num).toInt() : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      name: json['name']?.toString() ?? 'Product',
      price: (json['price'] is num) ? (json['price'] as num).toDouble() : double.tryParse(json['price']?.toString() ?? '') ?? 0.0,
      displayPriceLabel: json['display_price_label']?.toString(),
      imagePath: json['image_path']?.toString(),
      stock: (json['stock'] is num)
          ? (json['stock'] as num).toInt()
          : (json['available_stock'] is num)
              ? (json['available_stock'] as num).toInt()
              : int.tryParse(json['stock']?.toString() ?? '') ?? 0,
      category: json['category']?.toString(),
      sellerName: json['seller_name']?.toString() ?? 'Merchant',
      availabilityState: json['availability_state']?.toString() ?? 'available',
      isWatched: json['is_watched'] == true,
      variations: parsedVars,
    );
  }
}

