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
  final String sellerName;
  final String nextAction;
  final ParcelIncidentModel? activeIncident;
  final OrderProgressModel? progress;
  final List<ParcelTrackingEventModel> events;
  final List<ParcelScanEventModel> scans;
  final List<ParcelDeliveryAttemptModel> deliveryAttempts;

  ParcelJourneyDetailModel({
    required this.orderId,
    this.orderNumber,
    this.parcelCode,
    required this.status,
    required this.operationalStatus,
    this.parcelWeightGrams,
    required this.sellerName,
    required this.nextAction,
    this.activeIncident,
    this.progress,
    this.events = const [],
    this.scans = const [],
    this.deliveryAttempts = const [],
  });

  factory ParcelJourneyDetailModel.fromJson(Map<String, dynamic> json) {
    final orderMap = json['order'] as Map<String, dynamic>? ?? {};
    final rawEvents = json['events'] as List<dynamic>? ?? [];
    final rawScans = json['scans'] as List<dynamic>? ?? [];
    final rawAttempts = json['delivery_attempts'] as List<dynamic>? ?? [];

    return ParcelJourneyDetailModel(
      orderId: parseInt(orderMap['id']),
      orderNumber: orderMap['order_number'] as String?,
      parcelCode: orderMap['parcel_code'] as String?,
      status: orderMap['status']?.toString() ?? 'PLACED',
      operationalStatus: orderMap['operational_status']?.toString() ?? 'Placed',
      parcelWeightGrams: parseNullableInt(orderMap['parcel_weight_grams']),
      sellerName: orderMap['seller_name']?.toString() ?? 'Merchant',
      nextAction: orderMap['next_action']?.toString() ?? 'No action required',
      activeIncident: orderMap['active_incident'] is Map
          ? ParcelIncidentModel.fromJson(orderMap['active_incident'] as Map<String, dynamic>)
          : null,
      progress: json['progress'] is Map
          ? OrderProgressModel.fromJson(json['progress'] as Map<String, dynamic>)
          : null,
      events: rawEvents.map((e) => ParcelTrackingEventModel.fromJson(e as Map<String, dynamic>)).toList(),
      scans: rawScans.map((s) => ParcelScanEventModel.fromJson(s as Map<String, dynamic>)).toList(),
      deliveryAttempts: rawAttempts.map((a) => ParcelDeliveryAttemptModel.fromJson(a as Map<String, dynamic>)).toList(),
    );
  }
}

class ParcelIncidentModel {
  final int id;
  final int? orderId;
  final String? orderNumber;
  final String? parcelCode;
  final String? orderStatus;
  final String kind;
  final String status;
  final String claimStatus;
  final String claimStatusLabel;
  final String? assessment;
  final String? assessmentLabel;
  final String? reason;
  final String? claimNotes;
  final String? resolutionReason;
  final bool hasReportEvidence;
  final bool hasResolutionEvidence;
  final String? reportEvidenceUrl;
  final String? resolutionEvidenceUrl;
  final String? reporterName;
  final String createdAt;
  final String? resolvedAt;

  ParcelIncidentModel({
    required this.id,
    this.orderId,
    this.orderNumber,
    this.parcelCode,
    this.orderStatus,
    required this.kind,
    required this.status,
    required this.claimStatus,
    required this.claimStatusLabel,
    this.assessment,
    this.assessmentLabel,
    this.reason,
    this.claimNotes,
    this.resolutionReason,
    this.hasReportEvidence = false,
    this.hasResolutionEvidence = false,
    this.reportEvidenceUrl,
    this.resolutionEvidenceUrl,
    this.reporterName,
    required this.createdAt,
    this.resolvedAt,
  });

  factory ParcelIncidentModel.fromJson(Map<String, dynamic> json) {
    return ParcelIncidentModel(
      id: parseInt(json['id']),
      orderId: parseNullableInt(json['order_id']),
      orderNumber: json['order_number'] as String?,
      parcelCode: json['parcel_code'] as String?,
      orderStatus: json['order_status']?.toString() ?? json['recorded_custody'] as String?,
      kind: json['kind']?.toString() ?? 'Incident',
      status: json['status']?.toString() ?? 'OPEN',
      claimStatus: json['claim_status']?.toString() ?? 'REVIEW_PENDING',
      claimStatusLabel: json['claim_status_label']?.toString() ?? (json['claim_status']?.toString().replaceAll('_', ' ') ?? 'Review Pending'),
      assessment: json['assessment'] as String?,
      assessmentLabel: json['assessment_label'] as String?,
      reason: json['reason'] as String?,
      claimNotes: json['claim_notes'] as String?,
      resolutionReason: json['resolution_reason'] as String?,
      hasReportEvidence: json['has_report_evidence'] == true,
      hasResolutionEvidence: json['has_resolution_evidence'] == true,
      reportEvidenceUrl: json['report_evidence_url'] as String?,
      resolutionEvidenceUrl: json['resolution_evidence_url'] as String?,
      reporterName: json['reporter_name'] as String?,
      createdAt: json['created_at']?.toString() ?? '',
      resolvedAt: json['resolved_at'] as String?,
    );
  }
}

class ReturnPickupModel {
  final int id;
  final String? orderNumber;
  final String? parcelCode;
  final String status;
  final String operationalStatus;
  final String sellerName;
  final String? disputeStatus;
  final String? disputeReason;
  final String? disputeResolution;
  final String? riderName;
  final String? riderContact;
  final String? riderPlate;
  final bool isAssigned;
  final bool isRiderAccepted;
  final bool isBuyerHandedOver;
  final bool canConfirmHandover;
  final String statusMessage;

  ReturnPickupModel({
    required this.id,
    this.orderNumber,
    this.parcelCode,
    required this.status,
    required this.operationalStatus,
    required this.sellerName,
    this.disputeStatus,
    this.disputeReason,
    this.disputeResolution,
    this.riderName,
    this.riderContact,
    this.riderPlate,
    this.isAssigned = false,
    this.isRiderAccepted = false,
    this.isBuyerHandedOver = false,
    this.canConfirmHandover = false,
    required this.statusMessage,
  });

  factory ReturnPickupModel.fromJson(Map<String, dynamic> json) {
    return ReturnPickupModel(
      id: parseInt(json['id']),
      orderNumber: json['order_number'] as String?,
      parcelCode: json['parcel_code'] as String?,
      status: json['status']?.toString() ?? '',
      operationalStatus: json['operational_status']?.toString() ?? '',
      sellerName: json['seller_name']?.toString() ?? 'Merchant',
      disputeStatus: json['dispute_status'] as String?,
      disputeReason: json['dispute_reason'] as String?,
      disputeResolution: json['dispute_resolution'] as String?,
      riderName: json['rider_name'] as String?,
      riderContact: json['rider_contact'] as String?,
      riderPlate: json['rider_plate'] as String?,
      isAssigned: json['is_assigned'] == true,
      isRiderAccepted: json['is_rider_accepted'] == true,
      isBuyerHandedOver: json['is_buyer_handed_over'] == true,
      canConfirmHandover: json['can_confirm_handover'] == true,
      statusMessage: json['status_message']?.toString() ?? '',
    );
  }
}
