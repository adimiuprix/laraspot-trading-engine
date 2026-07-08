<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add columns for partial fill support
            $table->decimal('filled_amount', 20, 8)->default(0)->after('amount');
            $table->decimal('remaining_amount', 20, 8)->after('filled_amount');
            
            // Add index for matching performance
            $table->index(['symbol', 'side', 'status', 'price', 'created_at'], 'idx_orders_matching');
            $table->index(['status', 'remaining_amount'], 'idx_orders_remaining');
        });
        
        // Update status enum to include 'partially_filled'
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('open', 'partially_filled', 'filled', 'cancelled') NOT NULL DEFAULT 'open'");
        
        // Initialize remaining_amount for existing orders
        DB::statement('UPDATE orders SET remaining_amount = amount - filled_amount');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_matching');
            $table->dropIndex('idx_orders_remaining');
            $table->dropColumn(['filled_amount', 'remaining_amount']);
        });
    }
};
