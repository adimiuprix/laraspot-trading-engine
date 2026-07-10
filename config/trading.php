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
    | Note: Currently only 1-minute interval is enabled.
    | Other intervals can be added later: 3m, 5m, 15m, 30m, 1h, 4h, 1d, etc.
    |
    */

    'kline_intervals' => [
        '1m' => 60,  // 1 minute only
        
        // Future intervals (uncomment when ready):
        // '3m' => 180,
        // '5m' => 300,
        // '15m' => 900,
        // '30m' => 1800,
        // '1h' => 3600,
        // '4h' => 14400,
        // '1d' => 86400,
    ],
];
