import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  ApiException(this.message, {this.statusCode, this.errors});

  @override
  String toString() => message;
}

class ApiService {
  static const Duration _timeoutDuration = Duration(seconds: 25);
  static Function()? onUnauthorized;

  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  static Future<void> setToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  static Future<void> removeToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }

  static Future<Map<String, String>> _headers() async {
    final token = await getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  static void _checkUnauthorized(http.Response res) {
    if (res.statusCode == 401) {
      removeToken();
      if (onUnauthorized != null) {
        scheduleMicrotask(() => onUnauthorized?.call());
      }
    }
  }

  static void _handleNetworkError(Object e, String url) {
    if (e is ApiException) throw e;
    if (e is TimeoutException) {
      throw ApiException('Connection timed out connecting to $url. Please check your backend server.');
    }
    final msg = e.toString().toLowerCase();
    if (msg.contains('socket') ||
        msg.contains('connection refused') ||
        msg.contains('failed host lookup') ||
        msg.contains('xmlhttprequest') ||
        msg.contains('clientexception') ||
        msg.contains('os error: 10061')) {
      throw ApiException('Unable to connect to the server at $url. Ensure your Laravel backend server is running (php artisan serve).');
    }
    throw ApiException('Connection error: ${e.toString()}');
  }

  static Future<http.Response> get(String url) async {
    try {
      final headers = await _headers();
      final res = await http.get(Uri.parse(url), headers: headers).timeout(_timeoutDuration);
      _checkUnauthorized(res);
      return res;
    } catch (e) {
      _handleNetworkError(e, url);
      rethrow;
    }
  }

  static Future<http.Response> post(String url, Map<String, dynamic> body) async {
    try {
      final headers = await _headers();
      final res = await http.post(
        Uri.parse(url),
        headers: headers,
        body: jsonEncode(body),
      ).timeout(_timeoutDuration);
      _checkUnauthorized(res);
      return res;
    } catch (e) {
      _handleNetworkError(e, url);
      rethrow;
    }
  }

  static Future<http.Response> put(String url, Map<String, dynamic> body) async {
    try {
      final headers = await _headers();
      final res = await http.put(
        Uri.parse(url),
        headers: headers,
        body: jsonEncode(body),
      ).timeout(_timeoutDuration);
      _checkUnauthorized(res);
      return res;
    } catch (e) {
      _handleNetworkError(e, url);
      rethrow;
    }
  }

  static Future<http.Response> patch(String url, Map<String, dynamic> body) async {
    try {
      final headers = await _headers();
      final res = await http.patch(
        Uri.parse(url),
        headers: headers,
        body: jsonEncode(body),
      ).timeout(_timeoutDuration);
      _checkUnauthorized(res);
      return res;
    } catch (e) {
      _handleNetworkError(e, url);
      rethrow;
    }
  }

  static Future<http.Response> multipartPost({
    required String url,
    required Map<String, String> fields,
    required String fileField,
    required String filePath,
    String? method, // 'POST' or 'PATCH'
  }) async {
    try {
      final token = await getToken();
      final uri = Uri.parse(url);
      final request = http.MultipartRequest(method ?? 'POST', uri);

      request.headers['Accept'] = 'application/json';
      if (token != null && token.isNotEmpty) {
        request.headers['Authorization'] = 'Bearer $token';
      }

      request.fields.addAll(fields);
      if (filePath.isNotEmpty) {
        request.files.add(await http.MultipartFile.fromPath(fileField, filePath));
      }

      final streamedResponse = await request.send().timeout(_timeoutDuration);
      final res = await http.Response.fromStream(streamedResponse);
      _checkUnauthorized(res);
      return res;
    } catch (e) {
      _handleNetworkError(e, url);
      rethrow;
    }
  }
}
