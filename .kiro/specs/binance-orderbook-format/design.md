# Design Document: Binance-Style Orderbook with Price Level Aggregation

## Introduction

This document describes the architectural design for transforming the orderbook API to match Binance's response format while adding configurable price level aggregation. The system will support tick size-based grouping and precision-aware formatting, with all aggregation logic implemented in the PHP service layer using bcmath for numerical precision.

## Architecture Overview

### High-Level Architecture

The system follows a layered architecture pattern:

```
┌─────────────────────────────────────────────────────────────┐
│                     HTTP Layer                               │
│  OrderBookController → Request Validation → Response         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                   Service Layer                              │
│  OrderBookService ← SymbolConfigResolver                     │
│         ↓                                                    │
│  PriceLevelAggregator → ResponseFormatter                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  Data Access Layer                           │
│  OrderRepository ← Symbol Model                              │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    Database Layer                            │
│  orders table, symbols table                                 │
└─────────────────────────────────────────────────────────────┘
```

### Design Principles

1. **Separation of Concerns**: Aggregation logic is isolated in dedicated service classes
2. **Precision First**: All monetary calculations use bcmath functions to prevent floating-point errors
3. **Configurability**: Tick size and precision are stored per-symbol and easily modifiable
4. **Performance**: Aggregation occurs in-memory in a single pass over the data
5. **Backward Compatibility**: Existing API fields (last_price, spread) are preserved

## Component Design


### 1. OrderBookController

**Responsibility**: Handle HTTP requests, validate input, coordinate service calls, format HTTP responses

**Key Methods**:
```php
public function show(string $symbol, OrderBookQueryDTO $queryDTO): JsonResponse
```

**Dependencies**:
- `OrderBookService` (injected via constructor)
- `OrderBookQueryDTO` (form request for validation)

**Validation Logic**:
- Symbol format: alphanumeric with optional dash/underscore, 2-20 characters
- Depth parameter: integer between 1 and 5000, defaults to 20
- Returns 422 for validation failures with JSON error message

**Error Handling**:
- 404: Symbol route parameter missing
- 422: Invalid query parameters
- 500: Internal service errors (logged with context)
- Production mode: Sanitized error messages without stack traces

### 2. OrderBookQueryDTO

**Responsibility**: Encapsulate and validate query parameters

**Properties**:
```php
public ?int $depth = 20;
```

**Validation Rules**:
```php
return [
    'depth' => ['nullable', 'integer', 'min:1', 'max:5000'],
];
```

**Methods**:
- `getDepth(): int` - Returns validated depth with default fallback


### 3. OrderBookService

**Responsibility**: Orchestrate orderbook data retrieval, configuration lookup, aggregation, and formatting

**Key Methods**:
```php
public function getOrderBook(string $symbol, int $depth): array
```

**Dependencies**:
- `OrderRepository` (injected)
- `Symbol` Model (injected)
- `PriceLevelAggregator` (injected)
- `ResponseFormatter` (injected)

**Algorithm Flow**:
1. Retrieve symbol configuration (tick_size, precision) with fallback to defaults
2. Query raw orders from repository (bids and asks separately)
3. Pass orders to aggregator with tick_size and precision
4. Apply depth limit to aggregated price levels
5. Format aggregated data using ResponseFormatter
6. Add last_price and spread fields
7. Return formatted array

**Configuration Caching**:
- Symbol configuration cached per request in service instance
- Lazy loaded only when orderbook is requested
- Cache invalidated between requests (no cross-request caching)

**Error Handling**:
- Database connection failures: Log error, use default tick_size=0.01 and precision=8
- Symbol not found: Use default configuration values
- Repository errors: Bubble up as service exceptions with context


### 4. PriceLevelAggregator

**Responsibility**: Aggregate orders by tick-size-rounded price levels with bcmath precision

**Key Methods**:
```php
public function aggregateBids(Collection $orders, string $tickSize, int $precision): Collection
public function aggregateAsks(Collection $orders, string $tickSize, int $precision): Collection
```

