# Requirements Document

## Introduction

This feature enhances the orderbook API to match Binance's response format and adds price level aggregation capabilities. The system will transform the current object-based orderbook format into a nested array format compatible with Binance API standards. Additionally, it introduces configurable precision and tick size support at the symbol level, enabling price level aggregation within the service layer.

## Glossary

- **Orderbook_API**: The HTTP API endpoint that returns bid and ask orders for a trading symbol
- **Price_Level**: A specific price point in the orderbook where one or more orders exist
- **Tick_Size**: The minimum price increment for a trading symbol (e.g., 0.01, 0.1, 1.0)
- **Precision**: The number of decimal places used for price representation
- **Aggregation_Service**: The PHP service component that combines multiple orders at the same price level
- **Symbol_Configuration**: Database-stored settings that define tick size and precision for each trading pair
- **Response_Formatter**: The component that transforms orderbook data into Binance-compatible array format
- **Order_Repository**: The data access layer that retrieves open and partially filled orders
- **OrderBook_Service**: The business logic layer that orchestrates orderbook data retrieval and formatting
- **OrderBook_Controller**: The HTTP controller that handles orderbook API requests
- **OrderBook_QueryDTO**: The data transfer object that encapsulates orderbook query parameters
- **Symbol_Model**: The Eloquent model representing trading pair configurations

## Requirements

### Requirement 1: Response Format Transformation

**User Story:** As an API consumer, I want orderbook responses in Binance-compatible array format, so that I can integrate with existing trading infrastructure without modification

#### Acceptance Criteria

1. THE Orderbook_API SHALL return bids as a two-dimensional array where each element is ["price", "quantity", "total"]
2. THE Orderbook_API SHALL return asks as a two-dimensional array where each element is ["price", "quantity", "total"]
3. THE Response_Formatter SHALL convert all numeric values to string representation
4. THE Orderbook_API SHALL maintain backward compatibility by preserving last_price and spread fields
5. WHEN the orderbook is empty, THE Orderbook_API SHALL return empty arrays for bids and asks
6. THE Response_Formatter SHALL order bids from highest to lowest price
7. THE Response_Formatter SHALL order asks from lowest to highest price
8. THE Orderbook_API SHALL return HTTP status 200 for successful requests

### Requirement 2: Symbol Configuration Storage

**User Story:** As a system administrator, I want to configure tick size and precision per trading symbol, so that orderbook aggregation matches exchange-specific trading rules

#### Acceptance Criteria

1. THE Symbol_Configuration SHALL store tick_size as a decimal value in the database
2. THE Symbol_Configuration SHALL store precision as an integer value in the database
3. THE Symbol_Model SHALL provide access to tick_size and precision attributes
4. WHEN tick_size is not configured, THE Symbol_Configuration SHALL use a default value of 0.01
5. WHEN precision is not configured, THE Symbol_Configuration SHALL use a default value of 8
6. THE Symbol_Configuration SHALL prevent negative values for tick_size and precision
7. THE Symbol_Configuration SHALL allow tick_size values with up to 10 decimal places
8. THE Symbol_Configuration SHALL allow precision values between 0 and 18

### Requirement 3: Price Level Aggregation

**User Story:** As an API consumer, I want orders at identical price levels to be aggregated, so that I can see total liquidity at each price point

#### Acceptance Criteria

1. WHEN multiple orders exist at the same price level, THE Aggregation_Service SHALL sum their quantitys
2. THE Aggregation_Service SHALL calculate total as price multiplied by aggregated quantity
3. THE Aggregation_Service SHALL perform aggregation before applying depth limit
4. THE Aggregation_Service SHALL preserve time priority within each price level for matching purposes
5. WHEN aggregating quantitys, THE Aggregation_Service SHALL use bcadd with precision from Symbol_Configuration
6. WHEN calculating totals, THE Aggregation_Service SHALL use bcmul with precision from Symbol_Configuration
7. THE Aggregation_Service SHALL exclude orders with zero remaining_quantity from aggregation
8. THE Aggregation_Service SHALL process bids and asks independently

