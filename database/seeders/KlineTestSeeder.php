<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AssetService;
use App\Services\Trading\OrderService;
use App\DTOs\PlaceLimitOrderDTO;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KlineTestSeeder extends Seeder
{
    /**
     * Seed test data for kline generation.
     * 
     * This creates users, gives them balance, and places orders that will match
     * to generate trades, which will then generate klines.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create two test users
            $buyer = User::factory()->create([
                'name' => 'Test Buyer',
                'email' => 'buyer@test.com',
            ]);

            $seller = User::factory()->create([
                'name' => 'Test Seller',
                'email' => 'seller@test.com',
            ]);

            $assetService = app(AssetService::class);
            $orderService = app(OrderService::class);

            // Give buyer USDT balance
            $buyerUsdt = $assetService->getOrCreateAsset($buyer, 'USDT');
            $buyerUsdt->quantity = '100000.00000000';
            $buyerUsdt->save();

            // Give seller BTC balance
            $sellerBtc = $assetService->getOrCreateAsset($seller, 'BTC');
            $sellerBtc->quantity = '10.00000000';
            $sellerBtc->save();

            $this->command->info('Created test users with balances');

            // Place sell orders at different times
            $now = now();
            
            $this->command->info('Placing sell orders...');
            
            // Sell order 1: 0.1 BTC @ 50000
            $sellOrder1 = $orderService->placeOrder(new PlaceLimitOrderDTO(
                user: $seller,
                symbol: 'BTC/USDT',
                side: 'sell',
                price: '50000.00000000',
                quantity: '0.10000000'
            ));
            $this->command->info("Placed sell order #{$sellOrder1->id}");

            // Wait 30 seconds simulation
            sleep(2);

            // Sell order 2: 0.15 BTC @ 50100
            $sellOrder2 = $orderService->placeOrder(new PlaceLimitOrderDTO(
                user: $seller,
                symbol: 'BTC/USDT',
                side: 'sell',
                price: '50100.00000000',
                quantity: '0.15000000'
            ));
            $this->command->info("Placed sell order #{$sellOrder2->id}");

            sleep(2);

            // Sell order 3: 0.2 BTC @ 49900
            $sellOrder3 = $orderService->placeOrder(new PlaceLimitOrderDTO(
                user: $seller,
                symbol: 'BTC/USDT',
                side: 'sell',
                price: '49900.00000000',
                quantity: '0.20000000'
            ));
            $this->command->info("Placed sell order #{$sellOrder3->id}");

            sleep(2);

            $this->command->info('Placing buy orders to trigger matches...');

            // Buy order 1: Will match with sell order 3 (best price)
            $buyOrder1 = $orderService->placeOrder(new PlaceLimitOrderDTO(
                user: $buyer,
                symbol: 'BTC/USDT',
                side: 'buy',
                price: '50000.00000000',
                quantity: '0.20000000'
            ));
            $this->command->info("Placed buy order #{$buyOrder1->id} - should match");

            sleep(3);

            // Buy order 2: Will match with sell order 1
            $buyOrder2 = $orderService->placeOrder(new PlaceLimitOrderDTO(
                user: $buyer,
                symbol: 'BTC/USDT',
                side: 'buy',
                price: '50050.00000000',
                quantity: '0.10000000'
            ));
            $this->command->info("Placed buy order #{$buyOrder2->id} - should match");

            sleep(3);

            // Buy order 3: Will match with sell order 2
            $buyOrder3 = $orderService->placeOrder(new PlaceLimitOrderDTO(
                user: $buyer,
                symbol: 'BTC/USDT',
                side: 'buy',
                price: '50150.00000000',
                quantity: '0.15000000'
            ));
            $this->command->info("Placed buy order #{$buyOrder3->id} - should match");

            $this->command->newLine();
            $this->command->info('✓ Test data seeded successfully!');
            $this->command->info('Run: php artisan queue:work to process kline updates');
            $this->command->info('Then check: GET /api/klines/BTC/USDT?interval=1m');
        });
    }
}
