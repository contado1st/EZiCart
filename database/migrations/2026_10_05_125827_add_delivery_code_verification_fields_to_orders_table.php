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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_code_hash')->nullable()->after('delivery_notes');
            $table->text('delivery_code_encrypted')->nullable()->after('delivery_code_hash');
            $table->timestamp('delivery_code_expires_at')->nullable()->after('delivery_code_encrypted');
            $table->unsignedTinyInteger('delivery_code_attempts')->default(0)->after('delivery_code_expires_at');
            $table->timestamp('delivery_code_used_at')->nullable()->after('delivery_code_attempts');
            $table->index(['delivery_code_expires_at', 'delivery_code_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_code_expires_at', 'delivery_code_used_at']);
            $table->dropColumn([
                'delivery_code_hash',
                'delivery_code_encrypted',
                'delivery_code_expires_at',
                'delivery_code_attempts',
                'delivery_code_used_at',
            ]);
        });
    }
};
