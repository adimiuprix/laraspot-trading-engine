<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Give User 1 USDT for buying (500k USDT)
        Asset::updateOrCreate(
            ['user_id' => 1, 'symbol' => 'USDT'],
            ['quantity' => '500000.00000000', 'locked_quantity' => '0.00000000']
        );

        // Give User 1 some BTC too
        Asset::updateOrCreate(
            ['user_id' => 1, 'symbol' => 'BTC'],
            ['quantity' => '20.00000000', 'locked_quantity' => '0.00000000']
        );

        // Give User 2 BTC for selling
        Asset::updateOrCreate(
            ['user_id' => 2, 'symbol' => 'BTC'],
            ['quantity' => '50.00000000', 'locked_quantity' => '0.00000000']
        );

        // Give User 2 some USDT too
        Asset::updateOrCreate(
            ['user_id' => 2, 'symbol' => 'USDT'],
            ['quantity' => '500000.00000000', 'locked_quantity' => '0.00000000']
        );
    }
}
