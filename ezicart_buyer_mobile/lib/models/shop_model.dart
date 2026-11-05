import '../utils/safe_convert.dart';

class ShopModel {
  final int id;
  final String name;
  final String? businessName;
  final String ownerName;
  final String description;
  final String location;
  final double rating;
  final int reviewCount;
  final int productCount;
  final int followerCount;
  final bool isFollowing;
  final String? memberSince;
  final List<String> categories;

  ShopModel({
    required this.id,
    required this.name,
    this.businessName,
    required this.ownerName,
    required this.description,
    required this.location,
    required this.rating,
    required this.reviewCount,
    required this.productCount,
    required this.followerCount,
    required this.isFollowing,
    this.memberSince,
    this.categories = const [],
  });

  factory ShopModel.fromJson(Map<String, dynamic> json) {
    var rawCategories = json['categories'] as List<dynamic>? ?? [];
    var cats = rawCategories.map((c) => c.toString()).toList();

    return ShopModel(
      id: parseInt(json['id']),
      name: json['name']?.toString() ?? 'Merchant Shop',
      businessName: json['business_name']?.toString(),
      ownerName: json['owner_name']?.toString() ?? 'Store Owner',
      description: json['description']?.toString() ?? '',
      location: json['location']?.toString() ?? 'Philippines',
      rating: parseDouble(json['rating'], 5.0),
      reviewCount: parseInt(json['review_count']),
      productCount: parseInt(json['product_count']),
      followerCount: parseInt(json['follower_count']),
      isFollowing: json['is_following'] == true,
      memberSince: json['member_since']?.toString(),
      categories: cats,
    );
  }

  ShopModel copyWith({
    bool? isFollowing,
    int? followerCount,
  }) {
    return ShopModel(
      id: id,
      name: name,
      businessName: businessName,
      ownerName: ownerName,
      description: description,
      location: location,
      rating: rating,
      reviewCount: reviewCount,
      productCount: productCount,
      followerCount: followerCount ?? this.followerCount,
      isFollowing: isFollowing ?? this.isFollowing,
      memberSince: memberSince,
      categories: categories,
    );
  }
}

