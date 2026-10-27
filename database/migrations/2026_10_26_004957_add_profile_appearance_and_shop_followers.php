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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_display_name', 50)->nullable();
            $table->text('profile_bio')->nullable();
            $table->string('profile_avatar_path')->nullable();
            $table->string('profile_banner_path')->nullable();
            $table->string('profile_theme', 20)->default('rose');
        });
        Schema::create('shop_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->index()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['buyer_id', 'seller_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_follows');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_display_name', 'profile_bio', 'profile_avatar_path', 'profile_banner_path', 'profile_theme']);
        });
    }
};
