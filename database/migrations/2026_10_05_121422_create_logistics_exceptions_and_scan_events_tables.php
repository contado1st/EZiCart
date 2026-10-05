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
        Schema::create('logistics_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 60);
            $table->text('reason');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('previous_rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('OPEN');
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('station', 40);
            $table->string('result', 30);
            $table->string('failure_reason')->nullable();
            $table->string('method', 20);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['station', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_events');
        Schema::dropIfExists('logistics_exceptions');
    }
};
