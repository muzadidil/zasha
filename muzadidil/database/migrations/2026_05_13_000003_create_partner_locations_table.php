<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('accuracy_meters')->nullable();
            $table->boolean('is_online')->default(false)->index();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();
        });

        // POINT column for partner GPS coordinates. NOT NULL is required for SPATIAL INDEX on MariaDB.
        // SRID is enforced at insert time via ST_GeomFromText('POINT(lng lat)', 4326).
        DB::statement('ALTER TABLE partner_locations ADD COLUMN coordinates POINT NOT NULL AFTER user_id');
        DB::statement('ALTER TABLE partner_locations ADD SPATIAL INDEX partner_locations_coordinates_spatial (coordinates)');
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_locations');
    }
};
