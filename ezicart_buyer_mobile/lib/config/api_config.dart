import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiConfig {
  static String get defaultBaseUrl {
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      // Android emulator reaches host machine localhost at 10.0.2.2
      return 'http://10.0.2.2:8000/api';
    }
    // Using 127.0.0.1 avoids IPv6 resolution latency/refusal on Windows
    return 'http://127.0.0.1:8000/api';
  }

  static String? _customBaseUrl;

  static String get baseUrl => _customBaseUrl ?? defaultBaseUrl;

  static Future<void> loadBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final customUrl = prefs.getString('custom_api_base_url');
    if (customUrl != null && customUrl.trim().isNotEmpty) {
      var migrated = customUrl.trim();
      if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
        migrated = migrated
            .replaceAll('localhost:8000', '10.0.2.2:8000')
            .replaceAll('127.0.0.1:8000', '10.0.2.2:8000');
      } else {
        migrated = migrated.replaceAll('localhost:8000', '127.0.0.1:8000');
      }
      _customBaseUrl = migrated;
      await prefs.setString('custom_api_base_url', migrated);
    }
  }

  static Future<void> setBaseUrl(String newUrl) async {
    final cleanUrl = newUrl.trim().replaceAll(RegExp(r'/+$'), '');
    _customBaseUrl = cleanUrl;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('custom_api_base_url', cleanUrl);
  }

  static Future<void> resetBaseUrl() async {
    _customBaseUrl = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('custom_api_base_url');
  }

  /// Helper to convert relative or absolute image path to full URL
  static String? resolveImageUrl(String? path) {
    if (path == null || path.trim().isEmpty) return null;
    var trimmed = path.trim().replaceAll(r'\', '/');
    final root = baseUrl.replaceAll(RegExp(r'/api(/v\d+)?$'), '');

    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
        final rootUri = Uri.tryParse(root);
        final targetHost = rootUri?.host ?? '10.0.2.2';
        final targetPort = (rootUri != null && rootUri.hasPort) ? rootUri.port : 8000;
        final parsed = Uri.tryParse(trimmed);
        if (parsed != null && (parsed.host == '127.0.0.1' || parsed.host == 'localhost')) {
          trimmed = trimmed.replaceFirst('${parsed.host}:${parsed.port}', '$targetHost:$targetPort');
        }
      }
      return trimmed;
    }

    var cleanPath = trimmed.replaceFirst(RegExp(r'^/+'), '');
    if (cleanPath.startsWith('public/')) {
      cleanPath = cleanPath.substring(7);
    }
    if (cleanPath.startsWith('images/')) {
      return '$root/$cleanPath';
    }
    if (cleanPath.startsWith('storage/')) {
      return '$root/$cleanPath';
    }
    return '$root/storage/$cleanPath';
  }

  // Auth endpoints
  static String get loginEndpoint => '$baseUrl/auth/login';
  static String get registerEndpoint => '$baseUrl/auth/register';
  static String get verifyOtpEndpoint => '$baseUrl/auth/verify-email';
  static String get resendOtpEndpoint => '$baseUrl/auth/resend-verification';
  static String get forgotPasswordEndpoint => '$baseUrl/auth/forgot-password';
  static String get resetPasswordEndpoint => '$baseUrl/auth/reset-password';
  static String get meEndpoint => '$baseUrl/auth/me';
  static String get logoutEndpoint => '$baseUrl/auth/logout';

  // Catalog endpoints
  static String get productsEndpoint => '$baseUrl/products';
  static String get categoriesEndpoint => '$baseUrl/categories';
  static String productDetailEndpoint(int id) => '$baseUrl/products/$id';
  static String searchSuggestionsEndpoint(String q) => '$baseUrl/products/suggestions?q=${Uri.encodeComponent(q)}';

  // Shop / Storefront endpoints
  static String shopDetailsEndpoint(int id) => '$baseUrl/shops/$id';
  static String shopFollowEndpoint(int id) => '$baseUrl/shops/$id/follow';
  static String get followedShopsEndpoint => '$baseUrl/buyer/followed-shops';

  // Cart & Pricing endpoints
  static String get cartEndpoint => '$baseUrl/cart';
  static String get cartItemsEndpoint => '$baseUrl/cart/items';
  static String cartItemEndpoint(String key) => '$baseUrl/cart/items/$key';
  static String get cartSyncEndpoint => '$baseUrl/cart/sync';
  static String get cartQuoteEndpoint => '$baseUrl/cart/quote';

  // Checkout & Orders endpoints
  static String get checkoutEndpoint => '$baseUrl/checkout';
  static String get ordersEndpoint => '$baseUrl/orders';
  static String orderDetailEndpoint(int id) => '$baseUrl/orders/$id';
  static String confirmOrderEndpoint(int id) => '$baseUrl/orders/$id/confirm';
  static String cancelOrderEndpoint(int id) => '$baseUrl/orders/$id/cancel';
  static String refreshDeliveryCodeEndpoint(int id) => '$baseUrl/orders/$id/delivery-code/refresh';
  static String reviewOrderEndpoint(int id) => '$baseUrl/orders/$id/review';

  // Notifications endpoints
  static String get notificationsEndpoint => '$baseUrl/notifications';
  static String get notificationsUnreadCountEndpoint => '$baseUrl/notifications/unread-count';
  static String get notificationsReadAllEndpoint => '$baseUrl/notifications/read-all';
  static String notificationReadEndpoint(String id) => '$baseUrl/notifications/$id/read';
  static String get notificationPreferencesEndpoint => '$baseUrl/notifications/preferences';

  // Address book & PSGC endpoints
  static String get addressesEndpoint => '$baseUrl/addresses';
  static String addressDetailEndpoint(int id) => '$baseUrl/addresses/$id';
  static String addressDefaultEndpoint(int id) => '$baseUrl/addresses/$id/default';
  static String get psgcAddressesEndpoint => '$baseUrl/psgc-addresses';

  // Vouchers endpoints
  static String get vouchersEndpoint => '$baseUrl/vouchers';
  static String voucherClaimEndpoint(int id) => '$baseUrl/vouchers/$id/claim';

  // Discovery & Stock alerts endpoints
  static String get discoveryForYouEndpoint => '$baseUrl/discovery/for-you';
  static String get discoveryResetHistoryEndpoint => '$baseUrl/discovery/history';
  static String get stockAlertsEndpoint => '$baseUrl/stock-alerts';
  static String watchStockEndpoint(int id) => '$baseUrl/stock-alerts/$id';
  static String unwatchStockEndpoint(int id) => '$baseUrl/stock-alerts/$id';

  // Disputes & Returns endpoints
  static String get disputesEndpoint => '$baseUrl/disputes';
  static String orderDisputeEndpoint(int id) => '$baseUrl/orders/$id/dispute';

  // Messaging & Chat endpoints
  static String get chatThreadsEndpoint => '$baseUrl/chat/threads';
  static String chatConversationEndpoint(int id) => '$baseUrl/chat/$id';
  static String chatSendMessageEndpoint(int id) => '$baseUrl/chat/$id/messages';
  static String chatStartEndpoint(int sellerId) => '$baseUrl/chat/start/$sellerId';

  // Account endpoints
  static String get updateProfileEndpoint => '$baseUrl/account/profile';
  static String get updatePasswordEndpoint => '$baseUrl/account/password';
}
