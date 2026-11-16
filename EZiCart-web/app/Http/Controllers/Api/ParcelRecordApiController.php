<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ParcelEvidence;
use App\Models\ParcelIncident;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Services\AdministrativeAuditService;
use App\Services\DirectMessagingService;
use App\Services\OrderProgressService;
use App\Services\ParcelIncidentService;
use App\Services\ReturnPickupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParcelRecordApiController extends Controller
{
    /**
     * List buyer parcels or lookup by reference
     */
    public function evidenceIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $reference = $request->input('reference');

        if (filled($reference)) {
            $cleanRef = preg_replace('/^EZP:/', '', trim($reference));
            $order = Order::where(function ($q) use ($cleanRef) {
                $q->where('parcel_code', $cleanRef)->orWhere('order_number', $cleanRef);
            })->first();

            if (! $order) {
                return response()->json(['success' => false, 'message' => 'Parcel reference not found.'], 404);
            }

            try {
                app(DirectMessagingService::class)->authorizeOrder($order, $user);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to view this parcel.'], 403);
            }

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
            ]);
        }

        $query = Order::query();
        if ($user->role === 'buyer') {
            $query->where('buyer_id', $user->id);
        } elseif ($user->role === 'courier') {
            $query->where(function ($q) use ($user) {
                $q->where('pickup_courier_id', $user->id)
                    ->orWhere('delivery_courier_id', $user->id)
                    ->orWhereHas('returnPickupAssignment', fn ($a) => $a->where('rider_id', $user->id))
                    ->orWhereHas('transfers', fn ($t) => $t->where('carrier_rider_id', $user->id));
            });
        }

        $orders = $query
            ->with(['seller:id,business_name', 'trackingEvents' => fn ($q) => $q->latest('id')])
            ->latest('id')
            ->paginate(20);

        $data = collect($orders->items())->map(function (Order $order) {
            $photoCount = ParcelEvidence::where('order_id', $order->id)->count();
            $latestEvent = $order->trackingEvents->first();

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'parcel_code' => $order->parcel_code,
                'status' => $order->status,
                'operational_status' => $order->operational_status,
                'seller_name' => $order->seller?->business_name ?? 'Merchant',
                'recipient_name' => $order->recipient_name,
                'created_at' => $order->created_at->toIso8601String(),
                'latest_event' => $latestEvent ? [
                    'event_type' => $latestEvent->event_type,
                    'status' => $latestEvent->status,
                    'location' => $latestEvent->location,
                    'created_at' => $latestEvent->created_at->toIso8601String(),
                ] : null,
                'evidence_photos_count' => $photoCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ]);
    }

    /**
     * Get unified parcel journey, tracking events, scans, and evidence photos
     */
    public function evidenceShow(int $id, Request $request, DirectMessagingService $access, OrderProgressService $progressService): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['seller', 'buyer', 'pickupCourier', 'deliveryCourier', 'sortingCenter', 'dispute', 'activeIncident'])
            ->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        try {
            $access->authorizeOrder($order, $user);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to access this parcel.'], 403);
        }

        $events = ParcelTrackingEvent::where('order_id', $order->id)->with('actor')->orderBy('id')->get();
        $scans = DB::table('scan_events')
            ->join('users', 'users.id', '=', 'scan_events.actor_id')
            ->where('order_id', $order->id)
            ->select('scan_events.*', 'users.first_name', 'users.last_name', 'users.role')
            ->orderByDesc('scan_events.id')
            ->get();
        $photos = ParcelEvidence::where('order_id', $order->id)->with('actor')->latest('id')->get()->groupBy('event_key');
        $attempts = $order->deliveryAttempts()->with('rider')->latest('id')->get();

        $nextAction = match ($order->status) {
            'PLACED' => 'Seller: confirm the order.',
            'CONFIRMED' => 'Seller: prepare the parcel.',
            'PREPARING' => 'Seller: mark ready and schedule pickup.',
            'READY_FOR_PICKUP' => 'Assigned pickup rider: record arrival. Seller: scan rider badge and confirm handoff.',
            'PICKED_UP' => 'Pickup rider: take parcel to the hub. Hub: scan parcel at intake.',
            'AT_SORTING_CENTER' => 'Hub: verify destination, sort and stage the parcel.',
            'SORTED' => 'Hub: assign an eligible rider and prepare physical release.',
            'ASSIGNED_TO_RIDER' => $order->hub_released_at ? 'Assigned rider: start delivery after receiving the parcel.' : 'Hub: scan the assigned rider badge and parcel to record physical release.',
            'OUT_FOR_DELIVERY' => $order->payment_method === 'WALLET' ? 'Assigned rider: obtain the buyer’s delivery code and proof photo. Verified prepaid parcels require PHP 0.00 cash collection.' : 'Assigned rider: obtain the buyer’s delivery code, proof photo and COD amount before confirming delivery.',
            'DELIVERY_FAILED' => 'Hub: review the recorded failure and recover parcel before reassignment.',
            'DELIVERED' => 'Buyer: inspect the received parcel and confirm receipt.',
            'COMPLETED' => 'Buyer: review the purchased product.',
            'RETURN_IN_TRANSIT' => 'Assigned rider and seller: complete the scanned return handoff.',
            default => 'This parcel has reached a resolved stage.',
        };

        if (in_array($order->status, ['DELIVERED', 'COMPLETED'], true) && $order->dispute?->status && in_array($order->dispute->status, ['REFUND_APPROVED', 'REPLACEMENT_APPROVED'], true)) {
            $nextAction = 'Return approved, awaiting physical collection. Open Approved return pickups for assignment, acceptance and buyer handover.';
        }

        $progress = $progressService->forViewer($order, $user);

        $eventsData = $events->map(function ($e) use ($photos) {
            $key = 'tracking:'.$e->id;
            $eventPhotos = ($photos->get($key) ?? collect())->map(fn ($p) => [
                'id' => $p->id,
                'url' => url('/api/parcel-evidence/'.$p->id.'/photo'),
                'created_at' => $p->created_at->toIso8601String(),
            ]);

            return [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'status' => $e->status,
                'location' => $e->location ?: 'Location not recorded',
                'notes' => $e->notes,
                'actor_name' => $e->actor ? trim("{$e->actor->first_name} {$e->actor->last_name}") : 'System',
                'created_at' => $e->created_at->toIso8601String(),
                'photos' => $eventPhotos,
            ];
        });

        $scansData = $scans->map(function ($s) use ($photos) {
            $key = 'scan:'.$s->id;
            $scanPhotos = ($photos->get($key) ?? collect())->map(fn ($p) => [
                'id' => $p->id,
                'url' => url('/api/parcel-evidence/'.$p->id.'/photo'),
                'created_at' => $p->created_at->toIso8601String(),
            ]);

            return [
                'id' => $s->id,
                'station' => $s->station,
                'result' => $s->result,
                'actor_name' => trim("{$s->first_name} {$s->last_name}"),
                'role' => str_replace('_', ' ', $s->role),
                'method' => $s->method,
                'failure_reason' => $s->failure_reason,
                'created_at' => (string) $s->created_at,
                'photos' => $scanPhotos,
            ];
        });

        $attemptsData = $attempts->map(function ($a) {
            return [
                'id' => $a->id,
                'attempt_no' => $a->attempt_no,
                'outcome' => $a->outcome,
                'reason' => $a->reason ? str_replace('_', ' ', $a->reason) : null,
                'rider_name' => $a->rider ? trim("{$a->rider->first_name} {$a->rider->last_name}") : null,
                'attempted_at' => $a->attempted_at?->toIso8601String(),
                'has_proof' => ! empty($a->proof_path),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'parcel_code' => $order->parcel_code,
                    'status' => $order->status,
                    'operational_status' => $order->operational_status,
                    'parcel_weight_grams' => $order->parcel_weight_grams,
                    'seller_name' => $order->seller?->business_name ?? 'Merchant',
                    'next_action' => $nextAction,
                    'active_incident' => $order->activeIncident ? [
                        'id' => $order->activeIncident->id,
                        'kind' => $order->activeIncident->kind,
                        'status' => $order->activeIncident->status,
                        'claim_status' => $order->activeIncident->claim_status,
                    ] : null,
                ],
                'progress' => $progress,
                'events' => $eventsData,
                'scans' => $scansData,
                'delivery_attempts' => $attemptsData,
            ],
        ]);
    }

    /**
     * Stream private parcel evidence photo
     */
    public function evidencePhoto(int $id, Request $request, DirectMessagingService $access): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $evidence = ParcelEvidence::with('order')->find($id);

        if (! $evidence) {
            return response()->json(['success' => false, 'message' => 'Evidence photo not found.'], 404);
        }

        try {
            $access->authorizeOrder($evidence->order, $user);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view this photo.'], 403);
        }

        if (! Storage::disk('private')->exists($evidence->path)) {
            return response()->json(['success' => false, 'message' => 'Photo file missing.'], 404);
        }

        return Storage::disk('private')->response($evidence->path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Store private parcel event evidence photos
     */
    public function evidenceStore(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        try {
            app(DirectMessagingService::class)->authorizeOrder($order, $user);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized evidence upload.'], 403);
        }

        $data = $request->validate([
            'event_type' => ['required', 'in:scan,tracking'],
            'event_id' => ['required', 'integer'],
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $stored = [];
        try {
            DB::transaction(function () use ($request, $order, $data, $user, &$stored) {
                Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $event = DB::table($data['event_type'] === 'scan' ? 'scan_events' : 'parcel_tracking_events')
                    ->where('id', $data['event_id'])
                    ->where('order_id', $order->id)
                    ->first();

                abort_unless($event, 404, 'Event not found.');
                $key = $data['event_type'].':'.$event->id;
                $status = $event->status ?? ParcelTrackingEvent::where('order_id', $order->id)->where('created_at', '<=', $event->created_at)->latest('id')->value('status') ?? 'Recorded scan';

                foreach ($request->file('photos') as $file) {
                    $digest = hash_file('sha256', $file->getRealPath());
                    if (ParcelEvidence::where('order_id', $order->id)->where('event_key', $key)->where('digest', $digest)->exists()) {
                        continue;
                    }
                    if (ParcelEvidence::where('order_id', $order->id)->where('event_key', $key)->count() >= 3) {
                        throw ValidationException::withMessages(['photos' => 'An event can contain at most three distinct photos.']);
                    }
                    $path = $file->store('parcel-evidence/'.$order->id, 'private');
                    $stored[] = $path;
                    ParcelEvidence::create([
                        'order_id' => $order->id,
                        'actor_id' => $user->id,
                        'scan_event_id' => $data['event_type'] === 'scan' ? $event->id : null,
                        'tracking_event_id' => $data['event_type'] === 'tracking' ? $event->id : null,
                        'event_key' => $key,
                        'digest' => $digest,
                        'path' => $path,
                        'status' => $status,
                        'location' => $event->location ?? $event->station ?? null,
                    ]);
                }

                app(AdministrativeAuditService::class)->record($user, $order, 'parcel_event_evidence_saved', [], ['event' => $key], 'Private photos linked to parcel event from mobile.');
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($stored);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Parcel evidence recorded.',
        ]);
    }

    /**
     * List parcel incidents & claims for buyer
     */
    public function incidentsIndex(Request $request, ParcelIncidentService $service): JsonResponse
    {
        $buyer = $request->user();
        $status = $request->input('status'); // 'OPEN' or 'RESOLVED'

        $incidents = $service->query($buyer)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['order:id,order_number,parcel_code,status,total_amount'])
            ->latest('id')
            ->paginate(20);

        $data = collect($incidents->items())->map(function (ParcelIncident $incident) {
            return [
                'id' => $incident->id,
                'order_id' => $incident->order_id,
                'order_number' => $incident->order->order_number,
                'parcel_code' => $incident->order->parcel_code,
                'kind' => $incident->kind,
                'status' => $incident->status,
                'claim_status' => $incident->claim_status,
                'claim_status_label' => str_replace('_', ' ', $incident->claim_status),
                'recorded_custody' => str_replace('_', ' ', $incident->order->status),
                'reason' => $incident->reason,
                'claim_notes' => $incident->claim_notes,
                'has_report_evidence' => ! empty($incident->evidence_path),
                'has_resolution_evidence' => ! empty($incident->resolution_evidence_path),
                'report_evidence_url' => $incident->evidence_path ? url('/api/v1/parcel-incidents/'.$incident->id.'/evidence/report') : null,
                'resolution_evidence_url' => $incident->resolution_evidence_path ? url('/api/v1/parcel-incidents/'.$incident->id.'/evidence/resolution') : null,
                'created_at' => $incident->created_at->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_page' => $incidents->currentPage(),
            'last_page' => $incidents->lastPage(),
            'total' => $incidents->total(),
        ]);
    }

    /**
     * Incident detail with claim assessment
     */
    public function incidentShow(int $id, Request $request, ParcelIncidentService $service): JsonResponse
    {
        $buyer = $request->user();
        $incident = $service->query($buyer)
            ->whereKey($id)
            ->with(['order.seller', 'reporter'])
            ->first();

        if (! $incident) {
            return response()->json(['success' => false, 'message' => 'Incident record not found.'], 404);
        }

        $assessment = $service->assessment($incident);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $incident->id,
                'order_id' => $incident->order_id,
                'order_number' => $incident->order->order_number,
                'parcel_code' => $incident->order->parcel_code,
                'order_status' => $incident->order->status,
                'kind' => $incident->kind,
                'status' => $incident->status,
                'claim_status' => $incident->claim_status,
                'claim_status_label' => str_replace('_', ' ', $incident->claim_status),
                'assessment' => $assessment,
                'assessment_label' => str_replace('_', ' ', $assessment),
                'reason' => $incident->reason,
                'claim_notes' => $incident->claim_notes,
                'resolution_reason' => $incident->resolution_reason,
                'has_report_evidence' => ! empty($incident->evidence_path),
                'has_resolution_evidence' => ! empty($incident->resolution_evidence_path),
                'report_evidence_url' => $incident->evidence_path ? url('/api/v1/parcel-incidents/'.$incident->id.'/evidence/report') : null,
                'resolution_evidence_url' => $incident->resolution_evidence_path ? url('/api/v1/parcel-incidents/'.$incident->id.'/evidence/resolution') : null,
                'reporter_name' => $incident->reporter ? trim("{$incident->reporter->first_name} {$incident->reporter->last_name}") : 'Logistics',
                'created_at' => $incident->created_at->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Download / stream incident private photo
     */
    public function incidentEvidence(int $id, string $which, Request $request, ParcelIncidentService $service): StreamedResponse|JsonResponse
    {
        $buyer = $request->user();
        $incident = $service->query($buyer)->whereKey($id)->first();

        if (! $incident || ! in_array($which, ['report', 'resolution'], true)) {
            return response()->json(['success' => false, 'message' => 'Evidence not found.'], 404);
        }

        $path = $which === 'report' ? $incident->evidence_path : $incident->resolution_evidence_path;
        $digest = $which === 'report' ? $incident->evidence_sha256 : $incident->resolution_evidence_sha256;

        if (! $path || ! Storage::disk('private')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'Evidence file missing.'], 404);
        }

        return Storage::disk('private')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * List approved return pickups for buyer and courier
     */
    public function returnPickupsIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::whereHas('dispute', fn ($query) => $query->whereIn('status', ['REFUND_APPROVED', 'REPLACEMENT_APPROVED']))
            ->whereIn('status', ['DELIVERED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])
            ->when($user->role === 'buyer', fn ($query) => $query->where('buyer_id', $user->id))
            ->when($user->role === 'courier', fn ($query) => $query->where(fn ($q) => $q->whereHas('returnPickupAssignment', fn ($assignment) => $assignment->where('rider_id', $user->id))
                ->orWhere(fn ($return) => $return->where('status', 'RETURN_IN_TRANSIT')->where('delivery_courier_id', $user->id))))
            ->with(['seller:id,business_name', 'buyer', 'dispute', 'returnPickupAssignment.rider:id,first_name,last_name,contact_no,plate_number', 'trackingEvents'])
            ->latest('id')
            ->paginate(20);

        $data = collect($orders->items())->map(function (Order $order) {
            $assignment = $order->returnPickupAssignment;
            $rider = $assignment?->rider;
            $handedOver = $assignment && $order->trackingEvents->where('event_type', 'return_buyer_handover')->where('actor_id', $order->buyer_id)->where('notes', 'Return pickup assignment #'.$assignment->id)->isNotEmpty();

            $statusMessage = ! $assignment
                ? 'Return approved. Logistics must assign a rider for collection from your address.'
                : (! $assignment->accepted_at
                    ? 'Return pickup assigned. Waiting for the rider to accept.'
                    : ($handedOver
                        ? 'You confirmed handover. The rider will scan the parcel to confirm possession.'
                        : 'Return rider accepted. Confirm handover only after physically giving the parcel to the rider.'));

            $canHandover = $assignment && $assignment->accepted_at !== null && ! $handedOver && in_array($order->status, ['DELIVERED', 'COMPLETED'], true);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'parcel_code' => $order->parcel_code,
                'status' => $order->status,
                'operational_status' => $order->operational_status,
                'seller_name' => $order->seller?->business_name ?? 'Merchant',
                'dispute_status' => $order->dispute?->status,
                'dispute_reason' => $order->dispute?->reason,
                'dispute_resolution' => $order->dispute?->resolution,
                'rider_name' => $rider ? trim("{$rider->first_name} {$rider->last_name}") : null,
                'rider_contact' => $rider?->contact_no,
                'rider_plate' => $rider?->plate_number,
                'is_assigned' => $assignment !== null,
                'is_rider_accepted' => $assignment?->accepted_at !== null,
                'is_buyer_handed_over' => $handedOver,
                'can_confirm_handover' => $canHandover,
                'status_message' => $statusMessage,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ]);
    }

    /**
     * Buyer confirms physical handover of return parcel to rider
     */
    public function returnPickupHandover(int $id, Request $request, ReturnPickupService $service): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        try {
            $service->handover($order, $buyer);

            return response()->json([
                'success' => true,
                'message' => 'Handover confirmed. The rider will scan the parcel label to confirm possession.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
