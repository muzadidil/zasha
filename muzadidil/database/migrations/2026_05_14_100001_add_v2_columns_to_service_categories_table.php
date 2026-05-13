<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->json('radius_steps')->nullable()->after('search_radius_km');
            $table->unsignedSmallInteger('step_duration_seconds')->default(15)->after('radius_steps');
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn(['radius_steps', 'step_duration_seconds']);
        });
    }
};
