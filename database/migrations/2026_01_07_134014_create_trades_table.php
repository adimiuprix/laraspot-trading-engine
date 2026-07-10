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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('trade_time')->default(0)->nullable();
            $table->decimal('price', 20, 8)->default(0);
            $table->decimal('quantity', 20, 8)->default(0);
            $table->decimal('quote_amount', 20, 8)->default(0);    // price * quantity
            $table->decimal('commission', 20, 8)->default(0);
            $table->enum('maker_side', ['buy', 'sell']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
