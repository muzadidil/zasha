<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained('service_categories');
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details');
            $table->unsignedInteger('current_price');
            $table->unsignedInteger('initial_price');
            $table->enum('status', [
                'draft',
                'searching',
                'claimed',
                'in_progress',
                'completed',
                'cancelled',
                'expired',
            ])->default('draft');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'service_category_id', 'current_price'], 'orders_status_category_price_idx');
            $table->index(['customer_id', 'status']);
            $table->index(['partner_id', 'status']);
            $table->index('expires_at');
        });

        // Spatial columns are nullable for WFH category (which has no physical location).
        // SPATIAL INDEX requires NOT NULL on MariaDB, so we skip the index here and rely on
        // the composite (status, service_category_id, current_price) index plus a runtime
        // ST_Distance filter — acceptable since the searching set is bounded by status/category.
        DB::statement('ALTER TABLE orders ADD COLUMN pickup_location POINT NULL AFTER service_category_id');
        DB::statement('ALTER TABLE orders ADD COLUMN destination_location POINT NULL AFTER pickup_location');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
