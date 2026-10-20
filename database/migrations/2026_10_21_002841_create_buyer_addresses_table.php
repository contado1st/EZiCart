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
        Schema::create('buyer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('recipient_name');
            $table->string('recipient_contact', 20);
            $table->string('region', 120)->nullable();
            $table->string('province', 120);
            $table->string('municipality', 120);
            $table->string('barangay', 120)->nullable();
            $table->string('house_number', 100)->nullable();
            $table->string('street_address')->nullable();
            $table->string('postal_code', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_addresses');
    }
};
