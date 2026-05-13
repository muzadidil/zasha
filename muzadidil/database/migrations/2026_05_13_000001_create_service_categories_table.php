<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->unsignedInteger('min_price');
            $table->unsignedInteger('price_step');
            $table->unsignedTinyInteger('max_partners');
            $table->boolean('requires_geolocation')->default(true);
            $table->unsignedSmallInteger('search_radius_km')->nullable();
            $table->unsignedSmallInteger('search_timeout_minutes');
            $table->decimal('commission_percent', 5, 2)->default(5.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
