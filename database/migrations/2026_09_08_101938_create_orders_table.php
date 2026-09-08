<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            
            // Delivery Details
            $table->string('recipient_name');
            $table->string('recipient_contact');
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('street_address');

            // Financials
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(50.00);
            $table->decimal('commission_fee', 10, 2)->default(0.00); // 10% Platform fee
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method'); // COD, GCash, Bank Transfer

            // ERP Status Flow
            $table->enum('status', [
                'PLACED',
                'CONFIRMED',
                'PREPARING',
                'READY_FOR_PICKUP',
                'PICKED_UP',
                'AT_SORTING_CENTER',
                'SORTED',
                'ASSIGNED_TO_RIDER',
                'OUT_FOR_DELIVERY',
                'DELIVERED',
                'COMPLETED',
                'DELIVERY_FAILED',
                'RETURNED'
            ])->default('PLACED');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};