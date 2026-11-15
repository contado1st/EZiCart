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
        Schema::create('parcel_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('open_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('hub_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->json('snapshot_before');
            $table->unsignedBigInteger('baseline_event_id')->nullable();
            $table->string('reason', 500);
            $table->string('evidence_path');
            $table->char('evidence_sha256', 64);
            $table->string('status', 16)->default('OPEN');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision_reason', 1000)->nullable();
            $table->foreignId('tracking_event_id')->nullable()->constrained('parcel_tracking_events')->restrictOnDelete();
            $table->json('snapshot_after')->nullable();
            $table->timestamps();
            $table->index(['hub_id', 'status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('parcel_corrections')->exists()) {
            throw new RuntimeException('Retain correction requests, evidence and review history; use compatible code or fix forward.');
        }
        Schema::dropIfExists('parcel_corrections');
    }
};
