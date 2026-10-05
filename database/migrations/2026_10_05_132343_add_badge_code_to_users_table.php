<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('badge_code', 64)->nullable()->unique()->after('status');
        });

        DB::table('users')->where('role', 'courier')->whereNull('badge_code')->orderBy('id')->eachById(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['badge_code' => Str::random(48)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['badge_code']);
            $table->dropColumn('badge_code');
        });
    }
};
