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

  factory WalletTransaction.fromJson(Map<String, dynamic> json) {
    return WalletTransaction(
      id: parseInt(json['id']),
      reference: json['reference']?.toString() ?? '',
      type: json['type']?.toString() ?? '',
      typeLabel: json['type_label']?.toString() ?? (json['type']?.toString().replaceAll('_', ' ') ?? ''),
      bucket: json['bucket']?.toString(),
      accountType: json['account_type']?.toString(),
      delta: parseDouble(json['delta']),
      deltaCents: parseInt(json['delta_cents']),
      balanceBefore: parseDouble(json['balance_before']),
      balanceAfter: parseDouble(json['balance_after']),
      createdAt: json['created_at']?.toString(),
    );
  }
}

class WalletFundingIntentModel {
  final int id;
  final String reference;
  final double amount;
  final int amountCents;
  final String status;
  final String? createdAt;

  WalletFundingIntentModel({
    required this.id,
    required this.reference,
    required this.amount,
    required this.amountCents,
    required this.status,
    this.createdAt,
  });

  factory WalletFundingIntentModel.fromJson(Map<String, dynamic> json) {
    return WalletFundingIntentModel(
      id: parseInt(json['id']),
      reference: json['reference']?.toString() ?? '',
      amount: parseDouble(json['amount']),
      amountCents: parseInt(json['amount_cents']),
      status: json['status']?.toString() ?? 'PENDING',
      createdAt: json['created_at']?.toString(),
    );
  }
}

class WalletWithdrawalModel {
  final int id;
  final String reference;
  final double amount;
  final String destination;
  final String status;
  final String? createdAt;

  WalletWithdrawalModel({
    required this.id,
    required this.reference,
    required this.amount,
    required this.destination,
    required this.status,
    this.createdAt,
  });

  factory WalletWithdrawalModel.fromJson(Map<String, dynamic> json) {
    return WalletWithdrawalModel(
      id: parseInt(json['id']),
      reference: json['reference']?.toString() ?? 'WDR-${json['id']}',
      amount: parseDouble(json['amount']),
      destination: json['destination']?.toString() ?? '',
      status: json['status']?.toString() ?? 'PENDING',
      createdAt: json['created_at']?.toString(),
    );
  }
}

class RemittanceBatchModel {
  final int id;
  final String batchReference;
  final String hubName;
  final double expectedPesos;
  final double submittedPesos;
  final double? receivedPesos;
  final String status;
  final bool hasEvidence;
  final bool hasReceipt;
  final String? notes;
  final int itemsCount;
  final String createdAt;

  RemittanceBatchModel({
    required this.id,
    required this.batchReference,
    required this.hubName,
    required this.expectedPesos,
    required this.submittedPesos,
    this.receivedPesos,
    required this.status,
    this.hasEvidence = false,
    this.hasReceipt = false,
    this.notes,
    this.itemsCount = 0,
    required this.createdAt,
  });

  factory RemittanceBatchModel.fromJson(Map<String, dynamic> json) {
    return RemittanceBatchModel(
      id: parseInt(json['id']),
      batchReference: json['batch_reference']?.toString() ?? 'BATCH-${json['id']}',
      hubName: json['hub_name']?.toString() ?? 'Sorting Hub',
      expectedPesos: parseDouble(json['expected_pesos']),
      submittedPesos: parseDouble(json['submitted_pesos']),
      receivedPesos: parseNullableDouble(json['received_pesos']),
      status: json['status']?.toString() ?? 'SUBMITTED',
      hasEvidence: json['has_evidence'] == true,
      hasReceipt: json['has_receipt'] == true,
      notes: json['notes']?.toString(),
      itemsCount: parseInt(json['items_count'], 0),
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class PendingCollectionModel {
  final int id;
  final int orderId;
  final String orderNumber;
  final double collectedPesos;
  final String? collectedAt;
  final int? hubId;

  PendingCollectionModel({
    required this.id,
    required this.orderId,
    required this.orderNumber,
    required this.collectedPesos,
    this.collectedAt,
    this.hubId,
  });

  factory PendingCollectionModel.fromJson(Map<String, dynamic> json) {
    return PendingCollectionModel(
      id: parseInt(json['id']),
      orderId: parseInt(json['order_id']),
      orderNumber: json['order_number']?.toString() ?? '',
      collectedPesos: parseDouble(json['collected_pesos']),
      collectedAt: json['collected_at']?.toString(),
      hubId: parseNullableInt(json['hub_id']),
    );
  }
}
