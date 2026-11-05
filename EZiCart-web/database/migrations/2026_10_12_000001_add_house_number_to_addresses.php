<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('house_number', 100)->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('region')->nullable();
            $table->string('house_number', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['region', 'house_number']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('house_number');
        });
    }
};
