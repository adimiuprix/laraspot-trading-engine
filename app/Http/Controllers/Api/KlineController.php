<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KlineService;
use Illuminate\Http\Request;

class KlineController extends Controller
{
    public function __construct(
        private KlineService $klineService
    ) {}

    /**
     * Get kline/candlestick data for a symbol.
     *
     * GET /api/klines/{symbol}?interval=1m&startTime=1234567890&endTime=1234567999&limit=500
     *
     * Query Parameters:
     * - interval (required): 1m (currently only 1-minute interval is supported)
     * - startTime (optional): Start time in Unix timestamp (seconds)
     * - endTime (optional): End time in Unix timestamp (seconds)
     * - limit (optional): Number of klines to return (default: 500, max: 1000)
     *
     * Response Format (Binance-compatible):
     * [
     *   [
     *     1499040000,         // Open time (seconds)
     *     "0.01634790",       // Open
     *     "0.80000000",       // High
     *     "0.01575800",       // Low
     *     "0.01577100",       // Close
     *     "148976.11427815",  // Volume
     *     1499040059,         // Close time (seconds)
     *     "2434.19055334",    // Quote asset volume
     *     308,                // Number of trades
     *     "0.00000000",       // Taker buy base asset volume
     *     "0.00000000",       // Taker buy quote asset volume
     *     "0"                 // Ignore
     *   ]
     * ]
     */
    public function index(Request $request, string $symbol)
    {
        try {
            $validated = $request->validate([
                'interval'  => ['required', 'string', 'in:1m'],
                'startTime' => ['nullable', 'integer', 'min:0'],
                'endTime'   => ['nullable', 'integer', 'min:0'],
                'limit'     => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            return response()->json(
                $this->klineService
                    ->getKlines(
                        symbol: $symbol,
                        interval: $validated['interval'],
                        startTime: $validated['startTime'] ?? null,
                        endTime: $validated['endTime'] ?? null,
                        limit: $validated['limit'] ?? 500,
                    )
                    ->map(fn ($kline) => $kline->toApiFormat())
                    ->values()
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => -1100,
                'msg'  => $e->validator->errors()->first(),
            ], 400);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'code' => -1121,
                'msg'  => $e->getMessage(),
            ], 400);
        } catch (\Throwable) {
            return response()->json([
                'code' => -1000,
                'msg'  => 'An unknown error occurred',
            ], 500);
        }
    }
}
