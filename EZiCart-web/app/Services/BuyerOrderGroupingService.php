<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BuyerOrderGroupingService
{
    public const LABELS = ['all' => 'All orders', 'to_pay' => 'To Pay', 'to_ship' => 'Preparing to ship', 'to_receive' => 'To receive',
        'completed' => 'Completed', 'cancelled' => 'Cancelled', 'returns' => 'Returns & refunds', 'other' => 'Other history'];

    public function counts(Builder $orders): array
    {
        // Project the correlated CASE first; strict MySQL cannot aggregate it directly.
        $projected = (clone $orders)->selectRaw($this->expression().' AS purchase_group');
        $totals = DB::query()->fromSub($projected, 'buyer_order_groups')->selectRaw('purchase_group, COUNT(*) AS aggregate')
            ->groupBy('purchase_group')->pluck('aggregate', 'purchase_group');
        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($totals as $group => $count) {
            $counts[$group] = (int) $count;
        }
        $counts['all'] = (int) $totals->sum();

        return $counts;
    }

    /** One primary group per order. Cancelled history takes precedence over stale dispute/refund records. */
    public function expression(): string
    {
        return "CASE WHEN orders.status = 'CANCELLED' THEN 'cancelled'
            WHEN orders.status IN ('RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER', 'RETURNED')
                OR EXISTS (SELECT 1 FROM refund_executions r WHERE r.order_id = orders.id)
                OR EXISTS (SELECT 1 FROM disputes d WHERE d.order_id = orders.id AND d.status IN ('PENDING', 'UNDER_REVIEW', 'REFUND_APPROVED', 'REPLACEMENT_APPROVED')) THEN 'returns'
            WHEN orders.status = 'PLACED' AND orders.payment_method = 'WALLET' AND orders.wallet_payment_due_at IS NOT NULL AND orders.wallet_transaction_id IS NULL THEN 'to_pay'
            WHEN orders.status IN ('PLACED', 'CONFIRMED', 'PREPARING') THEN 'to_ship'
            WHEN orders.status IN ('READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED') THEN 'to_receive'
            WHEN orders.status = 'COMPLETED' THEN 'completed' ELSE 'other' END";
    }
}
