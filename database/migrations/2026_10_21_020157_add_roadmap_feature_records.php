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
        Schema::create('product_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
        Schema::create('stock_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('last_state', 20);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
        Schema::create('order_cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('PENDING');
            $table->string('reason', 500);
            $table->string('response', 500)->nullable();
            $table->timestamp('respond_by');
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'respond_by']);
        });
        Schema::create('barangay_rider_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_municipality_id')->constrained()->restrictOnDelete();
            $table->string('barangay', 120);
            $table->string('barangay_normalized', 120);
            $table->foreignId('primary_rider_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('backup_rider_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['area_municipality_id', 'barangay_normalized'], 'barangay_rider_coverage_unique');
        });
        Schema::create('commerce_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('value');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('vehicle_category', 20)->nullable();
            $table->string('vehicle_description', 120)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vehicle_category', 'vehicle_description']);
        });
        Schema::dropIfExists('commerce_settings');
        Schema::dropIfExists('barangay_rider_assignments');
        Schema::dropIfExists('order_cancellation_requests');
        Schema::dropIfExists('stock_subscriptions');
        Schema::dropIfExists('product_interactions');
    }
};
