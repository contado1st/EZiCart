import '../utils/safe_convert.dart';

class WalletBalances {
  final double available;
  final int availableCents;
  final double pending;
  final int pendingCents;
  final double held;
  final int heldCents;
  final double reserved;
  final int reservedCents;
  final bool isFrozen;
  final bool demoEnabled;

  WalletBalances({
    this.available = 0.0,
    this.availableCents = 0,
    this.pending = 0.0,
    this.pendingCents = 0,
    this.held = 0.0,
    this.heldCents = 0,
    this.reserved = 0.0,
    this.reservedCents = 0,
    this.isFrozen = false,
    this.demoEnabled = true,
  });

  factory WalletBalances.fromJson(Map<String, dynamic> json, {bool demoEnabled = true}) {
    final availCents = parseInt(json['available_cents'], (parseDouble(json['available']) * 100).round());
    final pendCents = parseInt(json['pending_cents'], (parseDouble(json['pending']) * 100).round());
    final hldCents = parseInt(json['held_cents'], (parseDouble(json['held']) * 100).round());
    final resCents = parseInt(json['reserved_cents'], (parseDouble(json['reserved']) * 100).round());

    return WalletBalances(
      available: parseDouble(json['available'], availCents / 100),
      availableCents: availCents,
      pending: parseDouble(json['pending'], pendCents / 100),
      pendingCents: pendCents,
      held: parseDouble(json['held'], hldCents / 100),
      heldCents: hldCents,
      reserved: parseDouble(json['reserved'], resCents / 100),
      reservedCents: resCents,
      isFrozen: json['is_frozen'] == true,
      demoEnabled: json['demo_enabled'] == true || demoEnabled,
    );
  }
}

class WalletTransaction {
  final int id;
  final String reference;
  final String type;
  final String typeLabel;
  final String? bucket;
  final String? accountType;
  final double delta;
  final int deltaCents;
  final double balanceBefore;
  final double balanceAfter;
  final String? createdAt;

  WalletTransaction({
    required this.id,
    required this.reference,
    required this.type,
    required this.typeLabel,
    this.bucket,
    this.accountType,
    required this.delta,
    required this.deltaCents,
    required this.balanceBefore,
    required this.balanceAfter,
    this.createdAt,
  });

  bool get isCredit => delta > 0;

  factory WalletTransaction.fromJson(Map<String, dynamic> json) {
    final dCents = parseInt(json['delta_cents'], (parseDouble(json['delta']) * 100).round());
    return WalletTransaction(
      id: parseInt(json['id']),
      reference: json['reference']?.toString() ?? 'TXN-${json['id']}',
      type: json['type']?.toString() ?? 'entry',
      typeLabel: json['type_label']?.toString() ??
          (json['type']?.toString().replaceAll('_', ' ') ?? 'Transaction'),
      bucket: json['bucket'] as String?,
      accountType: json['account_type'] as String?,
      delta: parseDouble(json['delta'], dCents / 100),
      deltaCents: dCents,
      balanceBefore: parseDouble(json['balance_before']),
      balanceAfter: parseDouble(json['balance_after']),
      createdAt: json['created_at'] as String?,
    );
  }
}

class DemoFundingIntent {
  final int id;
  final String reference;
  final String? provider;
  final String kind;
  final String kindLabel;
  final double amount;
  final int amountCents;
  final String status;
  final String statusLabel;
  final String reason;
  final String? receiptReference;
  final bool hasEvidence;
  final String? evidenceUrl;
  final String? createdAt;
  final String? expiresAt;
  final bool isExpired;
  final String? reviewedAt;
  final int? reviewedBy;
  final String? reviewReason;

  DemoFundingIntent({
    required this.id,
    required this.reference,
    this.provider,
    required this.kind,
    required this.kindLabel,
    required this.amount,
    required this.amountCents,
    required this.status,
    required this.statusLabel,
    required this.reason,
    this.receiptReference,
    this.hasEvidence = false,
    this.evidenceUrl,
    this.createdAt,
    this.expiresAt,
    this.isExpired = false,
    this.reviewedAt,
    this.reviewedBy,
    this.reviewReason,
  });

