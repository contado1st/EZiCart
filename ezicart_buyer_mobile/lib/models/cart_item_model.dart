import 'product_model.dart';

class CartItemModel {
  final ProductModel product;
  final ProductVariation? variation;
  int quantity;

  CartItemModel({required this.product, this.variation, this.quantity = 1});

  String get cartKey =>
      '${product.id}${variation != null ? "_${variation!.id}" : ""}';

  double get unitPrice => product.price + (variation?.priceAdjustment ?? 0.0);

  double get total => unitPrice * quantity;

  Map<String, dynamic> toLineMap() => {
    'product_id': product.id,
    'variation_id': variation?.id,
    'quantity': quantity,
    'price': unitPrice,
  };
}
