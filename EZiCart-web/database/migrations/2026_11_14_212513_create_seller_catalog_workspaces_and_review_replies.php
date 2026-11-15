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
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('store_categories')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('normalized_name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['seller_id', 'normalized_name']);
            $table->index(['seller_id', 'parent_id', 'sort_order']);
        });
        Schema::create('product_store_category', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_category_id')->constrained('store_categories')->cascadeOnDelete();
            $table->primary(['product_id', 'store_category_id']);
            $table->index(['store_category_id', 'product_id']);
        });
        Schema::create('seller_catalog_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('operation_key');
            $table->string('request_hash', 64);
            $table->json('result');
            $table->timestamps();
            $table->unique(['seller_id', 'operation_key']);
        });
        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('seller_catalog_operations');
        Schema::dropIfExists('product_store_category');
        Schema::dropIfExists('store_categories');
    }
};
