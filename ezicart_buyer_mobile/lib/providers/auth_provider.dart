import 'dart:convert';
import 'package:flutter/material.dart';
import '../config/api_config.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';

class AuthProvider extends ChangeNotifier {
  UserModel? _user;
  bool _isLoading = false;
  bool _isInitialized = false;
  String? _errorMessage;
  String? _pendingVerificationEmail;
  Map<String, int> _orderCounts = {};

  UserModel? get user => _user;
  bool get isLoading => _isLoading;
  bool get isInitialized => _isInitialized;
  bool get isAuthenticated => _user != null;
  String? get errorMessage => _errorMessage;
  String? get pendingVerificationEmail => _pendingVerificationEmail;
  Map<String, int> get orderCounts => _orderCounts;

  AuthProvider() {
    ApiService.onUnauthorized = () {
      _user = null;
      notifyListeners();
    };
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  void setPendingVerificationEmail(String? email) {
    _pendingVerificationEmail = email;
    notifyListeners();
  }

  Future<bool> tryAutoLogin() async {
    await ApiConfig.loadBaseUrl();
    final token = await ApiService.getToken();
    if (token == null) {
      _isInitialized = true;
      notifyListeners();
      return false;
    }

    try {
      final res = await ApiService.get(ApiConfig.meEndpoint);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        _user = UserModel.fromJson(data['user']);
        if (data['order_counts'] is Map) {
          _orderCounts = (data['order_counts'] as Map).map(
            (k, v) => MapEntry(
              k.toString(),
              (v is num) ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0,
            ),
          );
        }
        _isInitialized = true;
        notifyListeners();
        return true;
      }
      await ApiService.removeToken();
    } catch (_) {
      await ApiService.removeToken();
    }
    _isInitialized = true;
    notifyListeners();
    return false;
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

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.loginEndpoint, {
        'email': email.trim(),
        'password': password,
      });

      dynamic data;
      try {
        data = jsonDecode(res.body);
      } catch (_) {}

      _isLoading = false;

