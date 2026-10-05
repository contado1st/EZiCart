<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['rider_id', 'status']);
        });

        foreach (DB::table('orders')->whereNotNull('delivery_courier_id')->get() as $order) {
            $status = match ($order->status) {
                'DELIVERED', 'COMPLETED' => 'completed',
                'RETURNED_TO_SELLER' => 'returned',
                default => 'active',
            };
            $timestamp = $order->assigned_at ?? $order->created_at ?? now();
            DB::table('delivery_assignments')->insert([
                'order_id' => $order->id,
                'rider_id' => $order->delivery_courier_id,
                'status' => $status,
                'assigned_at' => $timestamp,
                'released_at' => $status === 'active' ? null : $timestamp,
                'completed_at' => $status === 'completed' ? ($order->delivered_at ?? $timestamp) : null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_assignments');
    }
};
