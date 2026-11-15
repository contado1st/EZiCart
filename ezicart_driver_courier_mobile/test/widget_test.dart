import 'package:flutter_test/flutter_test.dart';
import 'package:ezicart_driver_courier_mobile/config/api_config.dart';
import 'package:ezicart_driver_courier_mobile/models/user_model.dart';
import 'package:ezicart_driver_courier_mobile/models/order_model.dart';
import 'package:ezicart_driver_courier_mobile/models/allocation_model.dart';
import 'package:ezicart_driver_courier_mobile/main.dart';

void main() {
  test('ApiConfig courier endpoints and URL resolver test', () {
    expect(ApiConfig.baseUrl, isNotEmpty);
    expect(ApiConfig.loginEndpoint, contains('/courier/login'));
    expect(ApiConfig.dashboardEndpoint, contains('/courier/dashboard'));
    expect(ApiConfig.historyEndpoint, contains('/courier/history'));
    expect(ApiConfig.earningsEndpoint, contains('/courier/earnings'));
    expect(ApiConfig.forgotPasswordEndpoint, contains('/courier/forgot-password'));
    expect(ApiConfig.resetPasswordEndpoint, contains('/courier/reset-password'));
    expect(ApiConfig.changePasswordEndpoint, contains('/courier/password'));
    expect(ApiConfig.orderDetailEndpoint(123), contains('/courier/orders/123'));

    final fullUrl = ApiConfig.resolveImageUrl('https://example.com/proof.jpg');
    expect(fullUrl, 'https://example.com/proof.jpg');

    final relativeStorage = ApiConfig.resolveImageUrl(
      'storage/delivery-proofs/item.jpg',
    );
    expect(relativeStorage, contains('/storage/delivery-proofs/item.jpg'));

    final emptyUrl = ApiConfig.resolveImageUrl(null);
    expect(emptyUrl, isNull);
  });

  test('UserModel courier deserialization test', () {
    final json = {
      'id': '99',
      'first_name': 'Rider',
      'last_name': 'One',
      'email': 'rider@ezicart.trade',
      'role': 'courier',
      'status': 'approved',
      'badge_code': 'BADGE-XYZ-789',
      'vehicle_type': 'Motorcycle',
      'plate_number': 'ABC 1234',
      'vehicle_capacity_grams': '15000',
      'hub_transfer_only': false,
    };
    final user = UserModel.fromJson(json);
    expect(user.id, 99);
    expect(user.fullName, 'Rider One');
    expect(user.badgeCode, 'BADGE-XYZ-789');
    expect(user.vehicleType, 'Motorcycle');
    expect(user.plateNumber, 'ABC 1234');
    expect(user.vehicleCapacityGrams, 15000);
    expect(user.isApproved, true);
  });

  test('OrderModel and Tracking/Attempt deserialization test', () {
    final json = {
      'id': 105,
      'order_number': 'ORD-98765',
      'status': 'OUT_FOR_DELIVERY',
      'status_label': 'OUT FOR DELIVERY',
      'payment_method': 'COD',
      'total_amount': 450.50,
      'parcel_code': 'EZP-2026-001',
      'recipient_name': 'Buyer Juan',
      'recipient_contact': '09123456789',
      'delivery_address': 'Zone 1, Barangay Central, City',
      'seller': {
        'id': 12,
        'name': 'Sample Store',
        'contact_no': '09987654321',
        'address': 'Main Street, Hub',
      },
      'items': [
        {
          'id': 1,
          'product_name': 'Wireless Mouse',
          'quantity': 2,
          'unit_price': 225.25,
        },
      ],
      'tracking_events': [
        {
          'id': 1,
          'event_type': 'out_for_delivery',
          'status': 'OUT_FOR_DELIVERY',
          'location': 'Central Hub',
          'created_at': '2026-11-14T08:00:00Z',
        },
      ],
      'delivery_attempts': [
        {
          'id': 1,
          'attempt_no': 1,
          'outcome': 'failed',
          'reason': 'recipient_unavailable',
        },
      ],
    };

    final order = OrderModel.fromJson(json);
    expect(order.id, 105);
    expect(order.orderNumber, 'ORD-98765');
    expect(order.isCod, true);
    expect(order.items.length, 1);
    expect(order.items.first.productName, 'Wireless Mouse');
    expect(order.trackingEvents.length, 1);
    expect(order.deliveryAttempts.length, 1);
    expect(order.deliveryAttempts.first.outcome, 'failed');
  });

  test('AllocationModel deserialization test', () {
    final json = {
      'id': 7,
      'order_number': 'ORD-98765',
      'component': 'doorstep_delivery',
      'amount_cents': 6500,
      'created_at': '2026-11-14',
    };
    final alloc = AllocationModel.fromJson(json);
    expect(alloc.id, 7);
    expect(alloc.orderNumber, 'ORD-98765');
    expect(alloc.amountPesos, 65.0);
    expect(alloc.component, 'doorstep delivery');
  });

  testWidgets('App root widget smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const EZiCartCourierApp());
    expect(find.byType(EZiCartCourierApp), findsOneWidget);
  });
}
