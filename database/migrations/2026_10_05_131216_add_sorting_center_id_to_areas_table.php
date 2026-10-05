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
        Schema::table('areas', function (Blueprint $table): void {
            $table->foreignId('sorting_center_id')->nullable()->after('code')->constrained('users')->nullOnDelete();
            $table->index(['sorting_center_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table): void {
            $table->dropIndex(['sorting_center_id', 'is_active']);
            $table->dropConstrainedForeignId('sorting_center_id');
        });
    }
};
