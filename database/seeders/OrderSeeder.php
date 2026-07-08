<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample orders for testing partial fill

        // User 1 (Alice) - BUY orders
        Order::create([
            'user_id' => 1,
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'price' => '51000.00000000',
            'amount' => '5.00000000',
            'filled_amount' => '0.00000000',
            'remaining_amount' => '5.00000000',
            'status' => 'open',
        ]);

        Order::create([
            'user_id' => 1,
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '2.00000000',
            'filled_amount' => '0.00000000',
            'remaining_amount' => '2.00000000',
            'status' => 'open',
        ]);

        // User 2 (Bob) - SELL orders
        Order::create([
            'user_id' => 2,
            'symbol' => 'BTCUSDT',
            'side' => 'sell',
            'price' => '52000.00000000',
            'amount' => '3.00000000',
            'filled_amount' => '0.00000000',
            'remaining_amount' => '3.00000000',
            'status' => 'open',
        ]);

        $this->command->info('Orders seeded successfully!');
        $this->command->info('- User 1 (Alice): 2 BUY orders (5 BTC @ 51k, 2 BTC @ 50k)');
        $this->command->info('- User 2 (Bob): 1 SELL order (3 BTC @ 52k)');
        $this->command->info('Ready to test: User 2 can SELL 1 BTC @ 51k to trigger partial fill!');
    }
}