**Aggregation Algorithm** (single-pass):

```php
// Step 1: Round each order price to tick boundary
foreach ($orders as $order) {
    $rounded = $this->roundToTickSize($order->price, $tickSize, $precision);
    
    // Step 2: Group by rounded price
    if (!isset($levels[$rounded])) {
        $levels[$rounded] = [
            'price' => $rounded,
            'amount' => '0',
            'orders' => []
        ];
    }
    
    // Step 3: Aggregate amounts using bcadd
    $levels[$rounded]['amount'] = bcadd(
        $levels[$rounded]['amount'],
        $order->remaining_amount,
        $precision
    );
    
    // Step 4: Track orders for time priority (internal use)
    $levels[$rounded]['orders'][] = $order;
}

// Step 5: Calculate totals using bcmul
foreach ($levels as &$level) {
    $level['total'] = bcmul($level['price'], $level['amount'], $precision);
}

// Step 6: Sort levels (bids descending, asks ascending)
return $this->sortLevels($levels, $direction);
```

**Tick Size Rounding**:
```php
private function roundToTickSize(string $price, string $tickSize, int $precision): string
{
    bcscale($precision);
    $divided = bcdiv($price, $tickSize, 0);  // floor division
    return bcmul($divided, $tickSize, $precision);
}
```

**Key Characteristics**:
- Processes bids and asks independently
- Excludes orders with remaining_amount == 0
- Maintains order time priority within each level for matching purposes
- Single iteration over order collection
- All arithmetic uses bcmath for precision


### 5. ResponseFormatter

**Responsibility**: Transform aggregated price levels into Binance-compatible array format

**Key Methods**:
```php
public function formatOrderBook(
    Collection $bids,
    Collection $asks,
    int $precision,
    string $lastPrice,
    string $spread
): array
```

**Output Format**:
```php
[
    'bids' => [
        ["50100.50", "1.5", "75150.75"],  // [price, amount, total]
        ["50100.00", "2.3", "115230.00"],
        ...
    ],
    'asks' => [
        ["50101.00", "0.8", "40080.80"],
        ["50101.50", "1.2", "60121.80"],
        ...
    ],
    'last_price' => "50100.75",
    'spread' => "0.50"
]
```

**Formatting Logic**:
```php
private function formatLevel(array $level, int $precision): array
{
    return [
        $this->formatNumber($level['price'], $precision),
        $this->formatNumber($level['amount'], $precision),
        $this->formatNumber($level['total'], $precision),
    ];
}

private function formatNumber(string $value, int $precision): string
{
    bcscale($precision);
    $formatted = bcadd($value, '0', $precision);  // Apply precision
    return rtrim(rtrim($formatted, '0'), '.');     // Trim trailing zeros
}
```

**Key Features**:
- All numeric values converted to strings
- Trailing zeros removed (e.g., "10.50" becomes "10.5")
- Never uses scientific notation
- Maintains field order: [price, amount, total]
- Preserves sort order from aggregator


### 6. OrderRepository

**Responsibility**: Query orders from database with appropriate filters and sorting

**Key Methods**:
```php
public function getOpenBids(string $symbol, ?int $limit = null): Collection
public function getOpenAsks(string $symbol, ?int $limit = null): Collection
```

**Query Structure** (Bids):
```php
return Order::where('symbol', $symbol)
    ->whereIn('status', ['open', 'partially_filled'])
    ->where('remaining_amount', '>', 0)
    ->where('side', 'buy')
    ->orderByDesc('price')
    ->orderBy('created_at')
    ->when($limit, fn($query) => $query->limit($limit * 2))  // Pre-aggregation buffer
    ->get(['id', 'price', 'remaining_amount', 'created_at']);
```

**Query Structure** (Asks):
- Same as bids but with `side = 'sell'` and `orderBy('price')` ascending

**Performance Optimizations**:
- Indexes on: (symbol, status, side), (price), (created_at)
- Selective column retrieval (id, price, remaining_amount, created_at)
- Pre-aggregation limit multiplied by 2 to account for potential aggregation
- Database transaction wrapper for consistency

