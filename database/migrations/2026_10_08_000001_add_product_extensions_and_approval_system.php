<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Extend products table with status, rejection reason, and discount fields
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('is_archived')->index();
            $table->text('rejection_reason')->nullable()->after('status');
            $table->string('discount_type', 20)->nullable()->after('price'); // 'percent', 'fixed'
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
        });

        // Backfill existing products to 'approved' so existing seeded data remains accessible
        DB::table('products')->update(['status' => 'approved']);

        // 2. Create product_images table for multiple image uploads
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Populate product_images table from existing product images
        $existingProducts = DB::table('products')->whereNotNull('image_path')->get();
        foreach ($existingProducts as $p) {
            DB::table('product_images')->insert([
                'product_id' => $p->id,
                'image_path' => $p->image_path,
                'is_primary' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Extend product_variations table with image_path, sku, and price
        Schema::table('product_variations', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('value');
            $table->string('sku', 100)->nullable()->after('image_path');
            $table->decimal('price', 10, 2)->nullable()->after('price_adjustment');
        });

        // 4. Extend vouchers table to support product-specific vouchers, max discount, and start date
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('seller_id')->constrained()->cascadeOnDelete();
            $table->decimal('max_discount', 10, 2)->nullable()->after('value');
            $table->dateTime('start_date')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'max_discount', 'start_date']);
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'sku', 'price']);
        });

        Schema::dropIfExists('product_images');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['status', 'rejection_reason', 'discount_type', 'discount_value']);
        });
    }
};