  factory DemoFundingIntent.fromJson(Map<String, dynamic> json) {
    final amtCents = parseInt(json['amount_cents'], (parseDouble(json['amount']) * 100).round());
    return DemoFundingIntent(
      id: parseInt(json['id']),
      reference: json['reference']?.toString() ?? '',
      provider: json['provider'] as String?,
      kind: json['kind']?.toString() ?? 'demo_credit',
      kindLabel: json['kind_label']?.toString() ??
          (json['kind']?.toString().replaceAll('_', ' ') ?? 'Demo Credit'),
      amount: parseDouble(json['amount'], amtCents / 100),
      amountCents: amtCents,
      status: json['status']?.toString() ?? 'PENDING',
      statusLabel: json['status_label']?.toString() ??
          (json['status']?.toString().replaceAll('_', ' ') ?? 'Pending'),
      reason: json['reason']?.toString() ?? '',
      receiptReference: json['receipt_reference'] as String?,
      hasEvidence: json['has_evidence'] == true,
      evidenceUrl: json['evidence_url'] as String?,
      createdAt: json['created_at'] as String?,
      expiresAt: json['expires_at'] as String?,
      isExpired: json['is_expired'] == true,
      reviewedAt: json['reviewed_at'] as String?,
      reviewedBy: parseNullableInt(json['reviewed_by']),
      reviewReason: json['review_reason'] as String?,
    );
  }
}

class WalletWithdrawal {
  final int id;
  final String reference;
  final double amount;
  final int amountCents;
  final String destination;
  final String status;
  final String statusLabel;
  final String? createdAt;
  final String? reviewedAt;
  final String? reviewReason;

  WalletWithdrawal({
    required this.id,
    required this.reference,
    required this.amount,
    required this.amountCents,
    required this.destination,
    required this.status,
    required this.statusLabel,
    this.createdAt,
    this.reviewedAt,
    this.reviewReason,
  });

  factory WalletWithdrawal.fromJson(Map<String, dynamic> json) {
    final amtCents = parseInt(json['amount_cents'], (parseDouble(json['amount']) * 100).round());
    return WalletWithdrawal(
      id: parseInt(json['id']),
      reference: json['reference']?.toString() ?? '',
      amount: parseDouble(json['amount'], amtCents / 100),
      amountCents: amtCents,
      destination: json['destination']?.toString() ?? '',
      status: json['status']?.toString() ?? 'REQUESTED',
      statusLabel: json['status_label']?.toString() ??
          (json['status']?.toString().replaceAll('_', ' ') ?? 'Requested'),
      createdAt: json['created_at'] as String?,
      reviewedAt: json['reviewed_at'] as String?,
      reviewReason: json['review_reason'] as String?,
    );
  }
}

class WalletFinanceRecord {
  final int id;
  final int orderId;
  final String? orderNumber;
  final String? parcelCode;
  final String sellerName;
  final String paymentMethod;
  final double amount;
  final bool isAwaitingWalletPayment;
  final String statusNote;
  final String? remittanceStatus;
  final String? remittanceStatusLabel;
  final String? createdAt;

  WalletFinanceRecord({
    required this.id,
    required this.orderId,
    this.orderNumber,
    this.parcelCode,
    required this.sellerName,
    required this.paymentMethod,
    required this.amount,
    required this.isAwaitingWalletPayment,
    required this.statusNote,
    this.remittanceStatus,
    this.remittanceStatusLabel,
    this.createdAt,
  });

  factory WalletFinanceRecord.fromJson(Map<String, dynamic> json) {
    return WalletFinanceRecord(
      id: parseInt(json['id']),
      orderId: parseInt(json['order_id']),
      orderNumber: json['order_number'] as String?,
      parcelCode: json['parcel_code'] as String?,
      sellerName: json['seller_name']?.toString() ?? 'Merchant',
      paymentMethod: json['payment_method']?.toString() ?? 'COD',
      amount: parseDouble(json['amount']),
      isAwaitingWalletPayment: json['is_awaiting_wallet_payment'] == true,
      statusNote: json['status_note']?.toString() ?? '',
      remittanceStatus: json['remittance_status'] as String?,
      remittanceStatusLabel: json['remittance_status_label'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

class WalletRefundRecord {
  final int id;
  final int orderId;
  final String? orderNumber;
  final String reference;
  final double amount;
  final String method;
  final String status;
  final String? reason;
  final String? createdAt;

  WalletRefundRecord({
    required this.id,
    required this.orderId,
    this.orderNumber,
    required this.reference,
    required this.amount,
    required this.method,
    required this.status,
    this.reason,
    this.createdAt,
  });

  factory WalletRefundRecord.fromJson(Map<String, dynamic> json) {
    return WalletRefundRecord(
      id: parseInt(json['id']),
      orderId: parseInt(json['order_id']),
      orderNumber: json['order_number'] as String?,
      reference: json['reference']?.toString() ?? '',
      amount: parseDouble(json['amount']),
      method: json['method']?.toString() ?? 'WALLET',
      status: json['status']?.toString() ?? 'COMPLETED',
      reason: json['reason'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
