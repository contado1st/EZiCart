import '../utils/safe_convert.dart';

class ProductVariation {
  final int id;
  final String type;
  final String value;
  final double priceAdjustment;
  final int stock;

  ProductVariation({
    required this.id,
    required this.type,
    required this.value,
    required this.priceAdjustment,
    required this.stock,
  });

  factory ProductVariation.fromJson(Map<String, dynamic> json) {
    return ProductVariation(
      id: parseInt(json['id']),
      type: json['type']?.toString() ?? '',
      value: json['value']?.toString() ?? '',
      priceAdjustment: parseDouble(json['price_adjustment']),
      stock: parseInt(json['stock']),
    );
  }
}

class ReviewItemModel {
  final int id;
  final String userName;
  final int rating;
  final String? comment;
  final String createdAt;

  ReviewItemModel({
    required this.id,
    required this.userName,
    required this.rating,
    this.comment,
    required this.createdAt,
  });

  factory ReviewItemModel.fromJson(Map<String, dynamic> json) {
    String uName = 'Buyer';
    if (json['user'] is Map) {
      final user = json['user'] as Map<String, dynamic>;
      final fn = user['first_name']?.toString() ?? '';
      final ln = user['last_name']?.toString() ?? '';
      uName = '$fn $ln'.trim();
      if (uName.isEmpty) uName = 'Verified Buyer';
    } else if (json['user_name'] != null) {
      uName = json['user_name'].toString();
    }

    return ReviewItemModel(
      id: parseInt(json['id']),
      userName: uName,
      rating: parseInt(json['rating'], 5),
      comment: json['comment'] as String? ?? json['review'] as String?,
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class ProductModel {
  final int id;
  final String name;
  final String description;
  final String category;
  final double price;
  final int stock;
  final String? imagePath;
  final int? sellerId;
  final String sellerName;
  final double rating;
  final int reviewsCount;
  final List<ProductVariation> variations;
  final List<ReviewItemModel> reviews;

  int get reviewCount => reviewsCount;

  ProductModel({
    required this.id,
    required this.name,
    required this.description,
    required this.category,
    required this.price,
    required this.stock,
    this.imagePath,
    this.sellerId,
    required this.sellerName,
    this.rating = 5.0,
    this.reviewsCount = 0,
    required this.variations,
    this.reviews = const [],
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    var rawVariations = json['variations'] as List<dynamic>? ?? [];
    var parsedVariations = rawVariations
        .map((e) => ProductVariation.fromJson(e as Map<String, dynamic>))
        .toList();

    var rawReviews = json['reviews'] as List<dynamic>? ?? [];
    var parsedReviews = rawReviews
        .map((e) => ReviewItemModel.fromJson(e as Map<String, dynamic>))
        .toList();

    String seller = json['seller']?['business_name']?.toString() ?? '';
    if (seller.trim().isEmpty) {
      seller = '${json['seller']?['first_name'] ?? ''} ${json['seller']?['last_name'] ?? ''}'.trim();
    }
    if (seller.isEmpty) {
      seller = 'Merchant';
    }

    return ProductModel(
      id: parseInt(json['id']),
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      category: json['category']?.toString() ?? 'General',
      price: parseDouble(json['price']),
      stock: parseInt(json['stock']),
      imagePath: json['image_path'] as String?,
      sellerId: parseNullableInt(json['seller']?['id']),
      sellerName: seller,
      rating: parseDouble(json['rating'], 5.0),
      reviewsCount: parseInt(json['reviews_count'], parsedReviews.length),
      variations: parsedVariations,
      reviews: parsedReviews,
    );
  }
}
