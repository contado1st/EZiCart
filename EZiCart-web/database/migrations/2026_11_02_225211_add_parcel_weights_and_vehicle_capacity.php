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
        Schema::table('products', fn (Blueprint $table) => $table->unsignedBigInteger('weight_grams')->nullable());
        Schema::table('order_items', fn (Blueprint $table) => $table->unsignedBigInteger('unit_weight_grams')->nullable());
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('parcel_weight_grams')->nullable();
            $table->foreignId('weight_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('weight_recorded_at')->nullable();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('vehicle_capacity_grams')->nullable();
            $table->foreignId('vehicle_capacity_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vehicle_capacity_verified_at')->nullable();
        });
        Schema::table('parcel_transfers', fn (Blueprint $table) => $table->foreignId('carrier_rider_id')->nullable()->constrained('users')->nullOnDelete());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcel_transfers', fn (Blueprint $table) => $table->dropConstrainedForeignId('carrier_rider_id'));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_capacity_verified_by');
            $table->dropColumn(['vehicle_capacity_grams', 'vehicle_capacity_verified_at']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('weight_recorded_by');
            $table->dropColumn(['parcel_weight_grams', 'weight_recorded_at']);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('unit_weight_grams'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('weight_grams'));
    }
};
