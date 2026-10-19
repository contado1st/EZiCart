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
        Schema::create('parcel_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('source_hub_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('destination_hub_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('PLANNED')->index();
            $table->string('transport_reference')->nullable();
            $table->text('reason');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
        Schema::create('operational_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('stage');
            $table->timestamp('observed_at');
            $table->timestamp('created_at');
            $table->unique(['order_id', 'stage', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
        Schema::dropIfExists('parcel_transfers');
    }
};
