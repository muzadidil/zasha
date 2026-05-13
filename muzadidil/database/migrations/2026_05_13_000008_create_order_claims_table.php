<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_claims', function (Blueprint $table) {
            $table->id();
            $table->ulid('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('agreed_price');
            $table->unsignedInteger('fee_amount');
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->timestamp('claimed_at')->useCurrent();
            $table->index(['order_id', 'status']);
            $table->index(['partner_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_claims');
    }
};
