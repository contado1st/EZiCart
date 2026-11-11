<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ParcelEvidence;
use App\Models\ParcelTrackingEvent;
use App\Services\AdministrativeAuditService;
use App\Services\DirectMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ParcelEvidenceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);
        if (filled($data['reference'] ?? null)) {
            $reference = preg_replace('/^EZP:/', '', trim($data['reference']));
            $order = Order::where('parcel_code', $reference)->orWhere('order_number', $reference)->firstOrFail();
            app(DirectMessagingService::class)->authorizeOrder($order, $request->user());

            return redirect()->route('parcel-evidence.show', $order);
        }

        return view('orders.evidence', ['order' => null]);
    }

    public function show(Request $request, Order $order, DirectMessagingService $access)
    {
        $access->authorizeOrder($order, $request->user());
        $order->load(['seller', 'pickupCourier', 'deliveryCourier', 'sortingCenter']);
        $events = ParcelTrackingEvent::where('order_id', $order->id)->with('actor')->orderBy('id')->get();
        $scans = DB::table('scan_events')->join('users', 'users.id', '=', 'scan_events.actor_id')->where('order_id', $order->id)
            ->select('scan_events.*', 'users.first_name', 'users.last_name', 'users.role')->orderByDesc('scan_events.id')->get();
        $photos = ParcelEvidence::where('order_id', $order->id)->with('actor')->latest()->get()->groupBy('event_key');
        $attempts = $order->deliveryAttempts()->with('rider')->latest()->get();
        $nextAction = match ($order->status) {
            'PLACED' => 'Seller: confirm the order.', 'CONFIRMED' => 'Seller: prepare the parcel.', 'PREPARING' => 'Seller: mark ready and schedule pickup.',
            'READY_FOR_PICKUP' => 'Assigned pickup rider: record arrival. Seller: scan the rider badge and confirm handoff. Rider: scan the parcel to confirm possession.',
            'PICKED_UP' => 'Pickup rider: take the parcel to the hub. Hub: scan the parcel at intake.',
            'AT_SORTING_CENTER' => 'Hub: verify destination, sort and stage the parcel.', 'SORTED' => 'Hub: assign an eligible rider and prepare physical release.',
            'ASSIGNED_TO_RIDER' => $order->hub_released_at ? 'Assigned rider: start delivery after receiving the parcel.' : 'Hub: scan the assigned rider badge and parcel to record physical release.',
            'OUT_FOR_DELIVERY' => 'Assigned rider: obtain the buyer’s delivery code, proof photo and COD amount before confirming delivery.',
            'DELIVERY_FAILED' => 'Hub: review the recorded failure and recover the parcel before reassignment or return.',
            'DELIVERED' => 'Buyer: inspect the received parcel and confirm receipt.', 'COMPLETED' => 'Buyer: review the purchased product.',
            'RETURN_IN_TRANSIT' => 'Assigned rider and seller: complete the scanned return handoff.', default => 'This parcel has reached a resolved stage. Review the recorded history below.',
        };

        if (in_array($order->status, ['DELIVERED', 'COMPLETED'], true) && $order->dispute?->status && in_array($order->dispute->status, ['REFUND_APPROVED', 'REPLACEMENT_APPROVED'], true)) {
            $nextAction = 'Return approved, awaiting physical collection. Open Approved return pickups for assignment, acceptance and buyer handover.';
        }

        return view('orders.evidence', compact('order', 'events', 'scans', 'photos', 'attempts', 'nextAction'));
    }

    public function store(Request $request, Order $order, DirectMessagingService $access)
    {
        $access->authorizeOrder($order, $request->user());
        $data = $request->validate(['event_type' => ['required', 'in:scan,tracking'], 'event_id' => ['required', 'integer'],
            'photos' => ['required', 'array', 'min:1', 'max:3'], 'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000']]);
        $stored = [];
        try {
            DB::transaction(function () use ($request, $order, $data, &$stored) {
                Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $event = DB::table($data['event_type'] === 'scan' ? 'scan_events' : 'parcel_tracking_events')->where('id', $data['event_id'])->where('order_id', $order->id)->where('actor_id', $request->user()->id)->first();
                abort_unless($event && ($data['event_type'] !== 'scan' || $event->result === 'accepted'), 403);
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
                    if (! is_string($path)) {
                        throw new \RuntimeException('The parcel photo could not be saved.');
                    }
                    $stored[] = $path;
                    ParcelEvidence::create(['order_id' => $order->id, 'actor_id' => $request->user()->id,
                        'scan_event_id' => $data['event_type'] === 'scan' ? $event->id : null,
                        'tracking_event_id' => $data['event_type'] === 'tracking' ? $event->id : null,
                        'event_key' => $key, 'digest' => $digest, 'path' => $path, 'status' => $status,
                        'location' => $event->location ?? $event->station ?? null]);
                }
                app(AdministrativeAuditService::class)->record($request->user(), $order, 'parcel_event_evidence_saved', [], ['event' => $key, 'photos' => ParcelEvidence::where('order_id', $order->id)->where('event_key', $key)->count()], 'Private photos linked to an existing parcel event.');
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($stored);
            throw $e;
        }

        return back()->with('success', 'Parcel evidence recorded. Repeated uploads of the same photo are ignored.');
    }

    public function photo(Request $request, ParcelEvidence $evidence, DirectMessagingService $access)
    {
        $access->authorizeOrder($evidence->order, $request->user());
        abort_unless(Storage::disk('private')->exists($evidence->path), 404);

        return Storage::disk('private')->response($evidence->path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
