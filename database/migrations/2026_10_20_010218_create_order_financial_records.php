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
        Schema::create('order_finances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('due_cents');
            $table->unsignedBigInteger('commission_cents');
            $table->unsignedBigInteger('seller_payable_cents');
            $table->unsignedBigInteger('platform_subsidy_cents')->default(0);
            $table->unsignedBigInteger('funded_cents')->default(0);
            $table->string('funding_reference')->nullable()->unique();
            $table->string('funding_evidence_path')->nullable();
            $table->foreignId('funded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('funded_at')->nullable();
            $table->unsignedBigInteger('collected_cents')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->string('remittance_status')->default('NOT_COLLECTED')->index();
            $table->unsignedBigInteger('remitted_cents')->nullable();
            $table->string('remittance_reference')->nullable()->unique();
            $table->string('receipt_path')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('refund_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('dispute_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('AUTHORIZED');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference')->nullable()->unique();
            $table->string('evidence_path')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('REQUESTED');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference')->nullable()->unique();
            $table->string('evidence_path')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('platform_discount_amount', 10, 2)->default(0);
            $table->foreignId('replacement_for_order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('replacement_dispute_id')->nullable()->unique()->constrained('disputes')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('platform_discount_amount');
            $table->dropConstrainedForeignId('replacement_for_order_id');
            $table->dropConstrainedForeignId('replacement_dispute_id');
        });
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('refund_executions');
        Schema::dropIfExists('order_finances');
    }
};
