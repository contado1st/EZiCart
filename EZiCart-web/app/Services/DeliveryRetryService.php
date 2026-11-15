<?php

namespace App\Services;

use App\Models\DeliveryAttempt;
use App\Models\DeliveryRetryRequest;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryRetryService
{
    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    /** @param Collection<int, Order> $orders */
    public function contexts(Collection $orders, User $rider, bool $includeRecorded = true): array
    {
        if ($orders->isEmpty()) {
            return [];
        }
        $ids = $orders->modelKeys();
        $counts = DeliveryAttempt::whereIn('order_id', $ids)->where('outcome', 'failed')->selectRaw('order_id, COUNT(*) AS failures')->groupBy('order_id')->pluck('failures', 'order_id');
        $latest = DeliveryAttempt::whereIn('id', DeliveryAttempt::selectRaw('MAX(id)')->whereIn('order_id', $ids)->where('outcome', 'failed')->groupBy('order_id'))->get()->keyBy('order_id');
        $placements = DB::table('parcel_placements')->join('storage_locations', 'storage_locations.id', '=', 'parcel_placements.location_id')
            ->whereIn('parcel_placements.order_id', $ids)->whereNull('removed_at')->where('storage_locations.is_active', true)->whereIn('storage_locations.type', ['RETURNS', 'EXCEPTION'])
            ->select('parcel_placements.order_id', 'storage_locations.hub_id')->get()->keyBy('order_id');
        $scans = DB::table('scan_events')->whereIn('order_id', $ids)->where('station', 'failed_return_intake')->where('result', 'accepted')
            ->orderByDesc('id')->get(['order_id', 'actor_id', 'created_at'])->groupBy('order_id');
        $transfers = DB::table('parcel_transfers')->whereIn('active_order_id', $ids)->pluck('active_order_id')->flip();
        $requests = DeliveryRetryRequest::whereIn('order_id', $ids)->where('requested_by', $rider->id)->orderByDesc('id')->get()->groupBy('order_id');
        $result = [];
        foreach ($orders as $order) {
            $failure = $latest->get($order->id);
            $count = (int) ($counts[$order->id] ?? 0);
            $scan = $scans->get($order->id, collect())->first(fn ($scan) => (int) $scan->actor_id === (int) $order->sorting_center_id);
            $placement = $placements->get($order->id);
            $reason = match (true) {
                ! $failure => 'No failed attempt is recorded.',
                (int) $failure->rider_id !== $rider->id => 'Only the rider of the latest failed attempt can propose a retry.',
                $count >= max(1, (int) config('logistics.maximum_delivery_attempts', 3)) => 'Attempt limit reached. Logistics must arrange the physical seller return.',
                $order->status !== 'SORTED' => 'Wait for logistics to scan the parcel back into a Returns or Exception hub location.',
                $transfers->has($order->id) => 'An active hub transfer must be completed before a local retry can be proposed.',
                ! $failure->attempted_at || ! $order->delivery_recovered_at || $order->delivery_recovered_at->lt($failure->attempted_at)
                    || ! $scan || Carbon::parse($scan->created_at)->lt($failure->attempted_at)
                    || ! $placement || (int) $placement->hub_id !== (int) $order->sorting_center_id => 'Current scanned hub custody is not verified. Ask logistics to review the intake record.',
                default => null,
            };
            $request = $requests->get($order->id, collect())->first();
            if ($includeRecorded && $request && (int) $request->failed_attempt_id === $failure?->id) {
                $reason = 'A proposal for this failed attempt is already recorded. Review its decision or contact logistics.';
            }
            $result[$order->id] = ['failures' => $count, 'latest_failure' => $failure, 'can_request' => $reason === null, 'reason' => $reason, 'request' => $request];
        }

        return $result;
    }

    public function request(Order $order, User $actor, int $attemptId, string $date, string $reason): DeliveryRetryRequest
    {
        $proposed = Carbon::parse($date)->setTimezone(config('app.timezone'));
        $reason = trim($reason);
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:500']])->validate();

        return DB::transaction(function () use ($order, $actor, $attemptId, $proposed, $reason): DeliveryRetryRequest {
            $actor = app(ParcelLoadService::class)->lockVehicle($actor->id);
            abort_unless($actor->role === 'courier' && $actor->status === 'approved', 403);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = DeliveryRetryRequest::where('failed_attempt_id', $attemptId)->where('order_id', $locked->id)->where('requested_by', $actor->id)->lockForUpdate()->first();
            if ($existing) {
                if (! $existing->proposed_for->equalTo($proposed) || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['retry_request' => 'This failed attempt already has a different proposal. Contact logistics rather than replacing its history.']);
                }

                return $existing;
            }
            $context = $this->contexts(new Collection([$locked]), $actor)[$locked->id];
            abort_unless($context['latest_failure'] && (int) $context['latest_failure']->rider_id === $actor->id, 404);
            if ($context['latest_failure']->id !== $attemptId || ! $context['can_request']) {
                throw ValidationException::withMessages(['retry_request' => $context['reason'] ?? 'The latest failed attempt changed. Refresh the work history.']);
            }
            if (! $proposed->isFuture()) {
                throw ValidationException::withMessages(['proposed_for' => 'Choose a future retry time.']);
            }
            $request = DeliveryRetryRequest::create(['order_id' => $locked->id, 'failed_attempt_id' => $attemptId, 'requested_by' => $actor->id,
                'hub_id' => $locked->sorting_center_id, 'proposed_for' => $proposed, 'reason' => $reason]);
            $this->record($request, $locked, $actor, 'delivery_retry_requested', 'PENDING', 'Retry proposal only. No assignment, attempt, custody or money was changed.');
            $this->notifications->send($locked->sortingCenter, new WebOperationsNotification(['message' => 'A rider proposed a delivery retry for '.$locked->order_number.'. Review scanned hub custody and dispatch eligibility.', 'url' => route('logistics.dispatch').'#retry-requests']));

            return $request;
        }, 3);
    }

    /** Called inside normal dispatch's transaction, before its validated assignment transition. */
    public function resolveOnAssignment(Order $order, User $hub, ?int $requestId, ?string $scheduledAt, int $assignedRiderId): void
    {
        $pending = DeliveryRetryRequest::where('order_id', $order->id)->where('status', 'PENDING')->lockForUpdate()->get();
        if ($pending->isEmpty() && $requestId === null) {
            return;
        }
        abort_unless($hub->role === 'sorting_center' && $hub->status === 'approved' && (int) $order->sorting_center_id === $hub->id, 403);
        if ($requestId !== null) {
            $chosen = $pending->firstWhere('id', $requestId);
            abort_unless($chosen && (int) $chosen->hub_id === $hub->id && (int) $order->sorting_center_id === $hub->id, 404);
            $actor = User::findOrFail($chosen->requested_by);
            $context = $this->contexts(new Collection([$order]), $actor, includeRecorded: false)[$order->id];
            $reason = $context['latest_failure']?->id === (int) $chosen->failed_attempt_id ? $context['reason'] : 'The latest failed attempt changed. Refresh dispatch.';
            if ($reason !== null || ! $scheduledAt || ! Carbon::parse($scheduledAt)->isFuture()) {
                throw ValidationException::withMessages(['retry_request_id' => $reason ?? 'Choose a future dispatch schedule.']);
            }
        }
        foreach ($pending as $request) {
            $status = $request->id === $requestId ? 'SCHEDULED' : 'SUPERSEDED';
            $this->decide($request, $order, $hub, $status, $status === 'SCHEDULED' ? 'Scheduled through validated hub dispatch.' : 'Logistics assigned this parcel through dispatch; this proposal is no longer pending.',
                $status === 'SCHEDULED' ? Carbon::parse($scheduledAt) : null, ['assigned_rider_id' => $assignedRiderId]);
        }
    }

    /** Runs inside physical hub-transfer release's order-locked transaction. */
    public function supersedeForTransfer(Order $order, User $actor, int $transferId): void
    {
        abort_unless($actor->role === 'sorting_center' && $actor->status === 'approved' && (int) $order->sorting_center_id === $actor->id, 403);
        foreach (DeliveryRetryRequest::where('order_id', $order->id)->where('status', 'PENDING')->lockForUpdate()->get() as $request) {
            $this->decide($request, $order, $actor, 'SUPERSEDED', 'Parcel released for hub transfer. Follow the destination hub dispatch; this local retry proposal is no longer pending.', null, ['transfer_id' => $transferId]);
        }
    }

    public function reject(DeliveryRetryRequest $request, User $actor, string $reason): void
    {
        $reason = trim($reason);
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:1000']])->validate();
        DB::transaction(function () use ($request, $actor, $reason): void {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($request->order_id)->lockForUpdate()->firstOrFail();
            $locked = DeliveryRetryRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'sorting_center' && $actor->status === 'approved' && (int) $locked->hub_id === $actor->id && (int) $order->sorting_center_id === $actor->id, 404);
            if ($locked->status === 'REJECTED' && $locked->decision_reason === $reason) {
                return;
            }
            abort_unless($locked->status === 'PENDING', 422, 'This retry proposal was already reviewed.');
            $this->decide($locked, $order, $actor, 'REJECTED', $reason);
        }, 3);
    }

    private function decide(DeliveryRetryRequest $request, Order $order, User $actor, string $status, string $reason, ?Carbon $schedule = null, array $extra = []): void
    {
        $request->update(['status' => $status, 'scheduled_for' => $schedule, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'decision_reason' => $reason]);
        $this->record($request, $order, $actor, 'delivery_retry_'.strtolower($status), $status, $reason, $extra);
        $this->notifyRider($request, $order);
    }

    private function record(DeliveryRetryRequest $request, Order $order, User $actor, string $event, string $status, string $reason, array $extra = []): void
    {
        $this->audits->record($actor, $request, $event, $status === 'PENDING' ? [] : ['status' => 'PENDING'], ['status' => $status, 'failed_attempt_id' => $request->failed_attempt_id, 'proposed_for' => $request->proposed_for->toIso8601String(), 'scheduled_for' => $request->scheduled_for?->toIso8601String(), ...$extra], $reason);
        ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $actor->id, 'event_type' => $event, 'status' => $order->status, 'notes' => 'Retry proposal #'.$request->id.': '.$reason]);
    }

    private function notifyRider(DeliveryRetryRequest $request, Order $order): void
    {
        $this->notifications->send($request->rider, new WebOperationsNotification(['message' => 'Retry proposal for '.$order->order_number.': '.strtolower($request->status).'. '.$request->decision_reason, 'url' => route('courier.history', ['search' => $order->order_number])]));
    }
}
