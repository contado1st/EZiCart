<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // e.g., Color, Size
            $table->string('value'); // e.g., Red, Blue, Small, XL
            $table->decimal('price_adjustment', 10, 2)->default(0.00); // Price difference from base product
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('variation_info')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('variation_info');
        });

        Schema::dropIfExists('product_variations');
    }
};