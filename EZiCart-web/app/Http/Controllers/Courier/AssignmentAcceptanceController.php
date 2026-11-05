<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AdministrativeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentAcceptanceController extends Controller
{
    public function __invoke(Request $request, Order $order, AdministrativeAuditService $audits)
    {
        DB::transaction(function () use ($request, $order, $audits) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->delivery_courier_id === $request->user()->id, 403);
            abort_unless($locked->status === 'ASSIGNED_TO_RIDER' && $locked->hub_released_at === null, 422);
            $assignment = $locked->deliveryAssignments()->where('status', 'active')->where('rider_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($assignment->accepted_at) {
                return;
            }
            abort_if($assignment->assigned_at->copy()->addMinutes((int) config('commerce.rider_acceptance_minutes', 15))->lte(now()), 422, 'Assignment expired. Logistics must assign the parcel again.');
            $assignment->update(['accepted_at' => now()]);
            $audits->record($request->user(), $locked, 'rider_assignment_accepted', [], ['accepted_at' => now()->toIso8601String()], 'Rider accepted before the dispatch confirmation deadline.');
        });

        return back()->with('success', 'Assignment accepted. Await the scanned hub handoff.');
    }
}
