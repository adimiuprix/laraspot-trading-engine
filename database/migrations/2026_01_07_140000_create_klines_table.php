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
        Schema::create('klines', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index(); // BTC/USDT
            $table->string('interval', 10)->index(); // 1m, 5m, 1h, 1d
            $table->bigInteger('open_time')->index(); // Unix timestamp (milliseconds)
            $table->bigInteger('close_time'); // Unix timestamp (milliseconds)
            
            // OHLCV data (using decimal for precision)
            $table->decimal('open', 20, 8); // Opening price
            $table->decimal('high', 20, 8); // Highest price
            $table->decimal('low', 20, 8); // Lowest price
            $table->decimal('close', 20, 8); // Closing price
            $table->decimal('volume', 20, 8); // Volume in base currency (BTC)
            $table->decimal('quote_volume', 20, 8); // Volume in quote currency (USDT)
            
            // Additional metrics
            $table->integer('trades_count')->default(0); // Number of trades
            
            // Status
            $table->boolean('is_closed')->default(false); // Is interval completed
            
            $table->timestamps();
            
            // Composite unique index
            $table->unique(['symbol', 'interval', 'open_time'], 'klines_symbol_interval_time');
            
            // Index for querying
            $table->index(['symbol', 'interval', 'open_time'], 'klines_query_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('klines');
    }
};
