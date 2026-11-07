import 'package:flutter_test/flutter_test.dart';
import 'package:ezicart_buyer_mobile/config/api_config.dart';
import 'package:ezicart_buyer_mobile/models/user_model.dart';
import 'package:ezicart_buyer_mobile/models/product_model.dart';
import 'package:ezicart_buyer_mobile/models/order_model.dart';
import 'package:ezicart_buyer_mobile/models/address_model.dart';
import 'package:ezicart_buyer_mobile/models/category_model.dart';
import 'package:ezicart_buyer_mobile/main.dart';

void main() {
  test('ApiConfig default and URL resolver tests', () {
    expect(ApiConfig.baseUrl, isNotEmpty);

    final fullUrl = ApiConfig.resolveImageUrl('https://example.com/photo.jpg');
    expect(fullUrl, 'https://example.com/photo.jpg');

    final relativeStorage = ApiConfig.resolveImageUrl('storage/products/item.jpg');
    expect(relativeStorage, contains('/storage/products/item.jpg'));

    final relativeProduct = ApiConfig.resolveImageUrl('products/item.jpg');
    expect(relativeProduct, contains('/storage/products/item.jpg'));

    final emptyUrl = ApiConfig.resolveImageUrl(null);
    expect(emptyUrl, isNull);
  });

  test('UserModel deserialization test with string and numeric IDs', () {
    final json = {
      'id': '42',
      'first_name': 'Juan',
      'last_name': 'Dela Cruz',
      'email': 'juan@example.com',
      'age': '25',
      'role': 'buyer',
      'status': 'approved',
    };
    final user = UserModel.fromJson(json);
    expect(user.id, 42);
    expect(user.firstName, 'Juan');
    expect(user.name, 'Juan Dela Cruz');
    expect(user.age, 25);
  });

  test('ProductModel and Variation deserialization test', () {
    final json = {
      'id': '10',
      'name': 'Wireless Headphones',
      'description': 'Noise cancelling',
      'category': 'Electronics',
      'price': '1499.50',
      'stock': '15',
      'seller': {
        'id': '3',
        'business_name': 'Tech Hub',
      },
      'variations': [
        {
          'id': '101',
          'type': 'Color',
          'value': 'Black',
          'price_adjustment': '0',
          'stock': '10',
        }
      ],
    };
    final product = ProductModel.fromJson(json);
    expect(product.id, 10);
    expect(product.price, 1499.50);
    expect(product.sellerId, 3);
    expect(product.sellerName, 'Tech Hub');
    expect(product.variations.length, 1);
    expect(product.variations.first.id, 101);
  });

  test('OrderModel deserialization test', () {
    final json = {
      'id': '501',
      'parcel_code': 'EZC-2026-0001',
      'status': 'PLACED',
      'total_amount': '250.00',
      'created_at': '2026-11-02T00:00:00.000000Z',
      'seller': {'name': 'Shop 1'},
      'items': [
        {
          'id': '1',
          'product_id': '10',
          'name': 'Item A',
          'quantity': '2',
          'price': '125.00',
        }
      ],
      'tracking_timeline': [],
    };
    final order = OrderModel.fromJson(json);
    expect(order.id, 501);
    expect(order.totalAmount, 250.00);
    expect(order.items.length, 1);
    expect(order.items.first.quantity, 2);
  });

  test('AddressModel deserialization test', () {
    final json = {
      'id': '12',
      'label': 'Work',
      'recipient_name': 'Maria',
      'recipient_contact': '09123456789',
      'region': 'CALABARZON',
      'province': 'Laguna',
      'municipality': 'Calamba',
      'barangay': 'Real',
      'is_default': '1',
    };
    final address = AddressModel.fromJson(json);
    expect(address.id, 12);
    expect(address.label, 'Work');
    expect(address.isDefault, isTrue);
  });

  test('CategoryModel deserialization test', () {
    final json = {
      'name': 'Groceries',
      'icon': 'shopping_basket',
      'product_count': '45',
    };
    final cat = CategoryModel.fromJson(json);
    expect(cat.name, 'Groceries');
    expect(cat.productCount, 45);
  });

  testWidgets('App smoke test renders without uncaught exceptions', (tester) async {
    await tester.pumpWidget(const EZiCartApp());
    expect(find.byType(EZiCartApp), findsOneWidget);
  });
}

