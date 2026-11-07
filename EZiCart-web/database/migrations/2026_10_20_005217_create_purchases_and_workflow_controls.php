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
        if (! Schema::hasTable('purchases')) {
            Schema::create('purchases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
                $table->uuid('checkout_token');
                $table->string('request_hash', 64);
                $table->unsignedBigInteger('total_cents');
                $table->timestamps();
                $table->unique(['buyer_id', 'checkout_token']);
            });
        }
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'purchase_id')) {
                $table->foreignId('purchase_id')->nullable()->constrained()->restrictOnDelete();
            }
            if (! Schema::hasColumn('orders', 'seller_response_due_at')) {
                $table->timestamp('seller_response_due_at')->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'preparation_due_at')) {
                $table->timestamp('preparation_due_at')->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'seller_reminded_at')) {
                $table->timestamp('seller_reminded_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'restock_inspected_at')) {
                $table->timestamp('restock_inspected_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'restock_approved')) {
                $table->boolean('restock_approved')->nullable();
            }
            if (! Schema::hasColumn('orders', 'restock_inspected_by')) {
                $table->foreignId('restock_inspected_by')->nullable()->constrained('users')->restrictOnDelete();
            }
        });
        Schema::table('product_variations', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_variations', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->unsignedBigInteger('seller_id')->nullable()->change();
            if (! Schema::hasColumn('vouchers', 'max_discount')) {
                $table->decimal('max_discount', 10, 2)->nullable();
            }
        });
        if (! Schema::hasTable('administrative_audits')) {
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
