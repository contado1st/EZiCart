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
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('wallet_payment_due_at')->nullable();
            $table->index(['payment_method', 'status', 'wallet_payment_due_at'], 'orders_wallet_deadline');
        });
        Schema::table('wallet_funding_intents', function (Blueprint $table): void {
            $table->string('request_kind', 30)->nullable();
            $table->json('request_details')->nullable();
            $table->string('deposit_reference_hash', 64)->nullable()->unique();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->index(['provider', 'status', 'id'], 'funding_review_queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do not remove payment/review history after use; pause writes and fix forward.
        Schema::table('wallet_funding_intents', function (Blueprint $table): void {
            $table->dropIndex('funding_review_queue');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropUnique(['deposit_reference_hash']);
            $table->dropColumn(['request_kind', 'request_details', 'deposit_reference_hash', 'reviewed_at', 'review_reason']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_wallet_deadline');
            $table->dropColumn('wallet_payment_due_at');
        });
    }
};
