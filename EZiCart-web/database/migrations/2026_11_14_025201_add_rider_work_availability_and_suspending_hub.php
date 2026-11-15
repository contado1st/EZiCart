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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('work_availability', 16)->default('unknown');
            $table->timestamp('work_availability_changed_at')->nullable();
            $table->unsignedInteger('work_availability_version')->default(0);
            $table->foreignId('suspending_hub_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspending_hub_id');
            $table->dropColumn(['work_availability', 'work_availability_changed_at', 'work_availability_version']);
        });
    }
};
