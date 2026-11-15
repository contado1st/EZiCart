<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_retry_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('failed_attempt_id')->unique()->constrained('delivery_attempts')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('hub_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('proposed_for');
            $table->string('reason', 500);
            $table->string('status', 16)->default('PENDING');
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['hub_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_retry_requests');
    }
};
