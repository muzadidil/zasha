<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->ulid('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('rater_role', ['customer', 'partner']);
            $table->unsignedTinyInteger('stars');
            $table->string('comment', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'rater_id'], 'ratings_order_rater_unique');
            $table->index(['ratee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
