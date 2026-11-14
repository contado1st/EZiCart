import '../utils/safe_convert.dart';

class ProductVariation {
  final int id;
  final String type;
  final String value;
  final double priceAdjustment;
  final int stock;
  final String? imagePath;

  ProductVariation({
    required this.id,
    required this.type,
    required this.value,
    required this.priceAdjustment,
    required this.stock,
    this.imagePath,
  });

  factory ProductVariation.fromJson(Map<String, dynamic> json) {
    return ProductVariation(
      id: parseInt(json['id']),
      type: json['type']?.toString() ?? '',
      value: json['value']?.toString() ?? '',
      priceAdjustment: parseDouble(json['price_adjustment']),
      stock: parseInt(json['stock']),
      imagePath: json['image_path'] as String?,
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
  final int salesCount;
  final List<ProductVariation> variations;
  final List<ReviewItemModel> reviews;
  final List<String> galleryImages;

  int get reviewCount => reviewsCount;

  List<String> get allImages {
    final list = <String>[];
    for (final img in galleryImages) {
      final t = img.trim();
      if (t.isNotEmpty && !list.contains(t)) {
        list.add(t);
      }
    }
    if (imagePath != null && imagePath!.trim().isNotEmpty && !list.contains(imagePath!.trim())) {
      list.insert(0, imagePath!.trim());
    }
    return list;
  }

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
    this.rating = 0.0,
    this.reviewsCount = 0,
    this.salesCount = 0,
    required this.variations,
    this.reviews = const [],
    this.galleryImages = const [],
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

    // Parse gallery media images
    List<String> allImages = [];
    final baseImg = json['image_path'] as String?;
    if (baseImg != null && baseImg.trim().isNotEmpty) {
      allImages.add(baseImg.trim());
    }

    var rawMedia = json['media'] as List<dynamic>? ?? [];
    for (var m in rawMedia) {
      if (m is Map) {
        final path = m['path']?.toString();
        final kind = m['kind']?.toString() ?? 'image';
        if (path != null && path.trim().isNotEmpty && kind != 'video') {
          if (!allImages.contains(path.trim())) {
            allImages.add(path.trim());
          }
        }
      }
    }

    // Also support raw gallery_images list if present
    if (json['gallery_images'] is List) {
      for (var img in json['gallery_images']) {
        final str = img?.toString();
        if (str != null && str.trim().isNotEmpty && !allImages.contains(str.trim())) {
          allImages.add(str.trim());
        }
      }
    }

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
      imagePath: baseImg ?? (allImages.isNotEmpty ? allImages.first : null),
      sellerId: parseNullableInt(json['seller']?['id'] ?? json['user_id']),
      sellerName: seller,
      rating: parseDouble(json['rating'], 0.0),
      reviewsCount: parseInt(json['reviews_count'], parsedReviews.length),
      salesCount: parseInt(json['sales_count'] ?? json['sold_count'], 0),
      variations: parsedVariations,
      reviews: parsedReviews,
      galleryImages: allImages,
    );
  }
}
