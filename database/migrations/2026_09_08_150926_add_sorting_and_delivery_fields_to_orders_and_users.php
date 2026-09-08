<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('pickup_courier_id')->nullable()->after('courier_id')->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_courier_id')->nullable()->after('pickup_courier_id')->constrained('users')->nullOnDelete();
            $table->string('delivery_area')->nullable()->after('street_address'); // e.g., "Area A - Santa Cruz"
            $table->foreignId('sorting_center_id')->nullable()->after('delivery_courier_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('assigned_area')->nullable()->after('line_of_business'); // For couriers assigned to specific zones
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pickup_courier_id']);
            $table->dropForeign(['delivery_courier_id']);
            $table->dropForeign(['sorting_center_id']);
            $table->dropColumn(['pickup_courier_id', 'delivery_courier_id', 'delivery_area', 'sorting_center_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['assigned_area']);
        });
    }
};