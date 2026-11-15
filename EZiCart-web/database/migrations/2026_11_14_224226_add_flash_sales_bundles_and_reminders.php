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
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->uuid('operation_key');
            $table->string('request_hash', 64);
            $table->unsignedInteger('unit_cents');
            $table->unsignedInteger('quantity_limit');
            $table->unsignedInteger('allocated_quantity')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['seller_id', 'operation_key']);
            $table->index(['product_id', 'starts_at', 'ends_at']);
        });
        Schema::create('flash_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['flash_sale_id', 'order_id']);
        });
        Schema::create('flash_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['flash_sale_id', 'buyer_id']);
        });
        Schema::create('bundle_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 12);
            $table->unsignedInteger('threshold');
            $table->unsignedInteger('percent')->nullable();
            $table->foreignId('gift_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('gift_quantity')->default(1);
            $table->uuid('operation_key');
            $table->string('request_hash', 64);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['seller_id', 'operation_key']);
        });
        Schema::create('bundle_products', function (Blueprint $table) {
            $table->foreignId('bundle_deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->primary(['bundle_deal_id', 'product_id']);
            $table->index(['product_id', 'bundle_deal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_products');
        Schema::dropIfExists('bundle_deals');
        Schema::dropIfExists('flash_reminders');
        Schema::dropIfExists('flash_reservations');
        Schema::dropIfExists('flash_sales');
    }
};
