import 'package:flutter/material.dart';
import '../utils/safe_convert.dart';

class CategoryModel {
  final String name;
  final String icon;
  final int productCount;

  CategoryModel({
    required this.name,
    required this.icon,
    this.productCount = 0,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      name: json['name']?.toString() ?? '',
      icon: json['icon']?.toString() ?? 'category',
      productCount: parseInt(json['product_count']),
    );
  }

  static IconData getIcon(String categoryName) {
    final lower = categoryName.toLowerCase().trim();
    if (lower.contains('pet')) return Icons.pets_outlined;
    if (lower.contains('electronic') || lower.contains('gadget') || lower.contains('device') || lower.contains('mobile')) return Icons.devices_outlined;
    if (lower.contains('women')) return Icons.woman_outlined;
    if (lower.contains('men')) return Icons.man_outlined;
    if (lower.contains('apparel') || lower.contains('fashion') || lower.contains('clothing')) return Icons.checkroom_outlined;
    if (lower.contains('kid') || lower.contains('baby') || lower.contains('child')) return Icons.child_care_outlined;
    if (lower.contains('garden') || lower.contains('home') || lower.contains('living')) return Icons.yard_outlined;
    if (lower.contains('sport') || lower.contains('outdoor') || lower.contains('fitness')) return Icons.sports_basketball_outlined;
    if (lower.contains('beauty') || lower.contains('health') || lower.contains('wellness')) return Icons.spa_outlined;
    if (lower.contains('book') || lower.contains('media')) return Icons.menu_book_outlined;
    if (lower.contains('food') || lower.contains('gourmet') || lower.contains('grocer')) return Icons.restaurant_outlined;
    if (lower.contains('furniture') || lower.contains('office')) return Icons.chair_outlined;
    if (lower.contains('jewel') || lower.contains('watch')) return Icons.watch_outlined;
    return Icons.category_outlined;
  }

  static Color getColor(String categoryName) {
    final lower = categoryName.toLowerCase().trim();
    if (lower.contains('pet')) return const Color(0xFFE65100);
    if (lower.contains('electronic') || lower.contains('gadget')) return const Color(0xFF0288D1);
    if (lower.contains('women')) return const Color(0xFFD81B60);
    if (lower.contains('men')) return const Color(0xFF3949AB);
    if (lower.contains('kid') || lower.contains('baby')) return const Color(0xFFFB8C00);
    if (lower.contains('garden') || lower.contains('home')) return const Color(0xFF43A047);
    if (lower.contains('sport')) return const Color(0xFF00ACC1);
    if (lower.contains('beauty') || lower.contains('health')) return const Color(0xFFE91E63);
    if (lower.contains('book')) return const Color(0xFF8E24AA);
    if (lower.contains('food') || lower.contains('gourmet')) return const Color(0xFFF4511E);
    if (lower.contains('furniture')) return const Color(0xFF6D4C41);
    if (lower.contains('jewel') || lower.contains('watch')) return const Color(0xFFFFB300);
    return const Color(0xFF1E88E5);
  }
}
