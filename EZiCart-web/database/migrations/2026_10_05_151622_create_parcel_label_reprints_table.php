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
            $table->unsignedInteger('parcel_code_version')->default(1)->after('parcel_code');
        });

        Schema::create('parcel_label_reprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_parcel_code', 40);
            $table->string('replacement_parcel_code', 40)->unique();
            $table->unsignedInteger('previous_version');
            $table->unsignedInteger('replacement_version');
            $table->string('reason', 500);
            $table->index(['order_id', 'created_at']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_label_reprints');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('parcel_code_version');
        });
    }
};
