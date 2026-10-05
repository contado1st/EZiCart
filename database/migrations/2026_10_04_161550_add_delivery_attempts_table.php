<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('outcome', 32);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'attempt_no']);
            $table->index(['rider_id', 'attempted_at']);
        });

        DB::table('orders')
            ->whereNotNull('delivery_courier_id')
            ->where(function ($query): void {
                $query->whereNotNull('failed_at')->orWhereNotNull('delivered_at');
            })
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    $attempts = [];
                    $attemptNo = 1;

                    if ($order->failed_at !== null) {
                        $attempts[] = [
                            'order_id' => $order->id,
                            'rider_id' => $order->delivery_courier_id,
                            'attempt_no' => $attemptNo++,
                            'outcome' => 'failed',
                            'reason' => $order->delivery_failure_reason,
                            'notes' => $order->delivery_notes,
                            'attempted_at' => $order->failed_at,
                            'created_at' => $order->failed_at,
                            'updated_at' => $order->failed_at,
                        ];
                    }

                    if ($order->delivered_at !== null) {
                        $attempts[] = [
                            'order_id' => $order->id,
                            'rider_id' => $order->delivery_courier_id,
                            'attempt_no' => $attemptNo,
                            'outcome' => 'delivered',
                            'reason' => null,
                            'notes' => $order->delivery_notes,
                            'attempted_at' => $order->delivered_at,
                            'created_at' => $order->delivered_at,
                            'updated_at' => $order->delivered_at,
                        ];
                    }

                    if ($attempts !== []) {
                        DB::table('delivery_attempts')->insert($attempts);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
    }
};
