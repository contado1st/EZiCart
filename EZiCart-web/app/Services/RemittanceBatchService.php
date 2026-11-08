<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RemittanceBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RemittanceBatchService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function submit(User $rider, User $hub, array $financeIds, int $submitted, string $evidence, string $reason): RemittanceBatch
    {
        abort_unless($rider->status === 'approved' && $rider->role === 'courier' && $hub->status === 'approved' && $hub->role === 'sorting_center', 403);
        abort_unless(count($financeIds) > 0 && count($financeIds) <= 100 && count(array_unique($financeIds)) === count($financeIds) && $submitted >= 0 && strlen(trim($reason)) >= 5 && Storage::disk('private')->exists($evidence), 422, 'A batch requires unique collections, a reason and supporting evidence.');

        return DB::transaction(function () use ($rider, $hub, $financeIds, $submitted, $evidence, $reason): RemittanceBatch {
            $finances = OrderFinance::whereIn('id', $financeIds)->orderBy('id')->lockForUpdate()->get();
            abort_unless($finances->count() === count($financeIds), 422);
            foreach ($finances as $finance) {
                $order = Order::findOrFail($finance->order_id);
                abort_unless($finance->collector_id === $rider->id && $finance->remittance_status === 'COLLECTED' && $finance->collected_cents !== null && $order->payment_method === 'COD' && $order->sorting_center_id === $hub->id, 422, 'Every collection must belong to this rider and receiving hub and be unsubmitted COD cash.');
                abort_if(DB::table('remittance_batch_items')->where('order_finance_id', $finance->id)->exists(), 422, 'A collection already belongs to a batch.');
            }
            $expected = (int) $finances->sum('collected_cents');
            $batch = RemittanceBatch::create(['reference' => (string) Str::uuid(), 'rider_id' => $rider->id, 'hub_id' => $hub->id, 'submitted_by' => $rider->id, 'expected_cents' => $expected, 'submitted_cents' => $submitted, 'evidence_path' => $evidence, 'reason' => $reason, 'status' => $expected === $submitted ? 'SUBMITTED' : 'DISCREPANCY', 'submitted_at' => now()]);
            foreach ($finances as $finance) {
                DB::table('remittance_batch_items')->insert(['batch_id' => $batch->id, 'order_finance_id' => $finance->id, 'expected_cents' => $finance->collected_cents]);
                $finance->update(['remittance_status' => 'BATCH_SUBMITTED', 'submitted_by' => $rider->id, 'submitted_at' => now()]);
            }
            $this->event($batch, $rider, 'submitted', ['expected_cents' => $expected, 'submitted_cents' => $submitted, 'evidence_path' => $evidence, 'reason' => $reason]);

            return $batch;
        }, 3);
    }

    public function receive(RemittanceBatch $batch, User $actor, int $received, string $receipt, string $reason): void
    {
        DB::transaction(function () use ($batch, $actor, $received, $receipt, $reason): void {
            $locked = RemittanceBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor);
            abort_unless(in_array($locked->status, ['SUBMITTED', 'DISCREPANCY'], true) && $locked->received_cents === null && $received >= 0 && strlen(trim($reason)) >= 5 && Storage::disk('private')->exists($receipt), 422, 'Record the first physical cash receipt with evidence and a reason.');
            $locked->update(['received_cents' => $received, 'discrepancy_cents' => $received - $locked->expected_cents, 'receipt_path' => $receipt, 'status' => 'DISCREPANCY']);
            $this->event($locked, $actor, 'cash_received', ['received_cents' => $received, 'submitted_cents' => $locked->submitted_cents, 'expected_cents' => $locked->expected_cents, 'receipt_path' => $receipt, 'reason' => $reason]);
            if ($received === $locked->expected_cents && $locked->submitted_cents === $locked->expected_cents) {
                $this->verify($locked, $actor, $reason);
            }
        });
    }

    public function investigate(RemittanceBatch $batch, User $actor, string $reason): void
    {
        DB::transaction(function () use ($batch, $actor, $reason): void {
            $locked = RemittanceBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor);
            abort_unless($actor->isLogisticsAdmin() && $locked->status === 'DISCREPANCY' && strlen(trim($reason)) >= 5, 422);
            $locked->update(['status' => 'UNDER_INVESTIGATION']);
            $this->event($locked, $actor, 'investigation_opened', ['reason' => $reason]);
        });
    }

    public function reconcile(RemittanceBatch $batch, User $actor, int $receivedTotal, string $receipt, string $reason): void
    {
        DB::transaction(function () use ($batch, $actor, $receivedTotal, $receipt, $reason): void {
            $locked = RemittanceBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor);
            abort_unless($actor->isLogisticsAdmin() && $locked->status === 'UNDER_INVESTIGATION' && $receivedTotal === $locked->expected_cents && strlen(trim($reason)) >= 5 && Storage::disk('private')->exists($receipt), 422, 'Investigate and evidence the exact cumulative cash received before reconciliation.');
            $this->event($locked, $actor, 'discrepancy_resolved', ['original_submitted_cents' => $locked->submitted_cents, 'previous_received_cents' => $locked->received_cents, 'received_total_cents' => $receivedTotal, 'previous_receipt_path' => $locked->receipt_path, 'receipt_path' => $receipt, 'reason' => $reason]);
            $locked->update(['received_cents' => $receivedTotal, 'discrepancy_cents' => 0, 'receipt_path' => $receipt]);
            $this->verify($locked, $actor, $reason);
        });
    }

    public function authorize(RemittanceBatch $batch, User $actor): void
    {
        abort_unless($actor->status === 'approved' && $actor->id !== $batch->submitted_by && ($actor->isLogisticsAdmin() || ($actor->role === 'sorting_center' && $actor->id === $batch->hub_id)), 403);
    }

    private function verify(RemittanceBatch $batch, User $actor, string $reason): void
    {
        $items = DB::table('remittance_batch_items')->where('batch_id', $batch->id)->orderBy('order_finance_id')->get();
        foreach ($items as $item) {
            $finance = OrderFinance::whereKey($item->order_finance_id)->lockForUpdate()->firstOrFail();
            abort_unless($finance->remittance_status === 'BATCH_SUBMITTED', 422, 'This batch collection changed; investigate before verification.');
            $finance->update(['remittance_status' => 'VERIFIED', 'remitted_cents' => $item->expected_cents, 'remittance_reference' => 'batch:'.$batch->reference.':'.$finance->id, 'receipt_path' => $batch->receipt_path, 'verified_by' => $actor->id, 'verified_at' => now()]);
            app(OrderWalletService::class)->stageFunds($finance->order, $finance);
        }
        $batch->update(['status' => 'VERIFIED', 'verified_by' => $actor->id, 'verified_at' => now()]);
        $this->event($batch, $actor, 'verified', ['received_cents' => $batch->received_cents, 'reason' => $reason]);
    }

    private function event(RemittanceBatch $batch, User $actor, string $type, array $details): void
    {
        DB::table('remittance_batch_events')->insert(['batch_id' => $batch->id, 'actor_id' => $actor->id, 'type' => $type, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        $this->audits->record($actor, $batch, 'remittance_batch_'.$type, [], $details, $details['reason'] ?? $type);
    }
}
