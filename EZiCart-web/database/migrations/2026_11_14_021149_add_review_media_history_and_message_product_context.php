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
        Schema::table('reviews', function (Blueprint $table): void {
            $table->json('purchase_context')->nullable();
            $table->unsignedInteger('revision_version')->default(1);
        });
        Schema::create('review_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type', 40);
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('review_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['review_id', 'version']);
        });
        Schema::table('direct_messages', fn (Blueprint $table) => $table->json('product_context')->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('direct_messages', fn (Blueprint $table) => $table->dropColumn('product_context'));
        Schema::dropIfExists('review_revisions');
        Schema::dropIfExists('review_photos');
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn(['purchase_context', 'revision_version']));
    }
};