**Sorting Logic**:
- Bids: price DESC, created_at ASC (highest price first, oldest first within price)
- Asks: price ASC, created_at ASC (lowest price first, oldest first within price)


### 7. Symbol Model

**Responsibility**: Represent symbol configuration with tick size and precision

**Database Schema**:
```php
Schema::create('symbols', function (Blueprint $table) {
    $table->id();
    $table->string('symbol', 20)->unique();
    $table->decimal('tick_size', 20, 10)->default(0.01);
    $table->unsignedTinyInteger('precision')->default(8);
    $table->timestamps();
    
    $table->index('symbol');
});
```

**Model Attributes**:
```php
protected $fillable = ['symbol', 'tick_size', 'precision'];

protected $casts = [
    'tick_size' => 'string',  // Preserve precision, avoid float conversion
    'precision' => 'integer',
];
```

**Validation Rules** (Form Request or Model Observer):
```php
'tick_size' => ['required', 'numeric', 'min:0.0000000001', 'max:1000000', 'decimal:0,10'],
'precision' => ['required', 'integer', 'min:0', 'max:18'],
```

**Default Behavior**:
- Database defaults: tick_size=0.01, precision=8
- Model accessor provides fallback if column is NULL
- Service layer provides fallback if symbol record doesn't exist


## Data Flow

### Request Flow (Successful Path)

```
1. Client Request
   GET /api/orderbook/BTCUSDT?depth=50
   
2. OrderBookController::show()
   - Validate symbol format
   - Validate depth via OrderBookQueryDTO
   
3. OrderBookService::getOrderBook('BTCUSDT', 50)
   - Lookup Symbol configuration (tick_size=0.01, precision=2)
   - Call OrderRepository::getOpenBids('BTCUSDT', 50)
   - Call OrderRepository::getOpenAsks('BTCUSDT', 50)
   
4. Database Query
   SELECT id, price, remaining_amount, created_at
   FROM orders
   WHERE symbol = 'BTCUSDT'
     AND status IN ('open', 'partially_filled')
     AND remaining_amount > 0
     AND side = 'buy'
   ORDER BY price DESC, created_at ASC
   LIMIT 100
   
5. PriceLevelAggregator::aggregateBids($orders, '0.01', 2)
   - Round prices to tick boundaries
   - Group by rounded price
   - Sum amounts with bcadd
   - Calculate totals with bcmul
   - Sort descending
   
6. Apply Depth Limit
   - Take first 50 aggregated levels
   
7. ResponseFormatter::formatOrderBook()
   - Convert to [[price, amount, total], ...] format
   - Format numbers as strings with precision
   - Trim trailing zeros
   
8. Add Metadata
   - Append last_price and spread
   
9. JSON Response
   HTTP 200
   Content-Type: application/json
   {
     "bids": [["50100.5", "1.5", "75150.75"], ...],
     "asks": [["50101", "0.8", "40080.8"], ...],
     "last_price": "50100.75",
     "spread": "0.5"
   }
```


### Error Flow Examples

**Invalid Depth Parameter**:
```
1. GET /api/orderbook/BTCUSDT?depth=-5
2. OrderBookQueryDTO validation fails
3. Return 422 with {"message": "The depth must be at least 1."}
```

**Symbol Not Found (No Orders)**:
```
1. GET /api/orderbook/XYZABC?depth=20
2. Symbol configuration not found → use defaults
3. Repository returns empty collections
4. Return 200 with {"bids": [], "asks": [], "last_price": null, "spread": null}
```

**Database Connection Failure**:
```
1. Symbol configuration query fails
2. Log error with context
3. Use default tick_size=0.01, precision=8
4. Continue with orderbook retrieval
5. If orders query also fails → return 500 with {"message": "Internal server error"}
```

## Data Models

### Order Model (Existing)

**Relevant Fields**:
```php
'id' => integer
'symbol' => string
'side' => enum('buy', 'sell')
'price' => decimal(20,8)
'amount' => decimal(20,8)
'remaining_amount' => decimal(20,8)
'status' => enum('open', 'partially_filled', 'filled', 'cancelled')
'created_at' => timestamp
```