### Requirement 4: Tick Size-Based Aggregation

**User Story:** As a trading system operator, I want orders aggregated according to configured tick size, so that orderbook displays align with exchange trading rules

#### Acceptance Criteria

1. THE Aggregation_Service SHALL round order prices to the nearest tick_size boundary
2. WHEN tick_size is 0.01, THE Aggregation_Service SHALL group orders at 10.50, 10.51, 10.52, etc.
3. WHEN tick_size is 1.0, THE Aggregation_Service SHALL group orders at 10, 11, 12, etc.
4. THE Aggregation_Service SHALL round prices down to tick boundaries using bcmul and bcdiv operations
5. WHEN rounding to tick boundaries, THE Aggregation_Service SHALL use the formula: floor(price / tick_size) * tick_size
6. THE Aggregation_Service SHALL apply tick size aggregation after retrieving orders from Order_Repository
7. THE Aggregation_Service SHALL maintain distinct price levels after tick size aggregation
8. WHEN orders round to the same tick boundary, THE Aggregation_Service SHALL aggregate their quantitys

### Requirement 5: Query Parameter Support

**User Story:** As an API consumer, I want to control orderbook depth via query parameter, so that I can optimize response size for my use case

#### Acceptance Criteria

1. THE Orderbook_API SHALL accept a depth query parameter as an integer
2. WHEN depth parameter is omitted, THE Orderbook_API SHALL default to 20 levels
3. THE Orderbook_API SHALL limit depth parameter to a maximum of 5000 levels
4. WHEN depth parameter is less than 1, THE Orderbook_API SHALL return HTTP status 422
5. WHEN depth parameter is not an integer, THE Orderbook_API SHALL return HTTP status 422
6. THE OrderBook_QueryDTO SHALL validate and sanitize the depth parameter
7. THE Aggregation_Service SHALL apply depth limit after price level aggregation
8. THE Orderbook_API SHALL return exactly depth levels or fewer if insufficient orders exist

### Requirement 6: Database Migration for Symbol Configuration

**User Story:** As a developer, I want database schema to support symbol-specific configurations, so that tick size and precision can be persisted

#### Acceptance Criteria

1. THE Symbol_Configuration SHALL create a symbols table with id, symbol, tick_size, and precision columns
2. THE symbols table SHALL have symbol as a unique varchar(20) column
3. THE symbols table SHALL have tick_size as a decimal(20,10) column with default value 0.01
4. THE symbols table SHALL have precision as an unsigned tinyint column with default value 8
5. THE symbols table SHALL include created_at and updated_at timestamp columns
6. THE migration SHALL be reversible with a down method that drops the symbols table
7. THE migration SHALL set the primary key on the id column
8. THE migration SHALL create an index on the symbol column for query performance

### Requirement 7: Service Layer Integration

**User Story:** As a developer, I want OrderBook_Service to use Symbol_Configuration, so that aggregation respects per-symbol settings

#### Acceptance Criteria

1. THE OrderBook_Service SHALL retrieve Symbol_Configuration for the requested symbol
2. WHEN Symbol_Configuration does not exist for a symbol, THE OrderBook_Service SHALL use default values
3. THE OrderBook_Service SHALL pass tick_size and precision to Aggregation_Service
4. THE OrderBook_Service SHALL inject Symbol_Model dependency via constructor
5. THE OrderBook_Service SHALL handle database connection failures gracefully
6. WHEN Symbol_Configuration query fails, THE OrderBook_Service SHALL log the error and use default values
7. THE OrderBook_Service SHALL cache Symbol_Configuration within the request lifecycle
8. THE OrderBook_Service SHALL not modify Symbol_Configuration during orderbook retrieval

### Requirement 8: Precision-Aware Number Formatting

**User Story:** As an API consumer, I want numeric values formatted with appropriate precision, so that I receive consistent decimal representations

#### Acceptance Criteria

