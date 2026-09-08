<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_path')->nullable()->after('plate_number');
            $table->string('permit_path')->nullable()->after('id_path'); // Business / DTI Permit
            $table->string('license_path')->nullable()->after('permit_path'); // Courier Driver's License
            $table->string('or_cr_path')->nullable()->after('license_path'); // Courier Vehicle OR/CR
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['id_path', 'permit_path', 'license_path', 'or_cr_path']);
        });
    }
};