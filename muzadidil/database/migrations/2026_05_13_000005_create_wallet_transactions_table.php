<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('partner_wallets')->cascadeOnDelete();
            $table->enum('type', ['topup', 'fee', 'refund', 'adjustment']);
            // Signed amount: positive = credit, negative = debit.
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 32)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Intentionally no updated_at; this table is immutable audit trail.
            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
