<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_messages', fn (Blueprint $table) => $table->timestamp('unsent_at')->nullable());
        Schema::table('message_attachments', fn (Blueprint $table) => $table->timestamp('unsent_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('message_attachments', fn (Blueprint $table) => $table->dropColumn('unsent_at'));
        Schema::table('direct_messages', fn (Blueprint $table) => $table->dropColumn('unsent_at'));
    }
};
