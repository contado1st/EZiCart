<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('phone_verified_at')->nullable();
        });

        Schema::table('email_verification_codes', function (Blueprint $table): void {
            $table->string('channel', 10)->default('email');
        });
    }

    public function down(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table): void {
            $table->dropColumn('channel');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone_verified_at');
        });
    }
};
