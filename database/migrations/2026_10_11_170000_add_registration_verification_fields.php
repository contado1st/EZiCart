<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('canonical_email')->nullable()->unique();
            $table->string('canonical_mobile', 12)->nullable()->unique();
            $table->string('canonical_plate', 20)->nullable()->unique();
            $table->string('shop_name', 30)->nullable()->unique();
            $table->string('canonical_company_name', 150)->nullable()->unique();
            $table->string('seller_type', 32)->nullable();
            $table->string('tin', 15)->nullable()->unique();
            $table->string('postal_code', 4)->nullable();
            $table->string('region')->nullable();
            $table->string('sec_registration_no', 11)->nullable()->unique();
            $table->string('contact_position')->nullable();
            $table->json('service_areas')->nullable();
            $table->string('sec_certificate_path')->nullable();
            $table->string('bir_certificate_path')->nullable();
            $table->string('representative_id_path')->nullable();
            $table->string('license_number', 16)->nullable()->unique();
            $table->date('license_expires_at')->nullable();
            $table->foreignId('logistics_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->string('privacy_version', 32)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('sex', 10)->nullable()->change();
            $table->integer('age')->nullable()->change();
            $table->date('birthday')->nullable()->change();
            $table->string('province')->nullable()->change();
            $table->string('municipality')->nullable()->change();
            $table->string('barangay')->nullable()->change();
            $table->string('street_address')->nullable()->change();
        });

        Schema::create('email_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('logistics_owner_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropUnique(['canonical_mobile']);
            $table->dropUnique(['canonical_email']);
            $table->dropUnique(['canonical_plate']);
            $table->dropUnique(['shop_name']);
            $table->dropUnique(['canonical_company_name']);
            $table->dropUnique(['tin']);
            $table->dropUnique(['sec_registration_no']);
            $table->dropUnique(['license_number']);
            $table->dropColumn([
                'canonical_email', 'canonical_mobile', 'canonical_plate', 'shop_name', 'canonical_company_name', 'seller_type', 'tin', 'postal_code', 'region',
                'sec_registration_no', 'contact_position', 'service_areas', 'sec_certificate_path',
                'bir_certificate_path', 'representative_id_path', 'license_number', 'license_expires_at',
                'terms_accepted_at', 'terms_version', 'privacy_accepted_at', 'privacy_version',
                'approved_at', 'rejection_reason', 'email_verified_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('sex', 10)->nullable(false)->change();
            $table->integer('age')->nullable(false)->change();
            $table->date('birthday')->nullable(false)->change();
            $table->string('province')->nullable(false)->change();
            $table->string('municipality')->nullable(false)->change();
            $table->string('barangay')->nullable(false)->change();
            $table->string('street_address')->nullable(false)->change();
        });
    }
};