**Indexes** (Required for Performance):
```sql
INDEX idx_symbol_status_side (symbol, status, side)
INDEX idx_price (price)
INDEX idx_created_at (created_at)
```


### Symbol Model (New)

**Fields**:
```php
'id' => integer (primary key)
'symbol' => string(20) (unique, indexed)
'tick_size' => decimal(20,10) (default: 0.01)
'precision' => unsigned tinyint (default: 8)
'created_at' => timestamp
'updated_at' => timestamp
```

**Example Records**:
```php
['symbol' => 'BTCUSDT', 'tick_size' => '0.01', 'precision' => 2]
['symbol' => 'ETHUSDT', 'tick_size' => '0.01', 'precision' => 2]
['symbol' => 'DOGEUSDT', 'tick_size' => '0.00001', 'precision' => 5]
['symbol' => 'BNBUSDT', 'tick_size' => '0.1', 'precision' => 1]
```

## Technical Decisions

### Why bcmath Instead of Native Float?

**Problem**: PHP float arithmetic loses precision for monetary calculations
```php
// Float precision loss
0.1 + 0.2 === 0.3  // false
(0.1 + 0.2) == 0.30000000000000004  // true

// bcmath maintains precision
bcadd('0.1', '0.2', 8) === '0.30000000'  // true
```

**Solution**: Use bcmath for all monetary operations (add, multiply, divide, compare)

**Trade-offs**:
- ✅ Exact decimal precision
- ✅ No rounding errors in aggregation
- ❌ Slightly slower than float operations
- ❌ Works with strings, requires conversion


### Why Aggregation in PHP, Not Database?

**Alternatives Considered**:

1. **Database-side aggregation** (SQL GROUP BY):
   ```sql
   SELECT FLOOR(price / 0.01) * 0.01 AS rounded_price,
          SUM(remaining_amount) AS total_amount
   FROM orders
   GROUP BY rounded_price
   ```
   - ❌ Loses order time priority for matching
   - ❌ Tick size as parameter requires dynamic SQL
   - ❌ Precision handling varies by database engine
   - ✅ Potentially faster for large datasets

2. **PHP service-layer aggregation** (Chosen):
   - ✅ Preserves order records for time priority
   - ✅ Easy tick size parameterization
   - ✅ Consistent bcmath precision
   - ✅ Easier to test and modify logic
   - ✅ Can optimize with in-memory caching if needed

**Decision**: PHP aggregation provides better maintainability and correctness guarantees for this use case. Performance is acceptable for typical orderbook sizes (< 1000 orders per side).

### Why Tick Size per Symbol?

Different trading pairs have different natural price increments:
- BTCUSDT: $0.01 (tick_size=0.01)
- ETHUSDT: $0.01 (tick_size=0.01)
- SHIBUSDT: $0.00000001 (tick_size=0.00000001)
- BNBBTC: 0.000001 BTC (tick_size=0.000001)

Tick size must match the symbol's trading precision to avoid artificial price levels.


### Why Apply Depth Limit After Aggregation?

**Order of Operations**:
```
Raw Orders (500 bids) → Aggregation (150 price levels) → Depth Limit (20 levels)
```

**Rationale**:
- Depth parameter represents "number of price levels", not "number of orders"
- Aggregation reduces the count, so must happen before limiting
- Example: 100 orders at 5 different prices → depth=5, not depth=100

**Alternative** (Incorrect):
```
Raw Orders (500 bids) → Depth Limit (20 orders) → Aggregation (10 levels)
```
- Would arbitrarily cut off orders before seeing full price distribution
- Depth count would be unpredictable

### Why String Type for Numeric Values in Response?

**Binance API Standard**:
```json
{
  "bids": [["50100.50", "1.5", "75150.75"]]
}
```

**Reasons**:
1. **Precision Preservation**: JSON numbers can lose precision in clients
   ```javascript
   JSON.parse('{"price": 0.00000001}').price  // May round
   ```
