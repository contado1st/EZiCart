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
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->uuid('checkout_token');
            $table->string('request_hash', 64);
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
            $table->unique(['buyer_id', 'checkout_token']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('seller_response_due_at')->nullable()->index();
            $table->timestamp('preparation_due_at')->nullable()->index();
            $table->timestamp('seller_reminded_at')->nullable();
            $table->timestamp('restock_inspected_at')->nullable();
            $table->boolean('restock_approved')->nullable();
            $table->foreignId('restock_inspected_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('product_variations', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->unsignedBigInteger('seller_id')->nullable()->change();
            $table->decimal('max_discount', 10, 2)->nullable();
        });
        Schema::create('administrative_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('action');
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason');
            $table->timestamp('created_at');
            $table->index(['entity_type', 'entity_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrative_audits');
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('max_discount'));
        // Keep seller_id nullable: platform vouchers cannot be converted safely.
        Schema::table('product_variations', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_id');
            $table->dropConstrainedForeignId('restock_inspected_by');
            $table->dropColumn(['seller_response_due_at', 'preparation_due_at', 'seller_reminded_at', 'restock_inspected_at', 'restock_approved']);
        });
        Schema::dropIfExists('purchases');
    }
};
