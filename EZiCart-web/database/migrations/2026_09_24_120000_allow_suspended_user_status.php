<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('status', 'suspended')->exists()) {
            throw new RuntimeException('Cannot restore the status enum while suspended users exist.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });
    }
};
