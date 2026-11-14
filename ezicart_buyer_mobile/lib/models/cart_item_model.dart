import 'product_model.dart';
import '../utils/safe_convert.dart';

class CartItemModel {
  final ProductModel product;
  final ProductVariation? variation;
  int quantity;
  final String? customKey;
  final bool available;
  final String? unavailableReason;

  CartItemModel({
    required this.product,
    this.variation,
    this.quantity = 1,
    this.customKey,
    this.available = true,
    this.unavailableReason,
  });

  String get cartKey =>
      customKey ?? '${product.id}${variation != null ? "_${variation!.id}" : ""}';

  double get unitPrice => product.price + (variation?.priceAdjustment ?? 0.0);

  double get total => unitPrice * quantity;

  String? get displayImage =>
      (variation?.imagePath != null && variation!.imagePath!.trim().isNotEmpty)
          ? variation!.imagePath
          : product.imagePath;

  int get availableStock =>
      variation != null ? variation!.stock : product.stock;

  Map<String, dynamic> toLineMap() => {
    'product_id': product.id,
    'variation_id': variation?.id,
    'quantity': quantity,
    'price': unitPrice,
  };

  factory CartItemModel.fromApiJson(Map<String, dynamic> json) {
    final prodId = parseInt(json['product_id']);
    final varId = json['variation_id'] != null ? parseInt(json['variation_id']) : null;
    final maxStock = parseInt(json['max_quantity'], 20);
    final price = parseDouble(json['price']);

    ProductVariation? variation;
    if (varId != null && varId > 0) {
      final info = json['variation_info']?.toString() ?? '';
      final parts = info.split(': ');
      final type = parts.isNotEmpty ? parts[0] : 'Variation';
      final value = parts.length > 1 ? parts[1] : '';
      variation = ProductVariation(
        id: varId,
        type: type,
        value: value,
        priceAdjustment: 0.0,
        stock: maxStock,
        imagePath: json['image_path'] as String?,
      );
    }

    final product = ProductModel(
      id: prodId,
      name: json['name']?.toString() ?? 'Product #$prodId',
      description: '',
      category: '',
      price: price,
      stock: maxStock,
      imagePath: json['image_path'] as String?,
      sellerId: json['seller_id'] != null ? parseInt(json['seller_id']) : null,
      sellerName: json['seller_name']?.toString() ?? 'Shop',
      variations: variation != null ? [variation] : [],
    );

    return CartItemModel(
      product: product,
      variation: variation,
      quantity: parseInt(json['quantity'], 1),
      customKey: json['cart_key']?.toString(),
      available: json['available'] != false,
      unavailableReason: json['unavailable_reason'] as String?,
    );
  }
}
