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
}

