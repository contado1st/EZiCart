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
        Schema::table('refund_executions', function (Blueprint $table) {
            $table->foreignId('dispute_id')->nullable()->change();
            $table->foreignId('rts_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->string('authorization_reason', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('refund_executions')->whereNull('dispute_id')->exists()) {
            throw new RuntimeException('Retain RTS refund authorization history; use compatible code or fix forward.');
        }
        Schema::table('refund_executions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rts_order_id');
            $table->dropColumn('authorization_reason');
            $table->foreignId('dispute_id')->nullable(false)->change();
        });
    }
};
