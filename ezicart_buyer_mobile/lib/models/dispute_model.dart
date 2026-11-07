class DisputeModel {
  final int id;
  final int orderId;
  final String reason;
  final String description;
  final String status;
  final String? adminNotes;
  final DateTime? createdAt;
  final DateTime? resolvedAt;
  final String? parcelCode;
  final String? sellerName;

  const DisputeModel({
    required this.id,
    required this.orderId,
    required this.reason,
    required this.description,
    required this.status,
    this.adminNotes,
    this.createdAt,
    this.resolvedAt,
    this.parcelCode,
    this.sellerName,
  });

  factory DisputeModel.fromJson(Map<String, dynamic> json) {
    String? pCode;
    String? sName;
    if (json['order'] is Map) {
      pCode = json['order']['parcel_code']?.toString();
      if (json['order']['seller'] is Map) {
        sName = json['order']['seller']['business_name']?.toString();
      }
    }

    return DisputeModel(
      id: (json['id'] is num) ? (json['id'] as num).toInt() : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      orderId: (json['order_id'] is num) ? (json['order_id'] as num).toInt() : int.tryParse(json['order_id']?.toString() ?? '') ?? 0,
      reason: json['reason']?.toString() ?? 'General Dispute',
      description: json['description']?.toString() ?? '',
      status: json['status']?.toString() ?? 'PENDING',
      adminNotes: json['admin_notes']?.toString(),
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
      resolvedAt: json['resolved_at'] != null ? DateTime.tryParse(json['resolved_at'].toString()) : null,
      parcelCode: pCode,
      sellerName: sName,
    );
  }
}

