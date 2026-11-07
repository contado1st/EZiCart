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
          setState(() {
            _categories = list;
            _isLoading = false;
          });
          return;
        }
      }
    } catch (_) {}
    setState(() {
      _categories = [
        CategoryModel(name: 'Beauty', icon: 'face', productCount: 0),
        CategoryModel(name: 'Home & Living', icon: 'home', productCount: 0),
        CategoryModel(name: 'Fashion', icon: 'checkroom', productCount: 0),
        CategoryModel(name: 'Electronics', icon: 'devices', productCount: 0),
        CategoryModel(name: 'Groceries', icon: 'shopping_basket', productCount: 0),
      ];
      _isLoading = false;
    });
  }

  IconData _getCategoryIcon(String name) {
    switch (name.toLowerCase()) {
      case 'beauty':
        return Icons.brush_outlined;
      case 'home & living':
        return Icons.home_outlined;
      case 'fashion':
        return Icons.checkroom_outlined;
      case 'electronics':
        return Icons.devices_outlined;
      case 'groceries':
        return Icons.shopping_basket_outlined;
      default:
        return Icons.category_outlined;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Product Categories'),
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _fetchCategories,
          child: GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 14,
              crossAxisSpacing: 14,
              childAspectRatio: 1.15,
            ),
            itemCount: _categories.length,
            itemBuilder: (context, i) {
              final cat = _categories[i];
              return Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: const BorderSide(color: AppTheme.line),
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () {
                    if (widget.onSelectCategory != null) {
                      widget.onSelectCategory!(cat.name);
                    } else {
                      Navigator.of(context).pop(cat.name);
                    }
                  },
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AppTheme.primarySoft,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(
                            _getCategoryIcon(cat.name),
                            color: AppTheme.primary,
                            size: 32,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          cat.name,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                            color: AppTheme.ink,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${cat.productCount} Items',
                          style: const TextStyle(
                            fontSize: 12,
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

