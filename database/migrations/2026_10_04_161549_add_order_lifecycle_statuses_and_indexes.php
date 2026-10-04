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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('PLACED')->change();
            $table->timestamp('inventory_restored_at')->nullable();
            $table->index(['status', 'delivered_at']);
            $table->index(['delivery_courier_id', 'status']);
            $table->index(['status', 'updated_at']);
            $table->index('received_at');
            $table->index('sorted_at');
            $table->index('assigned_at');
            $table->index('failed_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('variation_id')->nullable()->after('product_id')->constrained('product_variations')->nullOnDelete();
        });

        DB::table('orders')->where('status', 'RETURNED')->update(['status' => 'RETURN_IN_TRANSIT']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'delivered_at']);
            $table->dropIndex(['delivery_courier_id', 'status']);
            $table->dropIndex(['status', 'updated_at']);
            $table->dropIndex(['received_at']);
            $table->dropIndex(['sorted_at']);
            $table->dropIndex(['assigned_at']);
            $table->dropIndex(['failed_at']);
            $table->dropColumn('inventory_restored_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variation_id');
        });
    }
};
