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
        Schema::table('products', function (Blueprint $table) {
            $table->string('compliance_status', 24)->nullable()->after('category');
            $table->text('compliance_note')->nullable()->after('compliance_status');
            $table->foreignId('compliance_reviewed_by')->nullable()->after('compliance_note')->constrained('users')->restrictOnDelete();
            $table->timestamp('compliance_reviewed_at')->nullable()->after('compliance_reviewed_by');
        });

        DB::table('products')->update(['compliance_status' => 'approved']);

        Schema::table('products', function (Blueprint $table) {
            $table->string('compliance_status', 24)->default('pending_review')->nullable(false)->change();
            $table->index(['compliance_status', 'created_at']);
        });

        Schema::create('product_compliance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->string('previous_status', 24)->nullable();
            $table->string('new_status', 24);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_compliance_events');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['compliance_status', 'created_at']);
            $table->dropConstrainedForeignId('compliance_reviewed_by');
            $table->dropColumn(['compliance_status', 'compliance_note', 'compliance_reviewed_at']);
        });
    }
};