      if (res.statusCode == 200 && data is Map && data['success'] == true) {
        await ApiService.setToken(data['token']);
        _user = UserModel.fromJson(data['user']);
        notifyListeners();
        return true;
      } else {
        if (data is Map && data['requires_verification'] == true) {
          _pendingVerificationEmail = data['email'] ?? email.trim();
        }
        _errorMessage = _extractErrorMessage(data, 'Login failed. Please check your credentials.');
        notifyListeners();
        return false;
      }
    } on ApiException catch (e) {
      _isLoading = false;
      _errorMessage = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> register({
    required String firstName,
    String? middleName,
    required String lastName,
    String? suffix,
    required String email,
    required String contactNo,
    required String birthday,
    required String password,
    String? region,
    String? province,
    String? municipality,
    String? barangay,
    String? streetAddress,
    String? houseNumber,
    String? postalCode,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.registerEndpoint, {
        'first_name': firstName.trim(),
        if (middleName != null && middleName.isNotEmpty)
          'middle_name': middleName.trim(),
        'last_name': lastName.trim(),
        if (suffix != null && suffix.isNotEmpty) 'suffix': suffix.trim(),
        'email': email.trim(),
        'contact_no': contactNo.trim(),
        'birthday': birthday,
        'password': password,
        'password_confirmation': password,
        'terms': true,
        if (region != null) 'region': region,
        if (province != null) 'province': province,
        if (municipality != null) 'municipality': municipality,
        if (barangay != null) 'barangay': barangay,
        if (streetAddress != null) 'street_address': streetAddress,
        if (houseNumber != null) 'house_number': houseNumber,
        if (postalCode != null) 'postal_code': postalCode,
      });

      dynamic data;
      try {
        data = jsonDecode(res.body);
      } catch (_) {}

      _isLoading = false;

      if (res.statusCode == 201 && data is Map && data['success'] == true) {
        _pendingVerificationEmail = email.trim();
        notifyListeners();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Registration could not be completed.');
        notifyListeners();
        return false;
      }
    } on ApiException catch (e) {
      _isLoading = false;
      _errorMessage = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> verifyOtp(String email, String code) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.verifyOtpEndpoint, {
        'email': email.trim(),
        'code': code.trim(),
      });

      dynamic data;
      try {
        data = jsonDecode(res.body);
      } catch (_) {}

      _isLoading = false;

      if (res.statusCode == 200 && data is Map && data['success'] == true) {
        await ApiService.setToken(data['token']);
        _user = UserModel.fromJson(data['user']);
        _pendingVerificationEmail = null;
        notifyListeners();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'OTP verification failed.');
        notifyListeners();
        return false;
      }
    } on ApiException catch (e) {
      _isLoading = false;
      _errorMessage = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> resendOtp(String email) async {
    try {
      final res = await ApiService.post(ApiConfig.resendOtpEndpoint, {
        'email': email.trim(),
      });
      dynamic data;
      try {
        data = jsonDecode(res.body);
      } catch (_) {}
      if (res.statusCode == 200 && data is Map && data['success'] == true) {
        return true;
      }
      _errorMessage = _extractErrorMessage(data, 'Failed to resend code.');
      notifyListeners();
      return false;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      notifyListeners();
      return false;
    }
  }

  Future<bool> forgotPassword(String email) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.forgotPasswordEndpoint, {
        'email': email.trim(),
      });
      dynamic data;
      try {
        data = jsonDecode(res.body);
      } catch (_) {}
      _isLoading = false;
      final success = res.statusCode == 200 && data is Map && data['success'] == true;
      if (!success) {
        _errorMessage = _extractErrorMessage(data, 'Could not request password recovery.');
      }
      notifyListeners();
      return success;
    } on ApiException catch (e) {
      _isLoading = false;
      _errorMessage = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> resetPassword(
    String email,
    String code,
    String newPassword,
  ) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.resetPasswordEndpoint, {
        'email': email.trim(),
        'code': code.trim(),
        'password': newPassword,
        'password_confirmation': newPassword,
      });

      dynamic data;
      try {
        data = jsonDecode(res.body);
      } catch (_) {}
      _isLoading = false;

      if (res.statusCode == 200 && data is Map && data['success'] == true) {
        if (data['token'] != null) {
          await ApiService.setToken(data['token']);
          _user = UserModel.fromJson(data['user']);
        }
        notifyListeners();
        return true;
      } else {
        _errorMessage = _extractErrorMessage(data, 'Password reset failed.');
        notifyListeners();
        return false;
      }
    } on ApiException catch (e) {
      _isLoading = false;
      _errorMessage = e.message;
      notifyListeners();
      return false;
    }
  }

  Future<bool> updateProfile(Map<String, dynamic> payload) async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await ApiService.put(
        ApiConfig.updateProfileEndpoint,
        payload,
      );
      final data = jsonDecode(res.body);
      _isLoading = false;
      if (res.statusCode == 200 && data['success'] == true) {
        _user = UserModel.fromJson(data['user']);
        notifyListeners();
        return true;
      }
      _errorMessage = data['message'] ?? 'Update failed.';
      notifyListeners();
      return false;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> updatePassword(
    String currentPassword,
    String newPassword,
  ) async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await ApiService.put(ApiConfig.updatePasswordEndpoint, {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPassword,
      });
      final data = jsonDecode(res.body);
      _isLoading = false;
      if (res.statusCode == 200 && data['success'] == true) {
        notifyListeners();
        return true;
      }
      _errorMessage = _extractErrorMessage(data, 'Password update failed.');
      notifyListeners();
      return false;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    try {
      await ApiService.post(ApiConfig.logoutEndpoint, {});
    } catch (_) {}
    await ApiService.removeToken();
    _user = null;
    notifyListeners();
  }
}
