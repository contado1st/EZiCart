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
            $table->timestamp('pickup_requested_at')->nullable();
            $table->timestamp('pickup_scheduled_for')->nullable();
            $table->string('pickup_window', 80)->nullable();
            $table->text('pickup_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pickup_requested_at', 'pickup_scheduled_for', 'pickup_window', 'pickup_notes']);
        });
    }
};
