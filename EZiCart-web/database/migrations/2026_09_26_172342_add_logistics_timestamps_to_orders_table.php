<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('pickup_claimed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('delivery_failure_reason')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->decimal('cod_collected_amount', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'picked_up_at', 'pickup_claimed_at', 'received_at', 'sorted_at', 'assigned_at',
                'out_for_delivery_at', 'delivered_at', 'failed_at',
                'delivery_failure_reason', 'delivery_notes', 'cod_collected_amount',
            ]);
        });
    }
};
