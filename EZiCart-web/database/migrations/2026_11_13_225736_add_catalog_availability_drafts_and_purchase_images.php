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
        // Inventory conflicts before any MySQL DDL (DDL is not transactionally reversible).
        $duplicates = DB::table('product_variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereNotNull('v.sku')->whereRaw("TRIM(v.sku) <> ''")->selectRaw('p.user_id, LOWER(TRIM(v.sku)) as normalized')
            ->groupBy('p.user_id')->groupByRaw('LOWER(TRIM(v.sku))')->havingRaw('COUNT(*) > 1');
        if (DB::query()->fromSub($duplicates, 'collisions')->exists()) {
            throw new RuntimeException('Resolve store SKU collisions before activating catalog changes. No SKUs were renamed.');
        }
        Schema::table('products', fn (Blueprint $table) => $table->boolean('is_seller_active')->default(true));
        Schema::table('order_items', fn (Blueprint $table) => $table->string('purchased_image_path')->nullable()->index());
        Schema::table('product_variations', function (Blueprint $table): void {
            $table->foreignId('seller_id')->nullable()->constrained('users');
        });
        DB::table('product_variations')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('product_variations')->where('id', $row->id)->update(['seller_id' => DB::table('products')->where('id', $row->product_id)->value('user_id')]);
            }
        });
        Schema::table('product_variations', function (Blueprint $table): void {
            $table->unsignedBigInteger('seller_id')->nullable(false)->change();
        });
        // Add the computed column after SQLite's table rebuild for the NOT NULL change.
        Schema::table('product_variations', function (Blueprint $table): void {
            $table->string('canonical_sku', 64)->nullable()->storedAs("NULLIF(LOWER(TRIM(sku)), '')");
            $table->unique(['seller_id', 'canonical_sku'], 'seller_sku_unique');
        });
        Schema::create('product_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->json('uploads');
            $table->foreignId('submitted_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_drafts');
        Schema::table('product_variations', function (Blueprint $table): void {
            $table->dropUnique('seller_sku_unique');
            $table->dropForeign(['seller_id']);
            $table->dropColumn(['canonical_sku', 'seller_id']);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('purchased_image_path'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_seller_active'));
    }
};
