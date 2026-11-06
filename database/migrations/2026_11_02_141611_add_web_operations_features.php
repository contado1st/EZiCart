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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_web_login_at')->nullable();
            $table->timestamp('last_web_activity_at')->nullable()->index();
            $table->string('shop_availability', 24)->default('open');
            $table->timestamp('shop_unavailable_from')->nullable();
            $table->timestamp('shop_returns_at')->nullable();
            $table->boolean('seller_promotions_enabled')->default(true);
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->unsignedInteger('recipient_limit')->nullable();
            $table->unsignedInteger('claimed_count')->default(0);
            $table->unsignedInteger('per_buyer_limit')->nullable();
        });
        Schema::table('product_variations', function (Blueprint $table) {
            $table->string('sku', 64)->nullable();
            $table->string('image_path')->nullable();
        });
        Schema::create('staff_profile_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('pending_user_id')->nullable()->unique();
            $table->json('previous_values');
            $table->json('proposed_values');
            $table->string('status', 16)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->timestamps();
            $table->unique(['voucher_id', 'buyer_id']);
        });
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_message_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('parcel_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('scan_event_id')->nullable()->constrained('scan_events')->restrictOnDelete();
            $table->foreignId('tracking_event_id')->nullable()->constrained('parcel_tracking_events')->restrictOnDelete();
            $table->string('event_key', 64);
            $table->string('digest', 64);
            $table->string('path');
            $table->string('status', 50);
            $table->string('location')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'event_key', 'digest'], 'parcel_evidence_event_file_unique');
        });
        Schema::create('seller_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 100);
            $table->text('message');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'created_at']);
        });
        Schema::create('promotion_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_promotion_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['seller_promotion_id', 'buyer_id']);
            $table->index(['buyer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['promotion_deliveries', 'seller_promotions', 'parcel_evidence', 'message_attachments', 'voucher_claims', 'staff_profile_revisions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('product_variations', fn (Blueprint $table) => $table->dropColumn(['sku', 'image_path']));
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn(['recipient_limit', 'claimed_count', 'per_buyer_limit']));
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_web_activity_at']);
            $table->dropColumn(['last_web_login_at', 'last_web_activity_at', 'shop_availability', 'shop_unavailable_from', 'shop_returns_at', 'seller_promotions_enabled']);
        });
    }
};
