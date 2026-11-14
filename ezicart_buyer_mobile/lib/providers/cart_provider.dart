import 'dart:convert';
import 'package:flutter/material.dart';
import '../config/api_config.dart';
import '../models/cart_item_model.dart';
import '../models/product_model.dart';
import '../services/api_service.dart';

class CartProvider extends ChangeNotifier {
  final Map<String, CartItemModel> _items = {};
  final Set<String> _selectedKeys = {};

  String? _checkoutToken;
  String? _quoteHash;
  String? _appliedVoucherCode;
  int? _voucherId;

  double _subtotal = 0.0;
  double _discount = 0.0;
  double _shippingFee = 0.0;
  double _total = 0.0;

  bool _isLoading = false;
  bool _isSyncing = false;
  String? _errorMessage;

  Map<String, CartItemModel> get items => {..._items};
  int get itemCount => _items.values.fold(0, (sum, i) => sum + i.quantity);

  // Item selection for checkout
  Set<String> get selectedKeys => {..._selectedKeys};
  bool isSelected(String cartKey) => _selectedKeys.contains(cartKey);

  void toggleSelect(String cartKey) {
    if (_selectedKeys.contains(cartKey)) {
      _selectedKeys.remove(cartKey);
    } else {
      _selectedKeys.add(cartKey);
    }
    _recalculateLocalTotals();
    notifyListeners();
  }

  void selectShop(int sellerId, bool select) {
    for (final entry in _items.entries) {
      if ((entry.value.product.sellerId ?? 0) == sellerId) {
        if (select) {
          _selectedKeys.add(entry.key);
        } else {
          _selectedKeys.remove(entry.key);
        }
      }
    }
    _recalculateLocalTotals();
    notifyListeners();
  }

  void selectAll(bool select) {
    if (select) {
      _selectedKeys.addAll(_items.keys);
    } else {
      _selectedKeys.clear();
    }
    _recalculateLocalTotals();
    notifyListeners();
  }

  bool get isAllSelected => _items.isNotEmpty && _selectedKeys.length == _items.length;

  List<CartItemModel> get selectedItems =>
      _items.entries.where((e) => _selectedKeys.contains(e.key)).map((e) => e.value).toList();

  List<CartItemModel> get checkoutItems =>
      selectedItems.isNotEmpty ? selectedItems : _items.values.toList();

  int get selectedItemCount => selectedItems.fold(0, (sum, i) => sum + i.quantity);

  double get selectedSubtotal => selectedItems.fold(0.0, (sum, i) => sum + i.total);

  Set<int?> get selectedShopIds => selectedItems.map((i) => i.product.sellerId).toSet();

  bool get hasMultipleShopsSelected => selectedShopIds.length > 1;

  void removePurchasedItems() {
    for (final item in selectedItems) {
      _items.remove(item.cartKey);
    }
    _selectedKeys.clear();
    _recalculateLocalTotals();
    clearQuote();
    notifyListeners();
  }

  String? get checkoutToken => _checkoutToken;
  String? get quoteHash => _quoteHash;
  String? get appliedVoucherCode => _appliedVoucherCode;
  int? get voucherId => _voucherId;

  double get subtotal => _subtotal;
  double get discount => _discount;
  double get shippingFee => _shippingFee;
  double get total => _total;

  bool get isLoading => _isLoading;
  bool get isSyncing => _isSyncing;
  String? get errorMessage => _errorMessage;

