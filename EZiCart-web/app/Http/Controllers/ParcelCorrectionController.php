<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ParcelCorrection;
use App\Services\HubOrderScope;
use App\Services\LogisticsQueueService;
use App\Services\ParcelCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParcelCorrectionController extends Controller
{
    public function index(Request $request, ParcelCorrectionService $service)
    {
        $data = $request->validate(['status' => ['nullable', 'in:OPEN,RESOLVED,REJECTED,all'], 'search' => ['nullable', 'string', 'max:100']]);
        $actor = $request->user();
        $corrections = $service->query($actor)->with(['order:id,order_number', 'hub:id,first_name,last_name,business_name', 'requester:id,first_name,last_name', 'reviewer:id,first_name,last_name'])
            ->when(($data['status'] ?? 'OPEN') !== 'all', fn ($q) => $q->where('status', $data['status'] ?? 'OPEN'))
            ->when(filled($data['search'] ?? null), fn ($q) => $q->whereHas('order', fn ($order) => $order->whereRaw("order_number LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($data['search'])).'%'])))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return response()->view('logistics.corrections.index', compact('corrections', 'actor'))->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request, Order $order, ParcelCorrectionService $service)
    {
        $actor = $request->user();
        abort_unless($actor->role === 'sorting_center' && $actor->status === 'approved' && app(HubOrderScope::class)->query($actor)->whereKey($order->id)->exists(), 404);
        abort_unless(in_array($order->status, [...LogisticsQueueService::STATUSES, 'READY_FOR_PICKUP'], true), 422);
        $snapshot = $service->snapshot($order);

        return response()->view('logistics.corrections.create', compact('order', 'snapshot'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Order $order, ParcelCorrectionService $service)
    {
        abort_unless($request->user()->role === 'sorting_center' && app(HubOrderScope::class)->query($request->user())->whereKey($order->id)->exists(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:5120'],
            'status' => ['prohibited'], 'target_status' => ['prohibited']]);
        $path = $request->file('evidence')->store('parcel-corrections', 'private');
        abort_unless(is_string($path) && $path !== '', 422, 'Evidence could not be stored. Please retry.');
        try {
            $correction = $service->request($order, $request->user(), $data['reason'], $path);
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }

        return redirect()->route('logistics-corrections.show', $correction)->with('success', 'Correction review requested. No parcel status, custody, inventory or money changed.');
    }

    public function show(Request $request, ParcelCorrection $correction, ParcelCorrectionService $service)
    {
        $correction = $service->query($request->user())->whereKey($correction->id)->with(['order', 'hub', 'requester', 'reviewer', 'event.actor'])->firstOrFail();
        $current = $service->snapshot($correction->order);
        $events = $correction->order->trackingEvents()->where('id', '>', $correction->baseline_event_id ?? 0)->where('created_at', '>=', $correction->created_at)
            ->with('actor')->orderByDesc('id')->paginate(25)->withQueryString();
        $actor = $request->user();

        $evidenceIntact = $service->evidenceIntact($correction);

        return response()->view('logistics.corrections.show', compact('correction', 'current', 'events', 'actor', 'evidenceIntact'))->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, ParcelCorrection $correction, ParcelCorrectionService $service)
    {
        $data = $request->validate(['outcome' => ['required', 'in:RESOLVED,REJECTED'], 'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'tracking_event_id' => ['required_if:outcome,RESOLVED', 'nullable', 'integer', 'min:1'], 'status' => ['prohibited'], 'target_status' => ['prohibited']]);
        $service->review($correction, $request->user(), $data['outcome'], $data['reason'], isset($data['tracking_event_id']) ? (int) $data['tracking_event_id'] : null);

        return back()->with('success', 'Review decision recorded or identical decision retained. Closing review did not change the parcel or post money.');
    }

    public function evidence(Request $request, ParcelCorrection $correction, ParcelCorrectionService $service)
    {
        $correction = $service->query($request->user())->whereKey($correction->id)->firstOrFail();
        abort_unless(Storage::disk('private')->exists($correction->evidence_path), 404, 'Supporting evidence is unavailable.');
        abort_unless($service->evidenceIntact($correction), 409, 'Supporting evidence changed. Investigate the file discrepancy; it cannot be presented as the original upload.');

        return Storage::disk('private')->download($correction->evidence_path, 'correction-'.$correction->id.'.'.pathinfo($correction->evidence_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
