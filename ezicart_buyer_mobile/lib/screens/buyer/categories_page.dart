import 'dart:convert';
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/category_model.dart';
import '../../services/api_service.dart';

class CategoriesPage extends StatefulWidget {
  final Function(String category)? onSelectCategory;

  const CategoriesPage({super.key, this.onSelectCategory});

  @override
  State<CategoriesPage> createState() => _CategoriesPageState();
}

class _CategoriesPageState extends State<CategoriesPage> {
  List<CategoryModel> _categories = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchCategories();
  }

  Future<void> _fetchCategories() async {
    try {
      final res = await ApiService.get(ApiConfig.categoriesEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((c) => CategoryModel.fromJson(c as Map<String, dynamic>))
              .toList();
          if (mounted) {
            setState(() {
              _categories = list;
              _isLoading = false;
            });
            return;
          }
        }
      }
    } catch (_) {}

    if (mounted) {
      setState(() {
        _categories = [
          CategoryModel(name: 'Pet Supplies', icon: 'pets', productCount: 0),
          CategoryModel(name: 'Electronics and Gadgets', icon: 'devices', productCount: 0),
          CategoryModel(name: "Women's Apparel", icon: 'woman', productCount: 0),
          CategoryModel(name: "Men's Apparel", icon: 'man', productCount: 0),
          CategoryModel(name: 'Kids and Baby', icon: 'child_care', productCount: 0),
          CategoryModel(name: 'Home and Garden', icon: 'yard', productCount: 0),
          CategoryModel(name: 'Sports and Outdoors', icon: 'sports_basketball', productCount: 0),
          CategoryModel(name: 'Health and Beauty', icon: 'spa', productCount: 0),
          CategoryModel(name: 'Books and Media', icon: 'menu_book', productCount: 0),
          CategoryModel(name: 'Food and Gourmet', icon: 'restaurant', productCount: 0),
          CategoryModel(name: 'Furniture and Office Equipment', icon: 'chair', productCount: 0),
          CategoryModel(name: 'Jewelry and Watches', icon: 'watch', productCount: 0),
        ];
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      backgroundColor: AppTheme.bg,
      appBar: AppBar(
        title: const Text('All Categories'),
        elevation: 0,
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _fetchCategories,
          child: GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 0.85,
            ),
            itemCount: _categories.length,
            itemBuilder: (context, i) {
              final cat = _categories[i];
              final iconData = CategoryModel.getIcon(cat.name);
              final iconColor = CategoryModel.getColor(cat.name);

              return Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                elevation: 0,
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () {
                    if (widget.onSelectCategory != null) {
                      widget.onSelectCategory!(cat.name);
                    } else {
                      Navigator.of(context).pop(cat.name);
                    }
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppTheme.line),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: iconColor.withValues(alpha: 0.1),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            iconData,
                            color: iconColor,
                            size: 26,
                          ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          cat.name,
                          textAlign: TextAlign.center,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 11.5,
                            color: AppTheme.ink,
                            height: 1.2,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${cat.productCount} items',
                          style: const TextStyle(
                            fontSize: 10,
                            color: AppTheme.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
