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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol', 10);
            $table->enum('side', ['buy', 'sell']);
            $table->enum('type', ['limit', 'market'])->default('limit');
            $table->decimal('price', 20, 8);
            $table->decimal('quantity', 20, 8);
            $table->decimal('filled_quantity', 20, 8)->default(0);
            $table->decimal('remaining_quantity', 20, 8);
            $table->enum('status', ['open', 'partially_filled', 'filled', 'cancelled'])->default('open')->index();
            $table->timestamps();

            // Index to optimize queries for open orders by user and symbol
            $table->index(['user_id', 'symbol', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
