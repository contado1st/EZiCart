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
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('label', 120);
            $table->string('type', 20);
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('capacity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hub_id', 'code']);
            $table->index(['hub_id', 'type', 'is_active']);
        });

        Schema::table('scan_events', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('order_id')->constrained('storage_locations')->nullOnDelete();
        });

        Schema::create('parcel_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_order_id')->nullable()->unique();
            $table->foreignId('location_id')->constrained('storage_locations')->restrictOnDelete();
            $table->foreignId('placed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('placed_at');
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->string('removal_reason')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'removed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_placements');
        Schema::table('scan_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
        Schema::dropIfExists('storage_locations');
    }
};
