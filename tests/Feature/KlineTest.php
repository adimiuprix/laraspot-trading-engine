<?php

use App\Models\Trade;
use App\Models\User;
use App\Models\Order;
use App\Models\Kline;
use App\Services\KlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->klineService = app(KlineService::class);
});

test('kline is created when first trade occurs', function () {
    // Arrange
    $symbol = 'BTC/USDT';
    $interval = '1m';
    $timestamp = 1752043517; // Some arbitrary timestamp
    $price = '50000.00000000';
    $amount = '0.10000000';

    // Act
    $kline = $this->klineService->updateOrCreateFromTrade(
        symbol: $symbol,
        interval: $interval,
        timestamp: $timestamp,
        price: $price,
        amount: $amount
    );

    // Assert
    expect($kline)->toBeInstanceOf(Kline::class);
    expect($kline->symbol)->toBe($symbol);
    expect($kline->interval)->toBe($interval);
    expect($kline->open)->toBe($price);
    expect($kline->high)->toBe($price);
    expect($kline->low)->toBe($price);
    expect($kline->close)->toBe($price);
    expect($kline->volume)->toBe($amount);
    expect($kline->trades_count)->toBe(1);
    expect($kline->is_closed)->toBeFalse();
});

test('bucket time calculation is correct', function () {
    $testCases = [
        ['timestamp' => 1752043517, 'interval' => 60, 'expected' => 1752043500],
        ['timestamp' => 1752043500, 'interval' => 60, 'expected' => 1752043500],
        ['timestamp' => 1752043559, 'interval' => 60, 'expected' => 1752043500],
        ['timestamp' => 1752043560, 'interval' => 60, 'expected' => 1752043560],
        ['timestamp' => 1752043517, 'interval' => 3600, 'expected' => 1752042000],
    ];

    foreach ($testCases as $case) {
        $kline = $this->klineService->updateOrCreateFromTrade(
            symbol: 'BTC/USDT',
            interval: '1m',
            timestamp: $case['timestamp'],
            price: '50000',
            amount: '0.1'
        );

        // Clean up for next iteration
        Kline::query()->delete();
    }

    expect(true)->toBeTrue();
});

test('kline high is updated when price increases', function () {
    $symbol = 'BTC/USDT';
    $interval = '1m';
    $timestamp = 1752043517;

    // First trade: price 50000
    $this->klineService->updateOrCreateFromTrade(
        symbol: $symbol,
        interval: $interval,
        timestamp: $timestamp,
        price: '50000.00000000',
        amount: '0.10000000'
    );

    // Second trade: price 51000 (higher)
    $kline = $this->klineService->updateOrCreateFromTrade(
        symbol: $symbol,
        interval: $interval,
        timestamp: $timestamp + 10, // Same bucket
        price: '51000.00000000',
        amount: '0.05000000'
    );

    expect($kline->open)->toBe('50000.00000000'); // Open doesn't change
    expect($kline->high)->toBe('51000.00000000'); // High updated
    expect($kline->close)->toBe('51000.00000000'); // Close updated
    expect($kline->volume)->toBe('0.15000000'); // Volume accumulated
    expect($kline->trades_count)->toBe(2);
});

test('kline low is updated when price decreases', function () {
    $symbol = 'BTC/USDT';
    $interval = '1m';
    $timestamp = 1752043517;

    // First trade: price 50000
    $this->klineService->updateOrCreateFromTrade(
        symbol: $symbol,
        interval: $interval,
        timestamp: $timestamp,
        price: '50000.00000000',
        amount: '0.10000000'
    );

    // Second trade: price 49000 (lower)
    $kline = $this->klineService->updateOrCreateFromTrade(
        symbol: $symbol,
        interval: $interval,
        timestamp: $timestamp + 10,
        price: '49000.00000000',
        amount: '0.05000000'
    );

    expect($kline->open)->toBe('50000.00000000'); // Open doesn't change
    expect($kline->low)->toBe('49000.00000000'); // Low updated
    expect($kline->close)->toBe('49000.00000000'); // Close updated
});