1. THE Response_Formatter SHALL format prices using precision from Symbol_Configuration
2. THE Response_Formatter SHALL format quantitys using precision from Symbol_Configuration
3. THE Response_Formatter SHALL format totals using precision from Symbol_Configuration
4. THE Response_Formatter SHALL use bcscale to set precision for all bc math operations
5. THE Response_Formatter SHALL trim trailing zeros from formatted numbers
6. WHEN precision is 2, THE Response_Formatter SHALL format 10.50000000 as "10.5"
7. WHEN precision is 8, THE Response_Formatter SHALL format 0.00000001 as "0.00000001"
8. THE Response_Formatter SHALL not use scientific notation for any numeric values

### Requirement 9: Error Handling and Validation

**User Story:** As an API consumer, I want clear error messages for invalid requests, so that I can correct my integration quickly

#### Acceptance Criteria

1. WHEN symbol parameter is missing, THE Orderbook_API SHALL return HTTP status 404
2. WHEN symbol parameter contains invalid characters, THE Orderbook_API SHALL return HTTP status 422
3. WHEN requested symbol does not have any orders, THE Orderbook_API SHALL return empty arrays with HTTP status 200
4. THE OrderBook_Controller SHALL validate symbol format before calling OrderBook_Service
5. THE OrderBook_Controller SHALL return JSON error responses with message field
6. WHEN an internal error occurs, THE Orderbook_API SHALL return HTTP status 500
7. THE OrderBook_Service SHALL log exceptions with context including symbol and depth parameters
8. THE Orderbook_API SHALL not expose sensitive error details in production environments

### Requirement 10: Performance Optimization

**User Story:** As a system operator, I want orderbook queries to execute efficiently, so that API response times remain acceptable under load

#### Acceptance Criteria

1. THE Order_Repository SHALL use database indexes for symbol and status columns
2. THE Order_Repository SHALL use database indexes for price and created_at columns
3. THE Aggregation_Service SHALL process aggregation in a single pass over the data
4. THE Aggregation_Service SHALL use array operations instead of multiple database queries
5. THE OrderBook_Service SHALL retrieve bids and asks in parallel where possible
6. THE Response_Formatter SHALL minimize memory allocations during array transformation
7. WHEN depth is 100 or less, THE Orderbook_API SHALL respond within 100 milliseconds under normal load
8. THE OrderBook_Service SHALL use lazy loading for Symbol_Configuration to avoid unnecessary queries

### Requirement 11: Data Consistency

**User Story:** As a trading system operator, I want orderbook data to reflect current order state, so that users see accurate market information

#### Acceptance Criteria

1. THE Order_Repository SHALL include only orders with status 'open' or 'partially_filled'
2. THE Order_Repository SHALL exclude orders with remaining_quantity equal to zero
3. THE Aggregation_Service SHALL use the remaining_quantity field for calculations, not the original quantity
4. THE OrderBook_Service SHALL retrieve orders within a database transaction
5. THE Orderbook_API SHALL reflect changes to orders within 1 second of order updates
6. THE Order_Repository SHALL sort bids by price descending then created_at ascending
7. THE Order_Repository SHALL sort asks by price ascending then created_at ascending
8. THE OrderBook_Service SHALL not cache orderbook data across requests

### Requirement 12: API Contract Compatibility

**User Story:** As an API consumer, I want the response structure documented and stable, so that my client code does not break unexpectedly

#### Acceptance Criteria

1. THE Orderbook_API SHALL maintain the root response structure with bids, asks, last_price, and spread fields
2. THE Orderbook_API SHALL return bids and asks as arrays of arrays, not objects
3. THE Orderbook_API SHALL use consistent field ordering: [price, quantity, total]
4. THE Orderbook_API SHALL set Content-Type header to application/json
5. THE Orderbook_API SHALL support CORS headers if configured
6. THE Orderbook_API SHALL version the endpoint path when making breaking changes
7. THE Orderbook_API SHALL document the response format in API documentation
8. THE Orderbook_API SHALL provide example responses for common scenarios
