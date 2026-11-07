<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('postal_code', 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('postal_code');
        });
    }
};
