import 'dart:convert';
import 'package:flutter/material.dart';
import '../config/api_config.dart';
import '../models/allocation_model.dart';
import '../models/order_model.dart';
import '../services/api_service.dart';

class CourierProvider extends ChangeNotifier {
  bool _isLoading = false;
  String? _errorMessage;
  String? _successMessage;

  Map<String, int> _stats = {
    'claimed_pickups': 0,
    'in_transit_hub': 0,
    'assigned_delivery': 0,
    'returning_to_seller': 0,
    'completed_today': 0,
    'failed_today': 0,
  };

  List<OrderModel> _availablePickups = [];
  List<OrderModel> _claimedPickups = [];
  List<OrderModel> _inTransitHub = [];
  List<OrderModel> _deliveries = [];
  List<OrderModel> _returns = [];
  List<OrderModel> _failedDeliveries = [];

  List<OrderModel> _historyOrders = [];
  List<OrderModel> _trackingOrders = [];
  List<AllocationModel> _allocations = [];
  double _availableEarnings = 0.0;

  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String? get successMessage => _successMessage;
  Map<String, int> get stats => _stats;

  List<OrderModel> get availablePickups => _availablePickups;
  List<OrderModel> get claimedPickups => _claimedPickups;
  List<OrderModel> get inTransitHub => _inTransitHub;
  List<OrderModel> get deliveries => _deliveries;
  List<OrderModel> get returns => _returns;
  List<OrderModel> get failedDeliveries => _failedDeliveries;

  List<OrderModel> get historyOrders => _historyOrders;
  List<OrderModel> get trackingOrders => _trackingOrders;
  List<AllocationModel> get allocations => _allocations;
  double get availableEarnings => _availableEarnings;

  void clearMessages() {
    _errorMessage = null;
    _successMessage = null;
    notifyListeners();
  }

  String _extractErrorMessage(dynamic data, String fallback) {
    if (data is Map) {
      if (data['errors'] is Map) {
        final errorMap = data['errors'] as Map;
        for (final val in errorMap.values) {
          if (val is List && val.isNotEmpty) {
            return val.first.toString();
          } else if (val is String && val.isNotEmpty) {
            return val;
          }
        }
      }
      if (data['message'] != null && data['message'].toString().isNotEmpty) {
        return data['message'].toString();
      }
    }
    return fallback;
  }

