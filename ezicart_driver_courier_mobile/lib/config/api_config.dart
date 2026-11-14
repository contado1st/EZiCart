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

  // Auth endpoints for Courier
  static String get loginEndpoint => '$baseUrl/courier/login';
  static String get meEndpoint => '$baseUrl/courier/me';
  static String get logoutEndpoint => '$baseUrl/courier/logout';

  // Courier Operations endpoints
  static String get dashboardEndpoint => '$baseUrl/courier/dashboard';
  static String get rotateBadgeEndpoint => '$baseUrl/courier/badge/rotate';
  static String orderDetailEndpoint(int id) => '$baseUrl/courier/orders/$id';
  static String claimPickupEndpoint(int id) => '$baseUrl/courier/orders/$id/claim';
  static String declinePickupEndpoint(int id) => '$baseUrl/courier/orders/$id/decline-pickup';
  static String arrivePickupEndpoint(int id) => '$baseUrl/courier/orders/$id/arrive';
  static String confirmPickupEndpoint(int id) => '$baseUrl/courier/orders/$id/confirm-pickup';
  static String acceptDeliveryAssignmentEndpoint(int id) => '$baseUrl/courier/orders/$id/accept-assignment';
  static String startDeliveryEndpoint(int id) => '$baseUrl/courier/orders/$id/start-delivery';
  static String declineDeliveryAssignmentEndpoint(int id) => '$baseUrl/courier/orders/$id/decline-delivery-assignment';
  static String completeDeliveryEndpoint(int id) => '$baseUrl/courier/orders/$id/complete-delivery';
  static String failDeliveryEndpoint(int id) => '$baseUrl/courier/orders/$id/fail-delivery';
  static String confirmReturnDeliveryEndpoint(int id) => '$baseUrl/courier/orders/$id/confirm-return-delivery';
  static String get historyEndpoint => '$baseUrl/courier/history';
  static String get trackingEndpoint => '$baseUrl/courier/tracking';
  static String get earningsEndpoint => '$baseUrl/courier/earnings';
  static String remittanceEndpoint(int id) => '$baseUrl/courier/orders/$id/remittance';
  static String orderMessagesEndpoint(int id) => '$baseUrl/courier/orders/$id/messages';
}
