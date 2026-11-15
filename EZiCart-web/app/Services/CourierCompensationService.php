<?php

namespace App\Services;

use App\Models\DeliveryRateVersion;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\ShipmentFinancial;
use App\Models\ShipmentPriceAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourierCompensationService
{
    /**
     * Read-only presentation, never a settlement or eligibility decision. One result per order.
     *
     * @param  ModelCollection<int, Order>  $orders
     */
    public function summaries(ModelCollection $orders, User $rider): array
    {
        if ($orders->isEmpty()) {
            return [];
        }
        $orders->loadMissing(['transfers' => fn ($q) => $q->where('carrier_rider_id', $rider->id),
            'deliveryAttempts' => fn ($q) => $q->where('rider_id', $rider->id), 'dispute', 'refundExecutions']);
        $shipments = ShipmentFinancial::whereIn('order_id', $orders->modelKeys())->get()->keyBy('order_id');
        // Match settlement: the latest APPROVED adjustment replaces the original quote.
        $adjustments = ShipmentPriceAdjustment::whereIn('id', ShipmentPriceAdjustment::selectRaw('MAX(id)')
            ->whereIn('order_id', $orders->modelKeys())->where('status', 'APPROVED')->groupBy('order_id'))->get()->keyBy('order_id');
        $rateIds = $shipments->pluck('rate_version_id')->merge($adjustments->map(fn ($adjustment) => $adjustment->revised_quote['rate_version_id'] ?? null))->filter()->unique();
        $approvedRates = DeliveryRateVersion::whereIn('id', $rateIds)->where('status', 'APPROVED')->pluck('id')->flip();
        $allocations = DB::table('logistics_allocations')->whereIn('order_id', $orders->modelKeys())
            ->where('beneficiary_id', $rider->id)->orderBy('id')->get();
        $posted = collect($this->postings($allocations, $rider))->groupBy('order_id');
        $result = [];
        foreach ($orders as $order) {
            $rows = $posted->get($order->id, collect())->values()->all();
            $shipment = $shipments->get($order->id);
            $quote = $adjustments->get($order->id)?->revised_quote ?? $shipment?->quote_snapshot;
            $jobs = [];
            if ((int) $order->pickup_courier_id === $rider->id) {
                $jobs[] = ['pickup', '', $order->picked_up_at];
            }
            if ((int) $order->delivery_courier_id === $rider->id) {
                $delivery = $order->deliveryAttempts->first(fn ($attempt) => (int) $attempt->rider_id === $rider->id && $attempt->outcome === 'delivered' && filled($attempt->proof_path));
                $jobs[] = ['last_mile', '', $delivery?->attempted_at];
                if ($order->return_handed_to_seller_at) {
                    $jobs[] = ['return_to_sender', '', $order->return_handed_to_seller_at];
                }
            }
            foreach ($order->transfers as $transfer) {
                if ((int) $transfer->carrier_rider_id === $rider->id && in_array($transfer->status, ['IN_TRANSIT', 'RECEIVED'], true)) {
                    $jobs[] = ['linehaul', 'transfer-'.$transfer->id, $transfer->status === 'RECEIVED' ? $transfer->received_at : null];
                }
            }
            foreach ($jobs as [$component, $leg, $workAt]) {
                // A recorded allocation wins over a projection, including reversed/unverified postings.
                $recordedIndex = collect($rows)->search(fn ($row) => $row['component'] === $component && ($row['leg'] === $leg || $row['leg'] === null));
                if ($recordedIndex !== false) {
                    if ($rows[$recordedIndex]['leg'] === $leg) {
                        $rows[$recordedIndex]['work_recorded_at'] = $workAt;
                    }

                    continue;
                }
                $amount = $quote['components'][$component] ?? null;
                $configured = $shipment !== null && $approvedRates->has($quote['rate_version_id'] ?? $shipment->rate_version_id) && ($quote['model'] ?? 'legacy') !== 'legacy' && is_int($amount) && $amount >= 0;
                $status = $configured ? ($amount === 0 ? 'No paid component' : ($workAt ? 'Pending settlement' : 'Estimated')) : 'Unconfigured';
                $note = $configured ? ($amount === 0 ? 'The recorded schedule sets this component to PHP 0.00; no credit is due for it under this schedule.' : 'Recorded rate estimate; not wallet cash. Verified work, funding, hold and administrator settlement checks still apply.') : 'No approved compensation component is recorded. Ask logistics administration; no percentage is inferred.';
                if ($configured && $amount > 0 && $workAt && ($shipment->settled_at || $order->refundExecutions->isNotEmpty()
                    || in_array($order->dispute?->status, ['PENDING', 'UNDER_REVIEW', 'REFUND_APPROVED', 'REPLACEMENT_APPROVED'], true)
                    || in_array($order->status, ['CANCELLED', 'RETURNED_TO_SELLER'], true))) {
                    $status = 'Held';
                    $note = 'Financial review required. This component has no verified credit to your wallet; a refund/return/dispute or completed settlement may affect it.';
                }
                $rows[] = ['order_id' => $order->id, 'component' => $component, 'leg' => $leg, 'amount_cents' => $configured ? $amount : null,
                    'posted_cents' => 0, 'status' => $status, 'note' => $note, 'recorded_at' => null, 'work_recorded_at' => $workAt];
            }
            $result[$order->id] = ['rows' => $rows, 'settled_cents' => array_sum(array_column($rows, 'posted_cents'))];
        }

        return $result;
    }

    /** Verify credits in batches, without multiplying allocation rows through ledger joins. */
    public function postings(Collection $allocations, User $rider): array
    {
        $allocations = $allocations->filter(fn ($allocation) => (int) $allocation->beneficiary_id === $rider->id);
        if ($allocations->isEmpty()) {
            return [];
        }
        $transactions = LedgerTransaction::whereIn('id', $allocations->pluck('transaction_id'))->get()->keyBy('id');
        $reversed = LedgerTransaction::whereIn('reversal_of', $transactions->keys())->pluck('reversal_of')->flip();
        $credits = DB::table('ledger_entries')->join('financial_accounts', 'financial_accounts.id', '=', 'ledger_entries.account_id')
            ->whereIn('transaction_id', $transactions->keys())->where('financial_accounts.user_id', $rider->id)
            ->where('financial_accounts.key', 'user:'.$rider->id.':available')->where('financial_accounts.bucket', 'available')->where('delta_cents', '>', 0)
            ->selectRaw('transaction_id, SUM(delta_cents) AS credited')->groupBy('transaction_id')->pluck('credited', 'transaction_id');
        $allocated = DB::table('logistics_allocations')->whereIn('transaction_id', $transactions->keys())->where('beneficiary_id', $rider->id)
            ->selectRaw('transaction_id, SUM(amount_cents) AS allocated')->groupBy('transaction_id')->pluck('allocated', 'transaction_id');

        return $allocations->mapWithKeys(function ($allocation) use ($transactions, $reversed, $credits, $allocated): array {
            $transaction = $transactions->get($allocation->transaction_id);
            $verified = $transaction?->type === 'logistics_earnings'
                && (int) ($transaction->metadata['order_id'] ?? 0) === (int) $allocation->order_id
                && ($transaction->metadata['component'] ?? null) === $allocation->component
                && (int) ($credits[$allocation->transaction_id] ?? 0) === (int) $allocated[$allocation->transaction_id];
            $isReversed = $reversed->has($allocation->transaction_id);

            return [$allocation->id => ['order_id' => (int) $allocation->order_id, 'component' => $allocation->component,
                'leg' => $transaction?->metadata['leg'] ?? null, 'amount_cents' => (int) $allocation->amount_cents,
                'posted_cents' => $verified && ! $isReversed ? (int) $allocation->amount_cents : 0,
                'status' => $isReversed ? 'Reversed' : ($verified ? 'Settled' : 'Held'),
                'note' => $isReversed ? 'The original credit has a recorded reversal; net settled credit here is zero.' : ($verified ? 'Verified ledger credit. Your current wallet balance can differ after other transactions.' : 'Allocation recorded but its wallet posting could not be verified. Ask logistics administration.'),
                'recorded_at' => $transaction?->created_at, 'work_recorded_at' => null]];
        })->all();
    }
}
