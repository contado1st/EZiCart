import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import '../models/psgc_location_model.dart';

class PsgcService {
  static final Map<String, List<PsgcLocationModel>> _cache = {};

  static Future<List<PsgcLocationModel>> fetchRegions() async {
    const cacheKey = 'level_regions';
    if (_cache.containsKey(cacheKey)) {
      return _cache[cacheKey]!;
    }

    try {
      final uri = Uri.parse(ApiConfig.psgcAddressesEndpoint).replace(
        queryParameters: {'level': 'regions'},
      );
      final res = await http.get(uri, headers: {'Accept': 'application/json'});
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((item) => PsgcLocationModel.fromJson(item as Map<String, dynamic>))
              .toList();
          _cache[cacheKey] = list;
          return list;
        }
      }
    } catch (_) {}
    return [];
  }

  static Future<List<PsgcLocationModel>> fetchProvinces(String regionCode) async {
    final cacheKey = 'level_provinces_$regionCode';
    if (_cache.containsKey(cacheKey)) {
      return _cache[cacheKey]!;
    }

    try {
      final uri = Uri.parse(ApiConfig.psgcAddressesEndpoint).replace(
        queryParameters: {
          'level': 'provinces',
          'parent': regionCode,
        },
      );
      final res = await http.get(uri, headers: {'Accept': 'application/json'});
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((item) => PsgcLocationModel.fromJson(item as Map<String, dynamic>))
              .toList();

          if (regionCode == '1300000000' && list.isEmpty) {
            list.add(const PsgcLocationModel(code: 'NCR', name: 'Metro Manila'));
          }

          _cache[cacheKey] = list;
          return list;
        }
      }
    } catch (_) {}

    if (regionCode == '1300000000') {
      return const [PsgcLocationModel(code: 'NCR', name: 'Metro Manila')];
    }
    return [];
  }

  static Future<List<PsgcLocationModel>> fetchLocalities(
    String parentCode, {
    bool isRegion = false,
  }) async {
    final cacheKey = 'level_localities_${parentCode}_$isRegion';
    if (_cache.containsKey(cacheKey)) {
      return _cache[cacheKey]!;
    }

    try {
      final uri = Uri.parse(ApiConfig.psgcAddressesEndpoint).replace(
        queryParameters: {
          'level': 'localities',
          'parent': parentCode,
          'parent_level': isRegion ? 'region' : 'province',
        },
      );
      final res = await http.get(uri, headers: {'Accept': 'application/json'});
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((item) => PsgcLocationModel.fromJson(item as Map<String, dynamic>))
              .toList();
          _cache[cacheKey] = list;
          return list;
        }
      }
    } catch (_) {}
    return [];
  }

  static Future<List<PsgcLocationModel>> fetchBarangays(String localityCode) async {
    final cacheKey = 'level_barangays_$localityCode';
    if (_cache.containsKey(cacheKey)) {
      return _cache[cacheKey]!;
    }

    try {
      final uri = Uri.parse(ApiConfig.psgcAddressesEndpoint).replace(
        queryParameters: {
          'level': 'barangays',
          'parent': localityCode,
        },
      );
      final res = await http.get(uri, headers: {'Accept': 'application/json'});
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['data'] is List) {
          final list = (body['data'] as List)
              .map((item) => PsgcLocationModel.fromJson(item as Map<String, dynamic>))
              .toList();
          _cache[cacheKey] = list;
          return list;
        }
      }
    } catch (_) {}
    return [];
  }
}

