import '../utils/safe_convert.dart';

class ShopModel {
  final int id;
  final String name;
  final String? displayName;
  final String? businessName;
  final String ownerName;
  final String description;
  final String about;
  final String? bio;
  final String? lineOfBusiness;
  final String location;
  final double rating;
  final int reviewCount;
  final int productCount;
  final int followerCount;
  final bool isFollowing;
  final String? memberSince;
  final String profileTheme;
  final String? avatarUrl;
  final String? bannerUrl;
  final bool hasAvatar;
  final bool hasBanner;
  final String lastActive;
  final bool isActiveRecently;
  final String shopAvailability;
  final String availabilityNote;
  final String? inactivityWarning;
  final String ratingNote;
  final List<String> categories;

  ShopModel({
    required this.id,
    required this.name,
    this.displayName,
    this.businessName,
    required this.ownerName,
    required this.description,
    this.about = '',
    this.bio,
    this.lineOfBusiness,
    required this.location,
    required this.rating,
    required this.reviewCount,
    required this.productCount,
    required this.followerCount,
    required this.isFollowing,
    this.memberSince,
    this.profileTheme = 'rose',
    this.avatarUrl,
    this.bannerUrl,
    this.hasAvatar = false,
    this.hasBanner = false,
    this.lastActive = 'Activity not recorded yet',
    this.isActiveRecently = false,
    this.shopAvailability = 'open',
    this.availabilityNote = 'Shop open',
    this.inactivityWarning,
    this.ratingNote = 'Rating includes reviews of approved, non-archived products, including items currently out of stock.',
    this.categories = const [],
  });

  factory ShopModel.fromJson(Map<String, dynamic> json) {
    var rawCategories = json['categories'] as List<dynamic>? ?? [];
    var cats = rawCategories.map((c) => c.toString()).toList();

    return ShopModel(
      id: parseInt(json['id']),
      name: json['name']?.toString() ?? 'Merchant Shop',
      displayName: json['display_name']?.toString() ?? json['name']?.toString(),
      businessName: json['business_name']?.toString(),
      ownerName: json['owner_name']?.toString() ?? 'Store Owner',
      description: json['description']?.toString() ?? '',
      about: json['about']?.toString() ?? json['bio']?.toString() ?? '',
      bio: json['bio']?.toString(),
      lineOfBusiness: json['line_of_business']?.toString(),
      location: json['location']?.toString() ?? 'Philippines',
      rating: parseDouble(json['rating'], 0.0),
      reviewCount: parseInt(json['review_count']),
      productCount: parseInt(json['product_count']),
      followerCount: parseInt(json['follower_count']),
      isFollowing: json['is_following'] == true,
      memberSince: json['member_since']?.toString(),
      profileTheme: json['profile_theme']?.toString() ?? 'rose',
      avatarUrl: json['avatar_url']?.toString(),
      bannerUrl: json['banner_url']?.toString(),
      hasAvatar: json['has_avatar'] == true || (json['avatar_url'] != null && json['avatar_url'].toString().isNotEmpty),
      hasBanner: json['has_banner'] == true || (json['banner_url'] != null && json['banner_url'].toString().isNotEmpty),
      lastActive: json['last_active']?.toString() ?? 'Activity not recorded yet',
      isActiveRecently: json['is_active_recently'] == true,
      shopAvailability: json['shop_availability']?.toString() ?? 'open',
      availabilityNote: json['availability_note']?.toString() ?? 'Shop open',
      inactivityWarning: json['inactivity_warning']?.toString(),
      ratingNote: json['rating_note']?.toString() ?? 'Rating includes reviews of approved, non-archived products, including items currently out of stock.',
      categories: cats,
    );
  }

  ShopModel copyWith({
    bool? isFollowing,
    int? followerCount,
    String? name,
    String? displayName,
    String? description,
    String? about,
  }) {
    return ShopModel(
      id: id,
      name: name ?? this.name,
      displayName: displayName ?? this.displayName,
      businessName: businessName,
      ownerName: ownerName,
      description: description ?? this.description,
      about: about ?? this.about,
      bio: bio,
      lineOfBusiness: lineOfBusiness,
      location: location,
      rating: rating,
      reviewCount: reviewCount,
      productCount: productCount,
      followerCount: followerCount ?? this.followerCount,
      isFollowing: isFollowing ?? this.isFollowing,
      memberSince: memberSince,
      profileTheme: profileTheme,
      avatarUrl: avatarUrl,
      bannerUrl: bannerUrl,
      hasAvatar: hasAvatar,
      hasBanner: hasBanner,
      lastActive: lastActive,
      isActiveRecently: isActiveRecently,
      shopAvailability: shopAvailability,
      availabilityNote: availabilityNote,
      inactivityWarning: inactivityWarning,
      ratingNote: ratingNote,
      categories: categories,
    );
  }
}
