<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // KTP number stored encrypted via Eloquent cast; column kept TEXT-sized for ciphertext.
            $table->text('ktp_number');
            $table->string('ktp_number_hash', 64)->unique();
            $table->string('ktp_photo_url', 500);
            $table->json('vehicle_info')->nullable();
            $table->json('skills')->nullable();
            $table->json('service_categories');
            $table->string('bank_name', 64)->nullable();
            // Bank account number stored encrypted (TEXT) per security checklist.
            $table->text('bank_account')->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
