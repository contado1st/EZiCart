<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert the column to VARCHAR(50) to support all platform roles without enum constraint errors
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'buyer'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('buyer', 'seller', 'courier', 'admin') NOT NULL DEFAULT 'buyer'");
    }
};