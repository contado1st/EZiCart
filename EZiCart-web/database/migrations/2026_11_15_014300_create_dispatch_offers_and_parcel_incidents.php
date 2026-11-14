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
        Schema::create('dispatch_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 10, 6);
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->string('source', 30);
            $table->string('address_hash', 64)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
        Schema::create('dispatch_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('hub_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key')->unique();
            $table->string('kind', 15);
            $table->string('status', 20);
            $table->decimal('pickup_latitude', 9, 6)->nullable();
            $table->decimal('pickup_longitude', 10, 6)->nullable();
            $table->string('pickup_address_hash', 64)->nullable();
            $table->string('outcome_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['hub_id', 'status']);
        });
        Schema::create('dispatch_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_cycle_id')->nullable()->unique()->constrained('dispatch_cycles')->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->unsignedBigInteger('weight_grams');
            $table->unsignedBigInteger('distance_meters');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['dispatch_cycle_id', 'rider_id']);
            $table->index(['rider_id', 'status', 'expires_at']);
        });
        Schema::create('parcel_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('open_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('custodian_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key')->unique();
            $table->string('kind', 15);
            $table->string('status', 20)->default('OPEN');
            $table->string('reason', 1000);
            $table->json('custody_snapshot');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_sha256', 64)->nullable();
            $table->string('claim_status', 40)->default('REVIEW_REQUIRED');
            $table->text('claim_notes')->nullable();
            $table->foreignId('claim_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('claim_reviewed_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('resolution_reason', 1000)->nullable();
            $table->string('resolution_evidence_path')->nullable();
            $table->string('resolution_evidence_sha256', 64)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_incidents');
        Schema::dropIfExists('dispatch_offers');
        Schema::dropIfExists('dispatch_cycles');
        Schema::dropIfExists('dispatch_locations');
    }
};
