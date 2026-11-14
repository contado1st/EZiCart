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
  String? _badgeCode;

  UserModel? get user => _user;
  bool get isLoading => _isLoading;
  bool get isInitialized => _isInitialized;
  bool get isAuthenticated => _user != null;
  String? get errorMessage => _errorMessage;
  String? get badgeCode => _badgeCode ?? _user?.badgeCode;

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
        if (data['user'] != null) {
          _user = UserModel.fromJson(data['user']);
          _badgeCode = _user?.badgeCode;
          _isInitialized = true;
          notifyListeners();
          return true;
        }
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
        'app': 'courier',
      });

      dynamic data;
      try {
        data = jsonDecode(res.body);
      } catch (_) {}

      _isLoading = false;

      if (res.statusCode == 200 && data is Map && data['success'] == true) {
        await ApiService.setToken(data['token']);
        _user = UserModel.fromJson(data['user']);
        _badgeCode = _user?.badgeCode;
        notifyListeners();
        return true;
      } else {
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

  Future<bool> rotateBadge() async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await ApiService.post(ApiConfig.rotateBadgeEndpoint, {});
      _isLoading = false;
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['success'] == true && data['badge_code'] != null) {
          _badgeCode = data['badge_code'].toString();
          notifyListeners();
          return true;
        }
      }
    } catch (_) {
      _isLoading = false;
      notifyListeners();
    }
    return false;
  }

  Future<void> logout() async {
    try {
      await ApiService.post(ApiConfig.logoutEndpoint, {});
    } catch (_) {}
    await ApiService.removeToken();
    _user = null;
    _badgeCode = null;
    notifyListeners();
  }
}
