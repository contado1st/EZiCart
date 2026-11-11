<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\ParcelTransfer;
use App\Models\ShipmentFinancial;
use App\Models\ShipmentPriceAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LogisticsSettlementService
{
    public function __construct(private FinancialLedgerService $ledger) {}

    /** Invoked inside the approved seller-settlement transaction after the same hold/dispute checks. */
    public function settle(Order $order, User $actor): void
    {
        $shipment = ShipmentFinancial::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
        if ($shipment->settled_at) {
            return;
        }
        $quote = $shipment->quote_snapshot;
        $funding = $shipment->buyer_shipping_cents + $shipment->seller_shipping_cents + $shipment->platform_shipping_cents + $shipment->additional_funding_cents;
        $refunded = $order->refundExecutions()->where('status', 'EXECUTED')->exists();
        if ($refunded) {
            $funding = $shipment->additional_funding_cents;
        }
        $escrow = $this->ledger->escrow($order->id);
        $unallocated = $this->ledger->account('platform:unallocated-logistics', 'unallocated_shipping', null, 'held');
        if ($quote['model'] === 'legacy') {
            if ($funding > 0) {
                $this->ledger->transfer('shipping:'.$order->id.':unconfigured', 'shipping_awaiting_tariff', $escrow, $unallocated, $funding, $actor, ['order_id' => $order->id]);
            }
            $shipment->update(['settled_at' => now(), 'settlement_snapshot' => ['status' => 'UNCONFIGURED', 'funding_cents' => $funding, 'unallocated_cents' => $funding, 'note' => 'Legacy flat shipping has no approved compensation schedule. No rider/hub percentages were invented.']]);

            return;
        }
        $adjustment = DB::table('shipment_price_adjustments')->where('order_id', $order->id)->where('status', 'APPROVED')->latest('id')->first();
        if ($adjustment) {
            $quote = json_decode($adjustment->revised_quote, true, 32, JSON_THROW_ON_ERROR);
        }
        $components = $quote['components'];
        $claims = [];
        $add = function (string $component, ?int $beneficiary, int $amount, string $leg = '') use (&$claims): void {
            if ($amount > 0) {
                $claims[] = ['component' => $component, 'beneficiary_id' => $beneficiary, 'amount_cents' => $amount, 'leg' => $leg];
            }
        };
        $add('pickup', $order->picked_up_at ? $order->pickup_courier_id : null, $components['pickup']);
        $delivered = $order->deliveryAttempts()->where('outcome', 'delivered')->whereNotNull('proof_path')->oldest('id')->first();
        $add('last_mile', $delivered?->rider_id, $components['last_mile']);
        $add('return_to_sender', $order->return_handed_to_seller_at ? $order->delivery_courier_id : null, $components['return_to_sender'] ?? 0);
        $hubIds = ParcelTrackingEvent::where('order_id', $order->id)->where('status', 'AT_SORTING_CENTER')->orderBy('id')->pluck('actor_id')
            ->filter(fn ($id) => User::whereKey($id)->where('role', 'sorting_center')->exists())->unique()->values();
        $transfers = ParcelTransfer::where('order_id', $order->id)->where('status', 'RECEIVED')->orderBy('id')->get();
        if ($hubIds->isEmpty() && $transfers->isNotEmpty()) {
            $hubIds = collect([$transfers->first()->source_hub_id, $transfers->last()->destination_hub_id])->unique()->values();
        }
        $add('origin_hub', $hubIds->first(), $components['origin_hub']);
        $add('destination_hub', $hubIds->count() > 1 ? $hubIds->last() : null, $components['destination_hub']);
        if ($transfers->isEmpty()) {
            $add('linehaul', null, $components['linehaul']);
        } else {
            foreach ($transfers as $transfer) {
                $add('linehaul', $transfer->carrier_rider_id, $components['linehaul'], 'transfer-'.$transfer->id);
            }
        }
        $operations = $this->ledger->account('platform:logistics-operations', 'logistics_operations');
        foreach (['cod', 'remote', 'handling', 'insurance', 'overhead'] as $component) {
            $add($component, null, $components[$component]);
        }
        $cost = array_sum(array_column($claims, 'amount_cents'));
        abort_unless($cost <= $funding, 422, 'Actual approved logistics claims exceed shipping funding. Record an authorized treasury contribution before settlement.');
        $held = 0;
        foreach ($claims as $claim) {
            $verifiedWork = $claim['beneficiary_id'] !== null;
            $operatingCost = in_array($claim['component'], ['cod', 'remote', 'handling', 'insurance', 'overhead'], true);
            $destination = $verifiedWork ? $this->ledger->wallet(User::findOrFail($claim['beneficiary_id'])) : ($operatingCost ? $operations : $unallocated);
            if (! $verifiedWork && ! $operatingCost) {
                $held += $claim['amount_cents'];
            }
            $key = 'shipping:'.$order->id.':'.$claim['component'].':'.($claim['beneficiary_id'] ?? 'unassigned').':'.$claim['leg'];
            $transaction = $this->ledger->transfer($key, $verifiedWork ? 'logistics_earnings' : ($operatingCost ? 'logistics_operating_cost' : 'logistics_claim_held'), $escrow, $destination, $claim['amount_cents'], $actor, ['order_id' => $order->id, 'component' => $claim['component'], 'leg' => $claim['leg']]);
            DB::table('logistics_allocations')->insert(['order_id' => $order->id, 'allocation_key' => $key, 'component' => $claim['component'], 'beneficiary_id' => $claim['beneficiary_id'], 'amount_cents' => $claim['amount_cents'], 'transaction_id' => $transaction->id, 'created_at' => now()]);
        }
        $margin = $funding - $cost;
        if ($margin > 0) {
            $this->ledger->transfer('shipping:'.$order->id.':margin', $refunded ? 'unused_return_funding_released' : 'net_logistics_margin', $escrow, $refunded ? $this->ledger->treasury() : $this->ledger->account('platform:logistics-margin', 'logistics_margin'), $margin, $actor, ['order_id' => $order->id]);
        }
        $percentages = array_map(fn ($claim) => ['component' => $claim['component'], 'beneficiary_id' => $claim['beneficiary_id'], 'percent_of_funding' => $funding ? round($claim['amount_cents'] * 100 / $funding, 4) : 0], $claims);
        if ($margin > 0) {
            $percentages[] = ['component' => $refunded ? 'unused_return_funding_released' : 'net_logistics_margin', 'beneficiary_id' => null, 'percent_of_funding' => round($margin * 100 / $funding, 4)];
        }
        $shipment->update(['settled_at' => now(), 'settlement_snapshot' => ['status' => $held ? 'PARTIALLY_HELD' : 'SETTLED', 'funding_cents' => $funding, 'original_shipping_refunded' => $refunded, 'claims' => $claims, 'logistics_cost_cents' => $cost - $held, 'unallocated_cents' => $held, 'net_margin_cents' => $refunded ? 0 : $margin, 'unused_return_funding_released_cents' => $refunded ? $margin : 0, 'allocation_percentages' => $percentages]]);
    }

    public function settleReturned(Order $order, User $actor): void
    {
        abort_unless($actor->status === 'approved' && $actor->isLogisticsAdmin(), 403);
        DB::transaction(function () use ($order, $actor): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'RETURNED_TO_SELLER' && $locked->refundExecutions()->where('status', 'EXECUTED')->exists()
                && ShipmentPriceAdjustment::where('order_id', $locked->id)->where('status', 'APPROVED')->where('measurements->kind', 'return_cost')->exists(), 422, 'An executed refund and treasury-approved return-cost funding are required.');
            $this->settle($locked, $actor);
        });
    }
}
