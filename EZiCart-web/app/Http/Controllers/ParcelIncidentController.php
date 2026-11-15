<?php

namespace App\Http\Controllers;

use App\Models\ParcelIncident;
use App\Services\ParcelIncidentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParcelIncidentController extends Controller
{
    public function index(Request $request, ParcelIncidentService $service)
    {
        $data = $request->validate(['status' => ['nullable', 'in:OPEN,RESOLVED']]);
        $status = $data['status'] ?? null;
        $incidents = $service->query($request->user())->when($status, fn ($q) => $q->where('status', $status))->with('order')->latest('id')->paginate(25)->withQueryString();
        $reportOrders = in_array($request->user()->role, ['sorting_center', 'logistics_admin'], true) ? $service->orders($request->user())->whereNotIn('status', ['PLACED', 'CONFIRMED', 'PREPARING', 'CANCELLED'])->latest('id')->limit(100)->get() : collect();

        return response()->view('logistics.incidents.index', compact('incidents', 'reportOrders', 'status'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, ParcelIncidentService $service)
    {
        $data = $request->validate(['order_id' => ['required', 'integer'], 'operation_key' => ['required', 'uuid'], 'kind' => ['required', 'in:LOST,DAMAGED'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'evidence' => ['required_if:kind,DAMAGED', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'], 'status' => ['prohibited']]);
        $order = $service->orders($request->user())->findOrFail($data['order_id']);
        $path = $request->file('evidence')?->store('parcel-incidents', 'private');
        try {
            $incident = $service->report($order, $request->user(), $data, $path);
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('private')->delete($path);
            } throw $error;
        }
        if ($path && $incident->evidence_path !== $path) {
            Storage::disk('private')->delete($path);
        }

        return redirect()->route('parcel-incidents.show', $incident)->with('success', 'Incident reported and claim review opened. No inventory, cash, refund, vehicle custody or payout was changed.');
    }

    public function show(Request $request, ParcelIncident $incident, ParcelIncidentService $service)
    {
        $incident = $service->query($request->user())->whereKey($incident->id)->with(['order.buyer', 'order.seller', 'order.finance', 'reporter'])->firstOrFail();
        $assessment = $service->assessment($incident);

        return response()->view('logistics.incidents.show', compact('incident', 'assessment'))->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, ParcelIncident $incident, ParcelIncidentService $service)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $service->reviewClaim($incident, $request->user(), $data['version'], $data['reason']);

        return back()->with('success', 'Payment/funding assessment recorded. No refund, compensation, debt or earnings were posted, and custody hold is unchanged.');
    }

    public function clear(Request $request, ParcelIncident $incident, ParcelIncidentService $service)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'parcel_reference' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'evidence' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
        $path = $request->file('evidence')->store('parcel-incidents/reconciliation', 'private');
        try {
            $service->clear($incident, $request->user(), $data['version'], $data['parcel_reference'], $data['reason'], $path);
        } catch (Throwable $error) {
            Storage::disk('private')->delete($path);
            throw $error;
        }

        return back()->with('success', 'Physical label and supporting photo reconciliation recorded. Incident hold cleared; normal custody and financial controls remain.');
    }

    public function evidence(Request $request, ParcelIncident $incident, string $which, ParcelIncidentService $service)
    {
        $incident = $service->query($request->user())->whereKey($incident->id)->firstOrFail();
        abort_unless(in_array($which, ['report', 'resolution'], true), 404);
        $path = $which === 'report' ? $incident->evidence_path : $incident->resolution_evidence_path;
        $digest = $which === 'report' ? $incident->evidence_sha256 : $incident->resolution_evidence_sha256;
        abort_unless($path && $digest && hash_equals($digest, $service->digest($path)), 409, 'Original evidence is missing or changed; investigate the discrepancy.');

        return Storage::disk('private')->download($path, basename($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
