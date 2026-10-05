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
        $duplicateOrderIds = DB::table('disputes')
            ->select('order_id')
            ->selectRaw('COUNT(*) AS dispute_count')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('order_id')
            ->pluck('order_id');

        if ($duplicateOrderIds->isNotEmpty()) {
            throw new RuntimeException('Cannot add one-dispute-per-order constraint until duplicate dispute records are reviewed for order IDs: '.implode(', ', $duplicateOrderIds->all()));
        }

        Schema::table('disputes', function (Blueprint $table) {
            $table->unique('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });
    }
};