  /// Fetch buyer's persistent cart from the backend database (same as web)
  Future<void> fetchCart({bool calculateQuote = true}) async {
    final token = await ApiService.getToken();
    if (token == null || token.isEmpty) return;

    _isSyncing = true;
    notifyListeners();

    try {
      final res = await ApiService.get(ApiConfig.cartEndpoint);
      _isSyncing = false;

      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] != null && body['data']['items'] is List) {
          _updateItemsFromApi(body['data']['items'] as List);
          if (calculateQuote && checkoutItems.isNotEmpty) {
            fetchAuthoritativeQuote();
          } else if (_items.isEmpty) {
            clearQuote();
          }
          return;
        }
      }
    } catch (_) {
      _isSyncing = false;
    }
    notifyListeners();
  }

  void _updateItemsFromApi(List apiLines) {
    _items.clear();
    for (final line in apiLines) {
      if (line is Map<String, dynamic>) {
        final item = CartItemModel.fromApiJson(line);
        _items[item.cartKey] = item;
      }
    }
    _selectedKeys.removeWhere((k) => !_items.containsKey(k));
    if (_selectedKeys.isEmpty) {
      _selectedKeys.addAll(_items.keys);
    }
    _recalculateLocalTotals();
    notifyListeners();
  }

  /// Add item to cart, optimistically updating local UI and syncing with web database
  Future<void> addItem(ProductModel product, {ProductVariation? variation, int quantity = 1}) async {
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
    _selectedKeys.add(key);
    _recalculateLocalTotals();
    notifyListeners();
    Future.microtask(() => fetchAuthoritativeQuote());

    // Synchronize to persistent web database
    final token = await ApiService.getToken();
    if (token != null && token.isNotEmpty) {
      try {
        final res = await ApiService.post(ApiConfig.cartItemsEndpoint, {
          'product_id': product.id,
          if (variation != null) 'variation_id': variation.id,
          'quantity': quantity,
        });

        if (res.statusCode == 200) {
          final body = jsonDecode(res.body);
          if (body['success'] == true && body['data'] != null && body['data']['items'] is List) {
            _updateItemsFromApi(body['data']['items'] as List);
          }
        }
      } catch (_) {}
    }
  }

  /// Update item quantity, syncing with web database
  Future<void> updateQuantity(String cartKey, int quantity) async {
    if (_items.containsKey(cartKey)) {
      final item = _items[cartKey]!;
      final maxStock = item.availableStock;
      final limit = maxStock > 0 ? (maxStock < 20 ? maxStock : 20) : 1;

      if (quantity <= 0) {
        _items.remove(cartKey);
        _selectedKeys.remove(cartKey);
      } else {
        _items[cartKey]!.quantity = quantity.clamp(1, limit);
      }
      _recalculateLocalTotals();
      notifyListeners();
      Future.microtask(() => fetchAuthoritativeQuote());

      // Synchronize to persistent web database
      final token = await ApiService.getToken();
      if (token != null && token.isNotEmpty) {
        try {
          final url = ApiConfig.cartItemEndpoint(cartKey);
          if (quantity <= 0) {
            await ApiService.delete(url);
          } else {
            await ApiService.patch(url, {'quantity': quantity.clamp(1, limit)});
          }
        } catch (_) {}
      }
    }
  }

  /// Remove item from cart, syncing with web database
  Future<void> removeItem(String cartKey) async {
    _items.remove(cartKey);
    _selectedKeys.remove(cartKey);
    _recalculateLocalTotals();
    notifyListeners();

    if (checkoutItems.isNotEmpty) {
      Future.microtask(() => fetchAuthoritativeQuote());
    } else {
      clearQuote();
    }

    // Synchronize to persistent web database
    final token = await ApiService.getToken();
    if (token != null && token.isNotEmpty) {
      try {
        await ApiService.delete(ApiConfig.cartItemEndpoint(cartKey));
      } catch (_) {}
    }
  }

  /// Clear cart, syncing with web database
  Future<void> clearCart() async {
    _items.clear();
    _selectedKeys.clear();
    clearQuote();
    notifyListeners();

    final token = await ApiService.getToken();
    if (token != null && token.isNotEmpty) {
      try {
        await ApiService.delete(ApiConfig.cartEndpoint);
      } catch (_) {}
    }
  }

  /// Sync local offline items to web backend upon login
  Future<void> syncLocalCartToServer() async {
    final token = await ApiService.getToken();
    if (token == null || token.isEmpty) return;

    if (_items.isEmpty) {
      await fetchCart();
      return;
    }

    final lines = _items.values.map((item) => {
      'product_id': item.product.id,
      'variation_id': item.variation?.id,
      'quantity': item.quantity,
    }).toList();

    try {
      final res = await ApiService.post(ApiConfig.cartSyncEndpoint, {'items': lines});
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['success'] == true && body['data'] != null && body['data']['items'] is List) {
          _updateItemsFromApi(body['data']['items'] as List);
          fetchAuthoritativeQuote();
          return;
        }
      }
    } catch (_) {}

    await fetchCart();
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
    _subtotal = selectedSubtotal;
    _total = _subtotal - _discount + _shippingFee;
    if (_total < 0) _total = 0.0;
  }

  Future<bool> fetchAuthoritativeQuote({String? voucherCode, Map<String, dynamic>? destination}) async {
    final targetItems = checkoutItems;
    if (targetItems.isEmpty) {
      clearQuote();
      notifyListeners();
      return true;
    }

    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    final lines = targetItems.map((item) => {
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

  Future<bool> applyVoucher(String code, {Map<String, dynamic>? destination}) async {
    final success = await fetchAuthoritativeQuote(voucherCode: code.trim(), destination: destination);
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
