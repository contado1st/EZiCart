import '../utils/safe_convert.dart';

class ParcelPhotoModel {
  final int id;
  final String url;
  final String createdAt;

  ParcelPhotoModel({
    required this.id,
    required this.url,
    required this.createdAt,
  });

  factory ParcelPhotoModel.fromJson(Map<String, dynamic> json) {
    return ParcelPhotoModel(
      id: parseInt(json['id']),
      url: json['url']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class ParcelTrackingEventModel {
  final int id;
  final String eventType;
  final String status;
  final String location;
  final String? notes;
  final String actorName;
  final String createdAt;
  final List<ParcelPhotoModel> photos;

  ParcelTrackingEventModel({
    required this.id,
    required this.eventType,
    required this.status,
    required this.location,
    this.notes,
    required this.actorName,
    required this.createdAt,
    this.photos = const [],
  });

  factory ParcelTrackingEventModel.fromJson(Map<String, dynamic> json) {
    final rawPhotos = json['photos'] as List<dynamic>? ?? [];
    return ParcelTrackingEventModel(
      id: parseInt(json['id']),
      eventType: json['event_type']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      location: json['location']?.toString() ?? 'Location not recorded',
      notes: json['notes'] as String?,
      actorName: json['actor_name']?.toString() ?? 'System',
      createdAt: json['created_at']?.toString() ?? '',
      photos: rawPhotos.map((p) => ParcelPhotoModel.fromJson(p as Map<String, dynamic>)).toList(),
    );
  }
}

class ParcelScanEventModel {
  final int id;
  final String? station;
  final String? result;
  final String actorName;
  final String? role;
  final String? method;
  final String? failureReason;
  final String createdAt;
  final List<ParcelPhotoModel> photos;

  ParcelScanEventModel({
    required this.id,
    this.station,
    this.result,
    required this.actorName,
    this.role,
    this.method,
    this.failureReason,
    required this.createdAt,
    this.photos = const [],
  });

  factory ParcelScanEventModel.fromJson(Map<String, dynamic> json) {
    final rawPhotos = json['photos'] as List<dynamic>? ?? [];
    return ParcelScanEventModel(
      id: parseInt(json['id']),
      station: json['station'] as String?,
      result: json['result'] as String?,
      actorName: json['actor_name']?.toString() ?? 'Staff',
      role: json['role'] as String?,
      method: json['method'] as String?,
      failureReason: json['failure_reason'] as String?,
      createdAt: json['created_at']?.toString() ?? '',
      photos: rawPhotos.map((p) => ParcelPhotoModel.fromJson(p as Map<String, dynamic>)).toList(),
    );
  }
}

class ParcelDeliveryAttemptModel {
  final int id;
  final int attemptNo;
  final String outcome;
  final String? reason;
  final String? riderName;
  final String? attemptedAt;
  final bool hasProof;

  ParcelDeliveryAttemptModel({
    required this.id,
    required this.attemptNo,
    required this.outcome,
    this.reason,
    this.riderName,
    this.attemptedAt,
    this.hasProof = false,
  });

  factory ParcelDeliveryAttemptModel.fromJson(Map<String, dynamic> json) {
    return ParcelDeliveryAttemptModel(
      id: parseInt(json['id']),
      attemptNo: parseInt(json['attempt_no'], 1),
      outcome: json['outcome']?.toString() ?? '',
      reason: json['reason'] as String?,
      riderName: json['rider_name'] as String?,
      attemptedAt: json['attempted_at'] as String?,
      hasProof: json['has_proof'] == true,
    );
  }
}

class OrderProgressModel {
  final int? stage;
  final String status;
  final String? owner;
  final String? ownerLabel;
  final String message;
  final bool requiresAction;
  final List<String> steps;

  OrderProgressModel({
    this.stage,
    required this.status,
    this.owner,
    this.ownerLabel,
    required this.message,
    this.requiresAction = false,
    this.steps = const [],
  });

  factory OrderProgressModel.fromJson(Map<String, dynamic> json) {
    final rawSteps = json['steps'] as List<dynamic>? ?? [
      'Placed',
      'Confirmed',
      'Prepared',
      'In Hub',
      'Out for Delivery',
      'Delivered',
      'Completed'
    ];
    return OrderProgressModel(
      stage: parseNullableInt(json['stage']),
      status: json['status']?.toString() ?? '',
      owner: json['owner'] as String?,
      ownerLabel: json['owner_label'] as String?,
      message: json['message']?.toString() ?? '',
      requiresAction: json['requires_action'] == true,
      steps: rawSteps.map((s) => s.toString()).toList(),
    );
  }
}

class ParcelEvidenceSummaryModel {
  final int id;
  final String? orderNumber;
  final String? parcelCode;
  final String status;
  final String operationalStatus;
  final String sellerName;
  final String? recipientName;
  final String createdAt;
  final ParcelTrackingEventModel? latestEvent;
  final int evidencePhotosCount;

  ParcelEvidenceSummaryModel({
    required this.id,
    this.orderNumber,
    this.parcelCode,
    required this.status,
    required this.operationalStatus,
    required this.sellerName,
    this.recipientName,
    required this.createdAt,
    this.latestEvent,
    this.evidencePhotosCount = 0,
  });

  factory ParcelEvidenceSummaryModel.fromJson(Map<String, dynamic> json) {
    return ParcelEvidenceSummaryModel(
      id: parseInt(json['id']),
      orderNumber: json['order_number'] as String?,
      parcelCode: json['parcel_code'] as String?,
      status: json['status']?.toString() ?? 'PLACED',
      operationalStatus: json['operational_status']?.toString() ?? 'Placed',
      sellerName: json['seller_name']?.toString() ?? 'Merchant',
      recipientName: json['recipient_name'] as String?,
      createdAt: json['created_at']?.toString() ?? '',
      latestEvent: json['latest_event'] is Map
          ? ParcelTrackingEventModel.fromJson(json['latest_event'] as Map<String, dynamic>)
          : null,
      evidencePhotosCount: parseInt(json['evidence_photos_count'], 0),
    );
  }
}

class ParcelJourneyDetailModel {
  final int orderId;
  final String? orderNumber;
  final String? parcelCode;
  final String status;
  final String operationalStatus;
  final int? parcelWeightGrams;
  final String? sellerName;
  final String? recipientName;
  final String? deliveryAddress;
  final String nextAction;
  final OrderProgressModel? progress;
  final List<ParcelTrackingEventModel> events;
  final List<ParcelScanEventModel> scans;
  final List<ParcelDeliveryAttemptModel> attempts;
  final List<ParcelPhotoModel> photos;

  ParcelJourneyDetailModel({
    required this.orderId,
    this.orderNumber,
    this.parcelCode,
    required this.status,
    required this.operationalStatus,
    this.parcelWeightGrams,
    this.sellerName,
    this.recipientName,
    this.deliveryAddress,
    required this.nextAction,
    this.progress,
    this.events = const [],
    this.scans = const [],
    this.attempts = const [],
    this.photos = const [],
  });

  factory ParcelJourneyDetailModel.fromJson(Map<String, dynamic> json) {
    final order = json['order'] as Map<String, dynamic>? ?? {};
    final rawEvents = json['tracking_events'] as List<dynamic>? ?? [];
    final rawScans = json['scans'] as List<dynamic>? ?? [];
    final rawAttempts = json['delivery_attempts'] as List<dynamic>? ?? [];
    final rawPhotos = json['evidence_photos'] as List<dynamic>? ?? [];

    return ParcelJourneyDetailModel(
      orderId: parseInt(order['id']),
      orderNumber: order['order_number'] as String?,
      parcelCode: order['parcel_code'] as String?,
      status: order['status']?.toString() ?? '',
      operationalStatus: order['operational_status']?.toString() ?? '',
      parcelWeightGrams: parseNullableInt(order['parcel_weight_grams']),
      sellerName: order['seller_name'] as String?,
      recipientName: order['recipient_name'] as String?,
      deliveryAddress: order['delivery_address'] as String?,
      nextAction: json['next_action']?.toString() ?? 'Review parcel journey',
      progress: json['progress'] is Map
          ? OrderProgressModel.fromJson(json['progress'] as Map<String, dynamic>)
          : null,
      events: rawEvents.map((e) => ParcelTrackingEventModel.fromJson(e as Map<String, dynamic>)).toList(),
      scans: rawScans.map((s) => ParcelScanEventModel.fromJson(s as Map<String, dynamic>)).toList(),
      attempts: rawAttempts.map((a) => ParcelDeliveryAttemptModel.fromJson(a as Map<String, dynamic>)).toList(),
      photos: rawPhotos.map((p) => ParcelPhotoModel.fromJson(p as Map<String, dynamic>)).toList(),
    );
  }
}

class IncidentAssessmentModel {
  final String status;
  final String? liableParty;
  final String? resolutionSummary;
  final double? refundEstimatePesos;
  final String? assessedAt;

  IncidentAssessmentModel({
    required this.status,
    this.liableParty,
    this.resolutionSummary,
    this.refundEstimatePesos,
    this.assessedAt,
  });

  factory IncidentAssessmentModel.fromJson(Map<String, dynamic> json) {
    return IncidentAssessmentModel(
      status: json['status']?.toString() ?? 'PENDING',
      liableParty: json['liable_party']?.toString(),
      resolutionSummary: json['resolution_summary']?.toString(),
      refundEstimatePesos: parseNullableDouble(json['refund_estimate_pesos']),
      assessedAt: json['assessed_at']?.toString(),
    );
  }
}

class ParcelIncidentModel {
  final int id;
  final int orderId;
  final String orderNumber;
  final String? parcelCode;
  final String kind; // LOST or DAMAGED
  final String status; // OPEN or RESOLVED
  final String claimStatus;
  final String claimStatusLabel;
  final String recordedCustody;
  final String reason;
  final String? claimNotes;
  final bool hasReportEvidence;
  final bool hasResolutionEvidence;
  final String? reportEvidenceUrl;
  final String? resolutionEvidenceUrl;
  final String createdAt;
  final String? resolvedAt;
  final IncidentAssessmentModel? assessment;

  ParcelIncidentModel({
    required this.id,
    required this.orderId,
    required this.orderNumber,
    this.parcelCode,
    required this.kind,
    required this.status,
    required this.claimStatus,
    required this.claimStatusLabel,
    required this.recordedCustody,
    required this.reason,
    this.claimNotes,
    this.hasReportEvidence = false,
    this.hasResolutionEvidence = false,
    this.reportEvidenceUrl,
    this.resolutionEvidenceUrl,
    required this.createdAt,
    this.resolvedAt,
    this.assessment,
  });

  factory ParcelIncidentModel.fromJson(Map<String, dynamic> json) {
    return ParcelIncidentModel(
      id: parseInt(json['id']),
      orderId: parseInt(json['order_id']),
      orderNumber: json['order_number']?.toString() ?? '',
      parcelCode: json['parcel_code'] as String?,
      kind: json['kind']?.toString() ?? 'DAMAGED',
      status: json['status']?.toString() ?? 'OPEN',
      claimStatus: json['claim_status']?.toString() ?? 'RECORDED',
      claimStatusLabel: json['claim_status_label']?.toString() ?? (json['claim_status']?.toString().replaceAll('_', ' ') ?? 'Recorded'),
      recordedCustody: json['recorded_custody']?.toString() ?? 'Hold',
      reason: json['reason']?.toString() ?? '',
      claimNotes: json['claim_notes'] as String?,
      hasReportEvidence: json['has_report_evidence'] == true,
      hasResolutionEvidence: json['has_resolution_evidence'] == true,
      reportEvidenceUrl: json['report_evidence_url'] as String?,
      resolutionEvidenceUrl: json['resolution_evidence_url'] as String?,
      createdAt: json['created_at']?.toString() ?? '',
      resolvedAt: json['resolved_at'] as String?,
      assessment: json['assessment'] is Map
          ? IncidentAssessmentModel.fromJson(json['assessment'] as Map<String, dynamic>)
          : null,
    );
  }
}