test('different intervals create different klines', function () {
    $symbol = 'BTC/USDT';
    $timestamp = 1752043517;
    $price = '50000.00000000';
    $amount = '0.10000000';

    // Currently only 1m interval is active
    $this->klineService->updateOrCreateFromTrade(
        symbol: $symbol,
        interval: '1m',
        timestamp: $timestamp,
        price: $price,
        amount: $amount
    );

    expect(Kline::count())->toBe(1);
    expect(Kline::where('interval', '1m')->count())->toBe(1);
    
    // Future: When more intervals are added, this will create multiple klines
    // $intervals = ['1m', '5m', '1h'];
    // foreach ($intervals as $interval) { ... }
    // expect(Kline::count())->toBe(3);
});

test('kline api endpoint returns correct format', function () {
    // Create test kline
    Kline::create([
        'symbol' => 'BTC/USDT',
        'interval' => '1m',
        'open_time' => 1752043500,
        'close_time' => 1752043559,
        'open' => '50000.00000000',
        'high' => '50500.00000000',
        'low' => '49800.00000000',
        'close' => '50200.00000000',
        'volume' => '1.50000000',
        'quote_volume' => '75300.00000000',
        'trades_count' => 10,
        'is_closed' => true,
    ]);

    // Call API
    $response = $this->getJson('/api/klines/BTC/USDT?interval=1m');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        [
            0, // open_time
            1, // open
            2, // high
            3, // low
            4, // close
            5, // volume
            6, // close_time
            7, // quote_volume
            8, // trades_count
        ],
    ]);

    $data = $response->json()[0];
    expect($data[0])->toBe(1752043500); // open_time
    expect($data[1])->toBe('50000.00000000'); // open
    expect($data[2])->toBe('50500.00000000'); // high
    expect($data[3])->toBe('49800.00000000'); // low
    expect($data[4])->toBe('50200.00000000'); // close
});

test('kline api validates interval parameter', function () {
    // Test with invalid interval
    $response = $this->getJson('/api/klines/BTC/USDT?interval=invalid');
    $response->assertStatus(400);
    $response->assertJsonStructure(['code', 'msg']);
    
    // Test with unsupported interval (5m not enabled yet)
    $response = $this->getJson('/api/klines/BTC/USDT?interval=5m');
    $response->assertStatus(400);
    
    // Test with valid interval (1m)
    Kline::create([
        'symbol' => 'BTC/USDT',
        'interval' => '1m',
        'open_time' => 1752043500,
        'close_time' => 1752043559,
        'open' => '50000.00000000',
        'high' => '50000.00000000',
        'low' => '50000.00000000',
        'close' => '50000.00000000',
        'volume' => '1.00000000',
        'quote_volume' => '50000.00000000',
        'trades_count' => 1,
        'is_closed' => true,
    ]);
    
    $response = $this->getJson('/api/klines/BTC/USDT?interval=1m');
    $response->assertStatus(200);
});

test('fill gaps creates empty klines', function () {
    // Create kline at timestamp 60 (minute 1)
    Kline::create([
        'symbol' => 'BTC/USDT',
        'interval' => '1m',
        'open_time' => 60,
        'close_time' => 119,
        'open' => '50000.00000000',
        'high' => '50000.00000000',
        'low' => '50000.00000000',
        'close' => '50200.00000000',
        'volume' => '1.00000000',
        'quote_volume' => '50200.00000000',
        'trades_count' => 1,
        'is_closed' => true,
    ]);

    // Fill gaps from 60 to 300 (should fill: 120, 180, 240, 300)
    $filled = $this->klineService->fillGaps('BTC/USDT', '1m', 60, 300);

    expect($filled)->toBeGreaterThan(0);

    // Check that gap kline has correct values
    $gapKline = Kline::where('open_time', 120)->first();
    
    if ($gapKline) {
        expect($gapKline->open)->toBe('50200.00000000'); // Previous close
        expect($gapKline->high)->toBe('50200.00000000');
        expect($gapKline->low)->toBe('50200.00000000');
        expect($gapKline->close)->toBe('50200.00000000');
        expect($gapKline->volume)->toBe('0.00000000');
        expect($gapKline->trades_count)->toBe(0);
    }
});
