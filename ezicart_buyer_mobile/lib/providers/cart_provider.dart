import 'dart:convert';
import 'package:flutter/material.dart';
import '../config/api_config.dart';
import '../models/cart_item_model.dart';
import '../models/product_model.dart';
import '../services/api_service.dart';

class CartProvider extends ChangeNotifier {
  final Map<String, CartItemModel> _items = {};
  String? _checkoutToken;
  String? _quoteHash;
  String? _appliedVoucherCode;
  int? _voucherId;

  double _subtotal = 0.0;
  double _discount = 0.0;
  double _shippingFee = 0.0;
  double _total = 0.0;

  bool _isLoading = false;
  String? _errorMessage;

  Map<String, CartItemModel> get items => {..._items};
  int get itemCount => _items.values.fold(0, (sum, i) => sum + i.quantity);

  String? get checkoutToken => _checkoutToken;
  String? get quoteHash => _quoteHash;
  String? get appliedVoucherCode => _appliedVoucherCode;
  int? get voucherId => _voucherId;

  double get subtotal => _subtotal;
  double get discount => _discount;
  double get shippingFee => _shippingFee;
  double get total => _total;

  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  void addItem(ProductModel product, {ProductVariation? variation, int quantity = 1}) {
    final maxStock = variation != null ? variation.stock : product.stock;
    final limit = maxStock > 0 ? (maxStock < 20 ? maxStock : 20) : 1;
    final key = '${product.id}${variation != null ? "_${variation.id}" : ""}';
    if (_items.containsKey(key)) {
      final newQty = _items[key]!.quantity + quantity;
      _items[key]!.quantity = newQty.clamp(1, limit);
    } else {
      _items[key] = CartItemModel(
        product: product,
        variation: variation,
        quantity: quantity.clamp(1, limit),
      );
    }
    _recalculateLocalTotals();
    notifyListeners();
    Future.microtask(() => fetchAuthoritativeQuote());
  }

  void updateQuantity(String cartKey, int quantity) {
    if (_items.containsKey(cartKey)) {
      final item = _items[cartKey]!;
      final maxStock = item.availableStock;
      final limit = maxStock > 0 ? (maxStock < 20 ? maxStock : 20) : 1;
      if (quantity <= 0) {
        _items.remove(cartKey);
      } else {
        _items[cartKey]!.quantity = quantity.clamp(1, limit);
      }
      _recalculateLocalTotals();
      notifyListeners();
      Future.microtask(() => fetchAuthoritativeQuote());
    }
  }

  void removeItem(String cartKey) {
    _items.remove(cartKey);
    _recalculateLocalTotals();
    notifyListeners();
    if (_items.isNotEmpty) {
      Future.microtask(() => fetchAuthoritativeQuote());
    } else {
      clearQuote();
    }
  }

  void clearCart() {
    _items.clear();
    clearQuote();
    notifyListeners();
  }

  void clearQuote() {
    _checkoutToken = null;
    _quoteHash = null;
    _appliedVoucherCode = null;
    _voucherId = null;
    _subtotal = 0.0;
    _discount = 0.0;
    _shippingFee = 0.0;
    _total = 0.0;
  }

  void _recalculateLocalTotals() {
    _subtotal = _items.values.fold(0.0, (sum, item) => sum + item.total);
    _total = _subtotal - _discount + _shippingFee;
    if (_total < 0) _total = 0.0;
  }

  Future<bool> fetchAuthoritativeQuote({String? voucherCode, Map<String, dynamic>? destination}) async {
    if (_items.isEmpty) {
      clearQuote();
      notifyListeners();
      return true;
    }

    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    final lines = _items.values.map((item) => {
      'product_id': item.product.id,
      'variation_id': item.variation?.id,
      'quantity': item.quantity,
    }).toList();

    try {
      final res = await ApiService.post(ApiConfig.cartQuoteEndpoint, {
        'items': lines,
        'voucher_code': voucherCode ?? _appliedVoucherCode,
        if (_checkoutToken != null) 'checkout_token': _checkoutToken,
        if (destination != null) 'destination': destination,
      });

      final body = jsonDecode(res.body);
      _isLoading = false;

      if (res.statusCode == 200 && body['success'] == true) {
        final data = body['data'];
        _subtotal = (data['subtotal'] is num) ? (data['subtotal'] as num).toDouble() : double.tryParse(data['subtotal']?.toString() ?? '') ?? _subtotal;
        _discount = (data['discount'] is num) ? (data['discount'] as num).toDouble() : double.tryParse(data['discount']?.toString() ?? '') ?? 0.0;
        _shippingFee = (data['shipping_fee'] is num) ? (data['shipping_fee'] as num).toDouble() : double.tryParse(data['shipping_fee']?.toString() ?? '') ?? 0.0;
        _total = (data['total'] is num) ? (data['total'] as num).toDouble() : double.tryParse(data['total']?.toString() ?? '') ?? (_subtotal - _discount + _shippingFee);
        _quoteHash = data['quote_hash']?.toString();
        _checkoutToken = data['checkout_token']?.toString();

        if (data['voucher'] != null) {
          _appliedVoucherCode = data['voucher']['code']?.toString();
          _voucherId = (data['voucher']['id'] is num)
              ? (data['voucher']['id'] as num).toInt()
              : int.tryParse(data['voucher']['id']?.toString() ?? '');
        } else if (voucherCode == null) {
          _appliedVoucherCode = null;
          _voucherId = null;
        }

        notifyListeners();
        return true;
      } else {
        _errorMessage = body['message'] ?? 'Unable to calculate checkout totals.';
        _checkoutToken = null;
        _quoteHash = null;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString();
      _checkoutToken = null;
      _quoteHash = null;
      notifyListeners();
      return false;
    }
  }

  Future<bool> applyVoucher(String code) async {
    final success = await fetchAuthoritativeQuote(voucherCode: code.trim());
    if (success) {
      _appliedVoucherCode = code.trim().toUpperCase();
    }
    return success;
  }

  void removeVoucher() {
    _appliedVoucherCode = null;
    _voucherId = null;
    fetchAuthoritativeQuote();
  }
}
