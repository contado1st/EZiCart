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
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('operation_key');
            $table->string('request_hash', 64);
            $table->string('kind', 12);
            $table->unsignedInteger('value');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['seller_id', 'operation_key']);
            $table->index(['product_id', 'starts_at', 'ends_at']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('purchase_limit')->nullable());
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('visibility', 12)->default('public');
            $table->boolean('product_scoped')->default(false);
            $table->unsignedTinyInteger('terms_version')->default(1);
        });
        Schema::create('voucher_products', function (Blueprint $table) {
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->primary(['voucher_id', 'product_id']);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->json('pricing_snapshot')->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('pricing_snapshot'));
        Schema::dropIfExists('voucher_products');
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn(['visibility', 'product_scoped', 'terms_version']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('purchase_limit'));
        Schema::dropIfExists('product_sales');
    }
};
