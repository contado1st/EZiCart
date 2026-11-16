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
  static String get forgotPasswordEndpoint => '$baseUrl/courier/forgot-password';
  static String get resetPasswordEndpoint => '$baseUrl/courier/reset-password';
  static String get changePasswordEndpoint => '$baseUrl/courier/password';

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
  static String orderRetryProposalEndpoint(int id) => '$baseUrl/courier/orders/$id/retry-proposals';
  static String confirmReturnDeliveryEndpoint(int id) => '$baseUrl/courier/orders/$id/confirm-return-delivery';
  static String get historyEndpoint => '$baseUrl/courier/history';
  static String get trackingEndpoint => '$baseUrl/courier/tracking';
  static String get earningsEndpoint => '$baseUrl/courier/earnings';
  static String remittanceEndpoint(int id) => '$baseUrl/courier/orders/$id/remittance';
  static String orderMessagesEndpoint(int id) => '$baseUrl/courier/orders/$id/messages';

  // Work Availability
  static String get courierWorkAvailabilityEndpoint => '$baseUrl/courier/work-availability';

  // Weight & Load Planning
  static String get courierLoadPlanningEndpoint => '$baseUrl/courier/load-planning';

  // Automatic Dispatch
  static String get courierDispatchOffersEndpoint => '$baseUrl/courier/dispatch-offers';
  static String get courierDispatchPositionEndpoint => '$baseUrl/courier/dispatch-offers/position';
  static String courierRespondDispatchOfferEndpoint(int offerId) => '$baseUrl/courier/dispatch-offers/$offerId/respond';

  // Approved Return Pickups
  static String get courierReturnPickupsEndpoint => '$baseUrl/courier/return-pickups';
  static String courierAcceptReturnPickupEndpoint(int orderId) => '$baseUrl/courier/return-pickups/$orderId/accept';
  static String courierCollectReturnPickupEndpoint(int orderId) => '$baseUrl/courier/return-pickups/$orderId/collect';

  // Finance & Remittance Batches
  static String get courierRemittanceBatchesEndpoint => '$baseUrl/courier/finance/remittance-batches';
  static String get courierSubmitRemittanceBatchEndpoint => '$baseUrl/courier/finance/remittance-batches';

  // Profile & Appearance
  static String get courierProfileEndpoint => '$baseUrl/courier/profile';
  static String get updateProfileEndpoint => '$baseUrl/account/profile';
  static String get updateAppearanceEndpoint => '$baseUrl/account/appearance';
  static String profileMediaEndpoint(String type) => '$baseUrl/account/profile/media/$type';

  // Addresses & PSGC Locations
  static String get psgcAddressesEndpoint => '$baseUrl/psgc-addresses';
  static String get addressesEndpoint => '$baseUrl/addresses';
  static String addressDetailEndpoint(int id) => '$baseUrl/addresses/$id';
  static String addressDefaultEndpoint(int id) => '$baseUrl/addresses/$id/default';

  // Notifications
  static String get notificationsEndpoint => '$baseUrl/notifications';
  static String get notificationsUnreadCountEndpoint => '$baseUrl/notifications/unread-count';
  static String get markAllNotificationsReadEndpoint => '$baseUrl/notifications/read-all';
  static String markNotificationReadEndpoint(String id) => '$baseUrl/notifications/$id/read';
  static String get notificationPreferencesEndpoint => '$baseUrl/notifications/preferences';

  // Parcel Records (Journey & Evidence, Incidents & Claims)
  static String get parcelEvidenceEndpoint => '$baseUrl/parcel-evidence';
  static String orderEvidenceEndpoint(int id) => '$baseUrl/orders/$id/evidence';
  static String parcelEvidencePhotoEndpoint(int id) => '$baseUrl/parcel-evidence/$id/photo';
  static String get parcelIncidentsEndpoint => '$baseUrl/parcel-incidents';
  static String parcelIncidentDetailEndpoint(int id) => '$baseUrl/parcel-incidents/$id';
  static String parcelIncidentEvidenceEndpoint(int id, String which) => '$baseUrl/parcel-incidents/$id/evidence/$which';
  static String get returnPickupsEndpoint => '$baseUrl/return-pickups';
  static String returnPickupHandoverEndpoint(int id) => '$baseUrl/return-pickups/$id/handover';

  // Finance & Wallet
  static String get walletEndpoint => '$baseUrl/wallet';
  static String get walletDemoFundingEndpoint => '$baseUrl/wallet/demo-funding';
  static String get walletWithdrawEndpoint => '$baseUrl/wallet/withdraw';
  static String get financeRecordsEndpoint => '$baseUrl/finance/records';

  // Chat & Messaging
  static String get chatThreadsEndpoint => '$baseUrl/chat/threads';
  static String chatConversationEndpoint(int id) => '$baseUrl/chat/$id';
  static String chatSendMessageEndpoint(int id) => '$baseUrl/chat/$id/messages';
  static String chatUnsendMessageEndpoint(int conversationId, int messageId) => '$baseUrl/chat/$conversationId/messages/$messageId';
  static String chatUnsendAttachmentEndpoint(int conversationId, int attachmentId) => '$baseUrl/chat/$conversationId/attachments/$attachmentId';
  static String chatStartEndpoint(int userId) => '$baseUrl/chat/start/$userId';
  static String chatAttachmentEndpoint(int id) => '$baseUrl/chat/attachments/$id';
}