2. **Consistency**: All trading APIs (Binance, Coinbase, Kraken) use strings
3. **Decimal Libraries**: Clients can parse into Decimal/BigNumber without loss
4. **No Scientific Notation**: Strings avoid automatic scientific notation (1e-8)


## Performance Considerations

### Expected Load Characteristics

**Typical Orderbook**:
- Active symbol: 200-500 orders per side
- Inactive symbol: 10-50 orders per side
- After aggregation: 20-100 price levels per side

**Query Performance**:
- Database query: ~10-20ms (with indexes)
- PHP aggregation: ~5-10ms (single pass)
- JSON formatting: ~2-5ms
- **Total response time**: ~20-40ms (depth ≤ 100)

### Optimization Strategies

**1. Database Indexes**:
```sql
-- Composite index for primary query
CREATE INDEX idx_orders_orderbook ON orders(symbol, status, side, remaining_amount, price);

-- Supporting indexes
CREATE INDEX idx_orders_created ON orders(created_at);
```

**2. Selective Column Retrieval**:
```php
->get(['id', 'price', 'remaining_amount', 'created_at'])  // Not SELECT *
```

**3. In-Memory Aggregation**:
- Single pass over data
- Array-based grouping (hash map behavior)
- No repeated database queries

**4. Lazy Configuration Loading**:
```php
// Only query symbol config when orderbook is actually requested
$config = $this->symbolConfig ??= $this->loadSymbolConfig($symbol);
```


**5. Response Caching** (Optional Future Enhancement):
```php
// Cache orderbook for 100ms to handle burst traffic
Cache::remember("orderbook:{$symbol}:{$depth}", 0.1, fn() => $this->generateOrderBook());
```

### Scalability Limits

**Current Design Scales To**:
- 10,000 orders per symbol (aggregation: ~50ms)
- 100 requests/second per symbol (with caching)
- 1000+ simultaneous symbols

**If Exceeds Limits**:
- Consider moving aggregation to database stored procedure
- Use Redis for orderbook caching with WebSocket updates
- Pre-aggregate orderbook on order insert/update events

## Error Handling Strategy

### Error Categories

**1. Client Errors (4xx)**:
```php
// 404: Missing symbol parameter (routing level)
// 422: Invalid query parameters
return response()->json([
    'message' => 'The depth must be at least 1.',
    'errors' => ['depth' => ['The depth must be at least 1.']]
], 422);
```

**2. Server Errors (5xx)**:
```php
// 500: Unexpected exceptions
try {
    return $this->orderBookService->getOrderBook($symbol, $depth);
} catch (\Exception $e) {
    Log::error('Orderbook retrieval failed', [
        'symbol' => $symbol,
        'depth' => $depth,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    return response()->json([
        'message' => app()->isProduction() 
            ? 'Internal server error' 
            : $e->getMessage()
    ], 500);
}
```


**3. Graceful Degradation**:
```php
// Symbol config not found → use defaults
$tickSize = $symbol->tick_size ?? '0.01';
$precision = $symbol->precision ?? 8;

// Database connection fails → log and use defaults
try {
    $symbol = Symbol::where('symbol', $symbol)->first();
} catch (QueryException $e) {
    Log::warning('Symbol config query failed, using defaults', [
        'symbol' => $symbol,
        'error' => $e->getMessage()
    ]);
    $symbol = null;  // Triggers default fallback
}
```

### Logging Strategy

**What to Log**:
- ✅ Configuration query failures (warning level)
- ✅ Service exceptions with context (error level)
- ✅ Unexpected empty results for active symbols (info level)
- ❌ Successful requests (too noisy)
- ❌ Client validation errors (expected behavior)

**Log Context**:
```php
Log::error('Orderbook aggregation failed', [
    'symbol' => $symbol,
    'depth' => $depth,
    'tick_size' => $tickSize,
    'precision' => $precision,
    'bid_count' => $bids->count(),
    'ask_count' => $asks->count(),
    'exception' => $e->getMessage(),
]);
```

