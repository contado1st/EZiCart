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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Account System Fields
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['buyer', 'seller', 'courier', 'admin', 'logistics'])->default('buyer');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Personal Details
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_initial', 5)->nullable();
            $table->string('sex', 10);
            $table->string('contact_no', 20);
            $table->date('birthday');
            $table->integer('age');

            // Address Breakdown
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('street_address');

            // Verification & File Upload Paths
            $table->string('id_upload_path')->nullable();

            // Seller Specific Fields
            $table->string('business_name', 150)->nullable();
            $table->string('line_of_business')->nullable();
            $table->string('business_permit_path')->nullable();

            // Courier Specific Fields
            $table->string('vehicle_type')->nullable();
            $table->string('plate_number', 20)->nullable();
            $table->string('or_cr_upload_path')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};