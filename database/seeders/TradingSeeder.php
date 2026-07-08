<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class TradingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Give User 1 USDT for buying
        Asset::updateOrCreate(
            ['user_id' => 1, 'symbol' => 'USDT'],
            ['amount' => '100000.00000000', 'locked_amount' => '0.00000000']
        );

        // Give User 1 some BTC too
        Asset::updateOrCreate(
            ['user_id' => 2, 'symbol' => 'BTC'],
            ['amount' => '2.00000000', 'locked_amount' => '0.00000000']
        );

        // Give User 2 BTC for selling
        Asset::updateOrCreate(
            ['user_id' => 2, 'symbol' => 'BTC'],
            ['amount' => '5.00000000', 'locked_amount' => '0.00000000']
        );

        // Give User 2 some USDT too
        Asset::updateOrCreate(
            ['user_id' => 2, 'symbol' => 'USDT'],
            ['amount' => '50000.00000000', 'locked_amount' => '0.00000000']
        );

        // Create some sample open orders for BTC/USDT
        // Buy orders (bids)
        Order::create([
            'user_id' => 1,
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'price' => '42000.00000000',
            'amount' => '0.50000000',
            'status' => 'open',
        ]);

        Order::create([
            'user_id' => 1,
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'price' => '41900.00000000',
            'amount' => '1.00000000',
            'status' => 'open',
        ]);

        Order::create([
            'user_id' => 1,
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'price' => '41800.00000000',
            'amount' => '0.75000000',
            'status' => 'open',
        ]);

        // Sell orders (asks)
        Order::create([
            'user_id' => 1,
            'symbol' => 'BTCUSDT',
            'side' => 'sell',
            'price' => '42100.00000000',
            'amount' => '0.50000000',
            'status' => 'open',
        ]);

        Order::create([
            'user_id' => 1,
            'symbol' => 'BTCUSDT',
            'side' => 'sell',
            'price' => '42200.00000000',
            'amount' => '1.00000000',
            'status' => 'open',
        ]);

        Order::create([
            'user_id' => 2,
            'symbol' => 'BTCUSDT',
            'side' => 'sell',
            'price' => '42300.00000000',
            'amount' => '0.75000000',
            'status' => 'open',
        ]);

        $this->command->info('Trading seeder completed!');
        $this->command->info('User 1: trader1@example.com / password');
        $this->command->info('User 2: trader2@example.com / password');
    }
}
