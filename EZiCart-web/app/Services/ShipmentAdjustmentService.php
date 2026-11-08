<?php

namespace App\Services;

use App\Models\DeliveryRateVersion;
use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Models\ShipmentFinancial;
use App\Models\ShipmentPriceAdjustment;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ShipmentAdjustmentService
{
    public function __construct(private FinancialLedgerService $ledger, private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public function propose(Order $order, User $hub, int $weight, array $dimensions, string $evidence, string $reason): ShipmentPriceAdjustment
    {
        return DB::transaction(function () use ($order, $hub, $weight, $dimensions, $evidence, $reason): ShipmentPriceAdjustment {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($hub->status === 'approved' && $hub->role === 'sorting_center' && $locked->sorting_center_id === $hub->id, 403);
            abort_unless(in_array($locked->status, ['AT_SORTING_CENTER', 'SORTED'], true) && strlen(trim($reason)) >= 5 && Storage::disk('private')->exists($evidence), 422);
            $shipment = ShipmentFinancial::where('order_id', $locked->id)->lockForUpdate()->firstOrFail();
            abort_unless($shipment->rate_version_id && ! $shipment->settled_at && ! ShipmentPriceAdjustment::where('order_id', $order->id)->where('measurements->kind', 'measurement')->whereIn('status', ['PROPOSED', 'APPROVED'])->exists(), 422, 'An original tariff and no pending/approved measurement adjustment are required.');
            $quote = app(DeliveryPricingService::class)->calculate(DeliveryRateVersion::findOrFail($shipment->rate_version_id), $weight, $dimensions, $order->payment_method, ['municipality' => $order->municipality]);
            $adjustment = ShipmentPriceAdjustment::create(['order_id' => $order->id, 'proposed_by' => $hub->id, 'measurements' => ['kind' => 'measurement', 'weight_grams' => $weight, 'dimensions_mm' => $dimensions], 'revised_quote' => $quote, 'difference_cents' => $quote['total_cents'] - $shipment->quote_snapshot['total_cents'], 'evidence_path' => $evidence, 'reason' => $reason]);
            $this->audits->record($hub, $adjustment, 'shipping_measurement_proposed', [], $adjustment->only(['measurements', 'difference_cents']), $reason);

            return $adjustment;
        });
    }

    public function proposeOperatingFunds(Order $order, User $actor, int $amount, string $evidence, string $reason, bool $returned = false): ShipmentPriceAdjustment
    {
        abort_unless($actor->status === 'approved' && $actor->isLogisticsAdmin(), 403);

        return DB::transaction(function () use ($order, $actor, $amount, $evidence, $reason, $returned): ShipmentPriceAdjustment {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $shipment = ShipmentFinancial::where('order_id', $locked->id)->lockForUpdate()->firstOrFail();
            abort_unless($shipment->rate_version_id && ! $shipment->settled_at && strlen(trim($reason)) >= 5 && Storage::disk('private')->exists($evidence), 422);
            $quote = ShipmentPriceAdjustment::where('order_id', $order->id)->where('status', 'APPROVED')->latest('id')->first()?->revised_quote ?? $shipment->quote_snapshot;
            if ($returned) {
                abort_unless($locked->status === 'RETURNED_TO_SELLER' && $locked->return_handed_to_seller_at !== null && $locked->refundExecutions()->where('status', 'EXECUTED')->exists(), 422, 'A completed return and executed buyer refund are required before return-cost funding.');
                abort_if(ShipmentPriceAdjustment::where('order_id', $locked->id)->where('measurements->kind', 'return_cost')->whereIn('status', ['PROPOSED', 'APPROVED'])->exists(), 422, 'Return cost funding is already recorded or awaiting review.');
                $quote['components']['return_to_sender'] = $quote['contingent_rts_cents'] ?? 0;
                $quote['components']['margin'] = 0;
                $legs = max(1, ParcelTransfer::where('order_id', $order->id)->where('status', 'RECEIVED')->count());
                $quote['total_cents'] = array_sum($quote['components']) + ($legs - 1) * $quote['components']['linehaul'];
                $amount = max(0, $quote['total_cents'] - $this->ledger->escrow($order->id)->balance_cents);
            } else {
                abort_unless($amount > 0 && $amount <= config('financial.maximum_transaction_cents'), 422);
            }
            $adjustment = ShipmentPriceAdjustment::create(['order_id' => $order->id, 'proposed_by' => $actor->id, 'measurements' => ['kind' => $returned ? 'return_cost' : 'operational_funding'], 'revised_quote' => $quote, 'difference_cents' => $amount, 'evidence_path' => $evidence, 'reason' => $reason]);
            $this->audits->record($actor, $adjustment, 'logistics_funding_proposed', [], ['amount_cents' => $amount, 'return_cost' => $returned], $reason);

            return $adjustment;
        });
    }

    public function approve(ShipmentPriceAdjustment $adjustment, User $actor, string $reason): void
    {
        abort_unless($actor->status === 'approved' && $actor->isCommerceAdmin() && strlen(trim($reason)) >= 5, 403);
        DB::transaction(function () use ($adjustment, $actor, $reason): void {
            $locked = ShipmentPriceAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            $shipment = ShipmentFinancial::where('order_id', $locked->order_id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'PROPOSED' && $locked->proposed_by !== $actor->id && ! $shipment->settled_at, 422);
            if ($locked->difference_cents > 0) {
                $this->ledger->transfer('shipping-adjustment:'.$locked->id, 'platform_shipping_subsidy', $this->ledger->treasury(), $this->ledger->escrow($locked->order_id), $locked->difference_cents, $actor, ['order_id' => $locked->order_id, 'adjustment_id' => $locked->id]);
                $shipment->increment('additional_funding_cents', $locked->difference_cents);
            }
            $locked->update(['status' => 'APPROVED', 'approved_by' => $actor->id]);
            $this->audits->record($actor, $locked, 'shipping_measurement_approved', [], ['status' => 'APPROVED', 'treasury_contribution_cents' => max(0, $locked->difference_cents)], $reason);
            $order = Order::findOrFail($locked->order_id);
            $this->notifications->send(collect([$order->buyer, $order->seller])->filter(), new OrderWorkflowNotification($order, 'shipping_measurement_verified', 'Hub parcel measurements were reviewed. Your original buyer payment remains unchanged; any approved extra funding is paid by the platform.'));
        });
    }
}
