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

class ProductMediaItem {
  final String path;
  final String kind; // 'image' or 'video'
  final String? mimeType;

  ProductMediaItem({
    required this.path,
    this.kind = 'image',
    this.mimeType,
  });

  bool get isVideo =>
      kind == 'video' ||
      path.toLowerCase().endsWith('.mp4') ||
      path.toLowerCase().endsWith('.webm') ||
      path.toLowerCase().endsWith('.mov') ||
      (mimeType != null && mimeType!.toLowerCase().startsWith('video/'));

  bool get isImage => !isVideo;

  factory ProductMediaItem.fromJson(Map<String, dynamic> json) {
    final path = json['path']?.toString() ?? '';
    final kind = json['kind']?.toString() ??
        (path.toLowerCase().endsWith('.mp4') ||
                path.toLowerCase().endsWith('.webm') ||
                path.toLowerCase().endsWith('.mov')
            ? 'video'
            : 'image');

    return ProductMediaItem(
      path: path,
      kind: kind,
      mimeType: json['mime_type']?.toString(),
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
  final List<ProductMediaItem> mediaItems;
  final String? displayPriceLabel;
  final bool isWishlisted;
  final bool isWatched;

  int get reviewCount => reviewsCount;

  /// Returns the estimated price range (e.g. "₱100.00 – ₱150.00") or exact price
  /// following the same calculation as the Laravel backend.
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

  /// Returns the total available stock across all active variations, or product stock
  int get availableStock {
    if (variations.isNotEmpty) {
      final active = variations.where((v) => v.stock > 0);
      if (active.isNotEmpty) {
        return active.fold(0, (sum, v) => sum + v.stock);
      }
    }
    return stock;
  }

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

  List<ProductMediaItem> get allMedia {
    final list = <ProductMediaItem>[];
    if (imagePath != null && imagePath!.trim().isNotEmpty) {
      list.add(ProductMediaItem(path: imagePath!.trim(), kind: 'image'));
    }
    for (final m in mediaItems) {
      if (m.path.trim().isNotEmpty && !list.any((x) => x.path.trim() == m.path.trim())) {
        list.add(m);
      }
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
    this.mediaItems = const [],
    this.displayPriceLabel,
    this.isWishlisted = false,
    this.isWatched = false,
  });

  ProductModel copyWith({
    int? id,
    String? name,
    String? description,
    String? category,
    double? price,
    int? stock,
    String? imagePath,
    int? sellerId,
    String? sellerName,
    double? rating,
    int? reviewsCount,
    int? salesCount,
    List<ProductVariation>? variations,
    List<ReviewItemModel>? reviews,
    List<String>? galleryImages,
    List<ProductMediaItem>? mediaItems,
    String? displayPriceLabel,
    bool? isWishlisted,
    bool? isWatched,
  }) {
    return ProductModel(
      id: id ?? this.id,
      name: name ?? this.name,
      description: description ?? this.description,
      category: category ?? this.category,
      price: price ?? this.price,
      stock: stock ?? this.stock,
      imagePath: imagePath ?? this.imagePath,
      sellerId: sellerId ?? this.sellerId,
      sellerName: sellerName ?? this.sellerName,
      rating: rating ?? this.rating,
      reviewsCount: reviewsCount ?? this.reviewsCount,
      salesCount: salesCount ?? this.salesCount,
      variations: variations ?? this.variations,
      reviews: reviews ?? this.reviews,
      galleryImages: galleryImages ?? this.galleryImages,
      mediaItems: mediaItems ?? this.mediaItems,
      displayPriceLabel: displayPriceLabel ?? this.displayPriceLabel,
      isWishlisted: isWishlisted ?? this.isWishlisted,
      isWatched: isWatched ?? this.isWatched,
    );
  }

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    var rawVariations = json['variations'] as List<dynamic>? ?? [];
    var parsedVariations = rawVariations
        .map((e) => ProductVariation.fromJson(e as Map<String, dynamic>))
        .toList();

    var rawReviews = json['reviews'] as List<dynamic>? ?? [];
    var parsedReviews = rawReviews
        .map((e) => ReviewItemModel.fromJson(e as Map<String, dynamic>))
        .toList();

    // Parse gallery media items (both images and videos)
    List<ProductMediaItem> parsedMedia = [];
    List<String> allImages = [];
    final baseImg = json['image_path'] as String?;
    if (baseImg != null && baseImg.trim().isNotEmpty) {
      allImages.add(baseImg.trim());
      parsedMedia.add(ProductMediaItem(path: baseImg.trim(), kind: 'image'));
    }

    var rawMedia = json['media'] as List<dynamic>? ?? [];
    for (var m in rawMedia) {
      if (m is Map) {
        final path = m['path']?.toString();
        final kind = m['kind']?.toString() ?? 'image';
        if (path != null && path.trim().isNotEmpty) {
          final item = ProductMediaItem(
            path: path.trim(),
            kind: kind,
            mimeType: m['mime_type']?.toString(),
          );
          if (!parsedMedia.any((x) => x.path.trim() == path.trim())) {
            parsedMedia.add(item);
          }
          if (item.isImage && !allImages.contains(path.trim())) {
            allImages.add(path.trim());
          }
        }
      }
    }

    // Also support raw gallery_images list if present
    if (json['gallery_images'] is List) {
      for (var img in json['gallery_images']) {
        final str = img?.toString();
        if (str != null && str.trim().isNotEmpty) {
          if (!allImages.contains(str.trim())) {
            allImages.add(str.trim());
          }
          if (!parsedMedia.any((x) => x.path.trim() == str.trim())) {
            parsedMedia.add(ProductMediaItem(path: str.trim(), kind: 'image'));
          }
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
      mediaItems: parsedMedia,
      displayPriceLabel: json['display_price_label']?.toString(),
      isWishlisted: json['is_wishlisted'] == true,
      isWatched: json['is_watched'] == true,
    );
  }
}
