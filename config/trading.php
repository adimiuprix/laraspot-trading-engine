<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Market Order Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for market order execution and safety limits.
    |
    */

    'market_order' => [
        // Maximum allowed slippage percentage (difference between best price and average execution price)
        'max_slippage_percent' => env('MARKET_ORDER_MAX_SLIPPAGE', 5),

        // Maximum allowed price impact percentage (how much the order moves the market)
        'max_price_impact_percent' => env('MARKET_ORDER_MAX_PRICE_IMPACT', 10),

        // Minimum liquidity ratio (e.g., 0.1 = at least 10% of order quantity must be available)
        'min_liquidity_ratio' => env('MARKET_ORDER_MIN_LIQUIDITY_RATIO', 0.1),

        // Maximum number of orders to fetch for matching
        'max_orders_to_fetch' => env('MARKET_ORDER_MAX_ORDERS_FETCH', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trading Fees
    |--------------------------------------------------------------------------
    */

    'fees' => [
        // Maker fee (for limit orders that add liquidity)
        'maker' => env('TRADING_FEE_MAKER', 0.001), // 0.1%

        // Taker fee (for market orders that take liquidity)
        'taker' => env('TRADING_FEE_TAKER', 0.001), // 0.1%
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // Minimum order quantity (in base currency)
        'min_order_quantity' => env('MIN_ORDER_quantity', '0.0001'),

        // Maximum order quantity (in base currency, 0 = unlimited)
        'max_order_quantity' => env('MAX_ORDER_quantity', 0),

        // Minimum order value (in quote currency)
        'min_order_value' => env('MIN_ORDER_VALUE', '10'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kline/Candlestick Intervals
    |--------------------------------------------------------------------------
    |
    | Supported intervals for OHLCV (candlestick) data generation.
    | Key = interval name, Value = seconds
    |
    | Available intervals:
    | - 1m (1 minute) = 60 seconds
    | - 3m (3 minutes) = 180 seconds
    | - 5m (5 minutes) = 300 seconds
    | - 15m (15 minutes) = 900 seconds
    | - 30m (30 minutes) = 1800 seconds
    | - 1h (1 hour) = 3600 seconds
    | - 4h (4 hours) = 14400 seconds
    | - 1d (1 day) = 86400 seconds
    |
    */

    'kline_intervals' => [
        '1m' => 60,
    ],
];
