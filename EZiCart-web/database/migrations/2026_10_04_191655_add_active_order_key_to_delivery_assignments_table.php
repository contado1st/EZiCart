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
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->foreignId('active_order_id')->nullable()->after('order_id')->constrained('orders')->restrictOnDelete();
        });

        foreach (DB::table('delivery_assignments')
            ->select('order_id')
            ->where('status', 'active')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('order_id') as $orderId) {
            $duplicateIds = DB::table('delivery_assignments')
                ->where('order_id', $orderId)
                ->where('status', 'active')
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->skip(1)
                ->pluck('id');

            DB::table('delivery_assignments')->whereIn('id', $duplicateIds)->update([
                'status' => 'reassigned',
                'released_at' => DB::raw('COALESCE(released_at, updated_at, assigned_at)'),
            ]);
        }

        DB::table('delivery_assignments')->where('status', 'active')->update(['active_order_id' => DB::raw('order_id')]);

        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->unique('active_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropUnique(['active_order_id']);
            $table->dropConstrainedForeignId('active_order_id');
        });
    }
};
