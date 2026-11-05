class StockAlertProduct {
  final int id;
  final String name;
  final double price;
  final String? imagePath;
  final int stock;
  final String? category;
  final String sellerName;
  final String availabilityState; // 'available', 'low', 'unavailable'
  final bool isWatched;

  const StockAlertProduct({
    required this.id,
    required this.name,
    required this.price,
    this.imagePath,
    required this.stock,
    this.category,
    required this.sellerName,
    required this.availabilityState,
    required this.isWatched,
  });

  factory StockAlertProduct.fromJson(Map<String, dynamic> json) {
    return StockAlertProduct(
      id: (json['id'] is num) ? (json['id'] as num).toInt() : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      name: json['name']?.toString() ?? 'Product',
      price: (json['price'] is num) ? (json['price'] as num).toDouble() : double.tryParse(json['price']?.toString() ?? '') ?? 0.0,
      imagePath: json['image_path']?.toString(),
      stock: (json['stock'] is num) ? (json['stock'] as num).toInt() : int.tryParse(json['stock']?.toString() ?? '') ?? 0,
      category: json['category']?.toString(),
      sellerName: json['seller_name']?.toString() ?? 'Merchant',
      availabilityState: json['availability_state']?.toString() ?? 'available',
      isWatched: json['is_watched'] == true,
    );
  }
}

