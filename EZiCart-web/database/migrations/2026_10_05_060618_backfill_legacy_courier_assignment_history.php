<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $legacyOrders = DB::table('orders')
            ->select(['id', 'courier_id', 'status', 'assigned_at', 'delivered_at', 'return_handed_to_seller_at', 'updated_at', 'created_at'])
            ->whereNotNull('courier_id')
            ->whereNull('delivery_courier_id')
            ->orderBy('id')
            ->get();

        foreach ($legacyOrders as $legacyOrder) {
            DB::transaction(function () use ($legacyOrder): void {
                $order = DB::table('orders')->where('id', $legacyOrder->id)->lockForUpdate()->first();

                if (
                    $order === null
                    || $order->courier_id === null
                    || $order->delivery_courier_id !== null
                    || DB::table('delivery_assignments')->where('order_id', $order->id)->exists()
                ) {
                    return;
                }

                $timestamp = $order->assigned_at ?? $order->created_at ?? now();
                $assignmentStatus = match ($order->status) {
                    'DELIVERED', 'COMPLETED' => 'completed',
                    'RETURNED_TO_SELLER' => 'returned',
                    'CANCELLED' => 'cancelled',
                    default => 'active',
                };
                $completedAt = $assignmentStatus === 'completed'
                    ? ($order->delivered_at ?? $order->updated_at ?? $timestamp)
                    : null;
                $releasedAt = match ($assignmentStatus) {
                    'active' => null,
                    'completed' => $completedAt,
                    'returned' => $order->return_handed_to_seller_at ?? $order->updated_at ?? $timestamp,
                    default => $order->updated_at ?? $timestamp,
                };

                DB::table('orders')->where('id', $order->id)->update([
                    'delivery_courier_id' => $order->courier_id,
                ]);
                DB::table('delivery_assignments')->insert([
                    'order_id' => $order->id,
                    'active_order_id' => $assignmentStatus === 'active' ? $order->id : null,
                    'rider_id' => $order->courier_id,
                    'assigned_by' => null,
                    'status' => $assignmentStatus,
                    'assigned_at' => $timestamp,
                    'released_at' => $releasedAt,
                    'completed_at' => $completedAt,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep migrated courier associations and assignment history intact.
    }
};
