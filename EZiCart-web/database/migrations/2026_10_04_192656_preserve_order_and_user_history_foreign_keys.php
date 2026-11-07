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
        Schema::table('orders', function (Blueprint $table) {
            foreach (['buyer_id', 'seller_id', 'courier_id', 'pickup_courier_id', 'delivery_courier_id', 'sorting_center_id'] as $column) {
                $table->dropForeign([$column]);
            }

            foreach (['buyer_id', 'seller_id', 'courier_id', 'pickup_courier_id', 'delivery_courier_id', 'sorting_center_id'] as $column) {
                $table->foreign($column)->references('id')->on('users')->restrictOnDelete();
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });

        Schema::table('parcel_tracking_events', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['actor_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['buyer_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['buyer_id']);
            $table->dropForeign(['seller_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['buyer_id']);
            $table->dropForeign(['seller_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['buyer_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('parcel_tracking_events', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['actor_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['buyer_id', 'seller_id'] as $column) {
                $table->dropForeign([$column]);
                $table->foreign($column)->references('id')->on('users')->cascadeOnDelete();
            }

            foreach (['courier_id', 'pickup_courier_id', 'delivery_courier_id', 'sorting_center_id'] as $column) {
                $table->dropForeign([$column]);
                $table->foreign($column)->references('id')->on('users')->nullOnDelete();
            }
        });
    }
};
