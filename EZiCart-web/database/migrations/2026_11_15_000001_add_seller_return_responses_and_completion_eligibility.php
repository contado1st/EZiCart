<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_return_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->unique()->constrained()->restrictOnDelete();
            $table->string('stage', 30)->default('awaiting_seller');
            $table->timestamp('response_due_at');
            $table->string('seller_decision', 15)->nullable();
            $table->string('seller_reason', 300)->nullable();
            $table->timestamp('seller_responded_at')->nullable();
            $table->timestamp('escalation_due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['stage', 'response_due_at']);
            $table->index(['stage', 'escalation_due_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completion_enrolled_at')->nullable();
            $table->timestamp('auto_completion_due_at')->nullable()->index();
            $table->timestamp('auto_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['auto_completion_due_at']);
            $table->dropColumn(['completion_enrolled_at', 'auto_completion_due_at', 'auto_completed_at']);
        });
        Schema::dropIfExists('seller_return_responses');
    }
};
