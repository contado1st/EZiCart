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
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 160)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->string('bucket', 20)->default('available');
            $table->string('currency', 3)->default('PHP');
            $table->bigInteger('balance_cents')->default(0);
            $table->boolean('allow_negative')->default(false);
            $table->boolean('frozen')->default(false);
            $table->timestamps();
        });
        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('idempotency_key', 160)->unique();
            $table->string('payload_hash', 64);
            $table->string('type', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversal_of')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->json('metadata');
            $table->timestamp('created_at');
        });
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->bigInteger('delta_cents');
            $table->bigInteger('balance_before_cents');
            $table->bigInteger('balance_after_cents');
            $table->timestamp('created_at');
            $table->unique(['transaction_id', 'account_id']);
        });
        Schema::create('wallet_funding_intents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('PHP');
            $table->string('provider')->default('sandbox');
            $table->string('status')->default('PENDING');
            $table->string('external_reference')->nullable()->unique();
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'funding_owner_key');
        });
        Schema::create('financial_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 120)->unique();
            $table->foreignId('intent_id')->constrained('wallet_funding_intents')->restrictOnDelete();
            $table->string('payload_hash', 64);
            $table->string('type', 30);
            $table->timestamp('created_at');
        });
        Schema::create('wallet_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('idempotency_key', 100);
            $table->text('destination');
            $table->string('status')->default('REQUESTED');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('external_reference')->nullable()->unique();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'withdrawal_owner_key');
        });
        Schema::create('voucher_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('source_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('reserve_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('maximum_cents');
            $table->unsignedBigInteger('cap_cents');
            $table->unsignedBigInteger('consumed_cents')->default(0);
            $table->unsignedBigInteger('released_cents')->default(0);
            $table->string('status')->default('RESERVED');
            $table->timestamps();
        });
        Schema::create('voucher_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('COMMITTED');
            $table->timestamps();
        });
        Schema::create('remittance_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('rider_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('hub_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('expected_cents');
            $table->unsignedBigInteger('submitted_cents');
            $table->unsignedBigInteger('received_cents')->nullable();
            $table->bigInteger('discrepancy_cents')->nullable();
            $table->string('status')->default('SUBMITTED')->index();
            $table->string('evidence_path');
            $table->string('receipt_path')->nullable();
            $table->text('reason');
            $table->timestamp('submitted_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('remittance_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('remittance_batches')->restrictOnDelete();
            $table->foreignId('order_finance_id')->unique()->constrained('order_finances')->restrictOnDelete();
            $table->unsignedBigInteger('expected_cents');
        });
        Schema::create('remittance_batch_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('remittance_batches')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->json('details');
            $table->timestamp('created_at');
        });
        Schema::create('delivery_rate_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('route_class', 40)->index();
            $table->string('status')->default('DRAFT');
            $table->boolean('sandbox')->default(true);
            $table->unsignedInteger('volumetric_divisor')->default(6000);
            $table->json('rules');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('effective_at');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('shipment_financials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('rate_version_id')->nullable()->constrained('delivery_rate_versions')->restrictOnDelete();
            $table->json('quote_snapshot');
            $table->unsignedBigInteger('buyer_shipping_cents');
            $table->unsignedBigInteger('seller_shipping_cents')->default(0);
            $table->unsignedBigInteger('platform_shipping_cents')->default(0);
            $table->unsignedBigInteger('additional_funding_cents')->default(0);
            $table->json('settlement_snapshot')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('shipment_price_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('measurements');
            $table->json('revised_quote');
            $table->bigInteger('difference_cents');
            $table->string('evidence_path');
            $table->text('reason');
            $table->string('status')->default('PROPOSED');
            $table->timestamps();
        });
        Schema::create('logistics_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('allocation_key', 160)->unique();
            $table->string('component', 40);
            $table->foreignId('beneficiary_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamp('created_at');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedTinyInteger('financial_version')->default(0);
            $table->unsignedBigInteger('wallet_paid_cents')->default(0);
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->unsignedBigInteger('seller_promo_funded_cents')->default(0);
            $table->unsignedBigInteger('platform_shipping_funded_cents')->default(0);
            $table->unsignedBigInteger('shipping_discount_cents')->default(0);
        });
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->string('discount_target', 20)->default('merchandise');
        });
        Schema::table('order_finances', function (Blueprint $table): void {
            $table->timestamp('ledger_funded_at')->nullable();
        });
        Schema::table('seller_payouts', function (Blueprint $table): void {
            $table->string('destination', 20)->default('external');
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['length_mm', 'width_mm', 'height_mm']));
        Schema::table('seller_payouts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropColumn('destination');
        });
        Schema::table('order_finances', fn (Blueprint $table) => $table->dropColumn('ledger_funded_at'));
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('discount_target'));
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('wallet_transaction_id');
            $table->dropColumn(['financial_version', 'wallet_paid_cents', 'seller_promo_funded_cents', 'platform_shipping_funded_cents', 'shipping_discount_cents']);
        });
        foreach (['logistics_allocations', 'shipment_price_adjustments', 'shipment_financials', 'delivery_rate_versions', 'remittance_batch_events', 'remittance_batch_items', 'remittance_batches', 'voucher_redemptions', 'voucher_budgets', 'wallet_withdrawals', 'financial_provider_events', 'wallet_funding_intents', 'ledger_entries', 'ledger_transactions', 'financial_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