  Future<void> fetchDashboard() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.get(ApiConfig.dashboardEndpoint);
      _isLoading = false;

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['stats'] is Map) {
          _stats = (data['stats'] as Map).map(
            (k, v) => MapEntry(k.toString(), (v is num) ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0),
          );
        }

        if (data['available_pickups'] is List) {
          _availablePickups = (data['available_pickups'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
        if (data['claimed_pickups'] is List) {
          _claimedPickups = (data['claimed_pickups'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
        if (data['in_transit_hub'] is List) {
          _inTransitHub = (data['in_transit_hub'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
        if (data['deliveries'] is List) {
          _deliveries = (data['deliveries'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
        if (data['returns'] is List) {
          _returns = (data['returns'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
        if (data['failed_deliveries'] is List) {
          _failedDeliveries = (data['failed_deliveries'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
      } else {
        dynamic data;
        try {
          data = jsonDecode(res.body);
        } catch (_) {}
        _errorMessage = _extractErrorMessage(data, 'Failed to fetch dashboard data.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
  }

  Future<OrderModel?> fetchOrderDetails(int orderId) async {
    try {
      final res = await ApiService.get(ApiConfig.orderDetailEndpoint(orderId));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['order'] != null) {
          return OrderModel.fromJson(data['order']);
        }
      }
    } catch (_) {}
    return null;
  }

  Future<bool> claimPickup(int orderId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.claimPickupEndpoint(orderId), {});
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Pickup claimed successfully.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not accept pickup.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> declinePickup(int orderId, {String? reason}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.declinePickupEndpoint(orderId), {
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      });
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Pickup declined.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not decline pickup.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> arriveAtPickup(int orderId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.arrivePickupEndpoint(orderId), {});
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Arrival at seller recorded.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not record arrival.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> confirmPickup(int orderId, String parcelReference, {String method = 'manual'}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.confirmPickupEndpoint(orderId), {
        'parcel_reference': parcelReference.trim(),
        'method': method,
      });
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Pickup confirmed.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not confirm pickup.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> acceptDeliveryAssignment(int orderId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.acceptDeliveryAssignmentEndpoint(orderId), {});
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Delivery assignment accepted.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not accept delivery assignment.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> startDelivery(int orderId, {String? parcelReference, String method = 'manual'}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.startDeliveryEndpoint(orderId), {
        if (parcelReference != null && parcelReference.trim().isNotEmpty)
          'parcel_reference': parcelReference.trim(),
        'method': method,
      });
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Delivery started.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not start delivery.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> declineDeliveryAssignment(int orderId, {String? reason}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.declineDeliveryAssignmentEndpoint(orderId), {
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      });
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Delivery assignment declined.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not decline assignment.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> completeDelivery({
    required int orderId,
    required String recipientConfirmation,
    required String deliveryCode,
    double? codCollectedAmount,
    String? deliveryNotes,
    required String proofFilePath,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final fields = <String, String>{
        'recipient_confirmation': recipientConfirmation.trim(),
        'delivery_code': deliveryCode.trim(),
        if (deliveryNotes != null && deliveryNotes.trim().isNotEmpty)
          'delivery_notes': deliveryNotes.trim(),
        if (codCollectedAmount != null)
          'cod_collected_amount': codCollectedAmount.toStringAsFixed(2),
      };

      final res = await ApiService.multipartPost(
        url: ApiConfig.completeDeliveryEndpoint(orderId),
        fields: fields,
        fileField: 'proof_file',
        filePath: proofFilePath,
        method: 'POST',
      );

      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Delivery marked complete.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not complete delivery.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> failDelivery({
    required int orderId,
    required String failureReason,
    String? deliveryNotes,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.failDeliveryEndpoint(orderId), {
        'failure_reason': failureReason,
        if (deliveryNotes != null && deliveryNotes.trim().isNotEmpty)
          'delivery_notes': deliveryNotes.trim(),
      });
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Delivery failure recorded.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not record delivery failure.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<bool> confirmReturnDelivery(int orderId, String parcelReference, {String method = 'manual'}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.confirmReturnDeliveryEndpoint(orderId), {
        'parcel_reference': parcelReference.trim(),
        'method': method,
      });
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Return handover recorded.';
        await fetchDashboard();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not record return handover.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }

  Future<void> fetchHistory({String? search, String? status}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final queryParams = <String, String>{};
      if (search != null && search.trim().isNotEmpty) queryParams['search'] = search.trim();
      if (status != null && status.trim().isNotEmpty) queryParams['status'] = status.trim();

      var uri = Uri.parse(ApiConfig.historyEndpoint);
      if (queryParams.isNotEmpty) {
        uri = uri.replace(queryParameters: queryParams);
      }

      final res = await ApiService.get(uri.toString());
      _isLoading = false;

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['orders'] is List) {
          _historyOrders = (data['orders'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
  }

  Future<void> fetchTracking() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.get(ApiConfig.trackingEndpoint);
      _isLoading = false;

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['orders'] is List) {
          _trackingOrders = (data['orders'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        }
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
  }

  Future<void> fetchEarnings() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.get(ApiConfig.earningsEndpoint);
      _isLoading = false;

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final cents = data['available_balance_cents'];
        _availableEarnings = (cents is num)
            ? (cents.toDouble() / 100.0)
            : double.tryParse(data['available_balance_pesos']?.toString() ?? '0') ?? 0.0;

        if (data['allocations'] is List) {
          _allocations = (data['allocations'] as List)
              .map((a) => AllocationModel.fromJson(a as Map<String, dynamic>))
              .toList();
        }
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
  }

  Future<bool> submitRemittance({
    required int orderId,
    required double amount,
    required String reference,
    required String reason,
    required String evidencePath,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.multipartPost(
        url: ApiConfig.remittanceEndpoint(orderId),
        fields: {
          'amount': amount.toStringAsFixed(2),
          'reference': reference.trim(),
          'reason': reason.trim(),
        },
        fileField: 'evidence',
        filePath: evidencePath,
      );
      _isLoading = false;
      final data = jsonDecode(res.body);

      if (res.statusCode == 200 && data['success'] == true) {
        _successMessage = data['message'] ?? 'Remittance submitted.';
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Could not submit remittance.');
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
    }
    notifyListeners();
    return false;
  }
}
