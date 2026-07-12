<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KlineController extends Controller
{
    /**
     * Get klines (candlestick data) for a symbol.
     */
    public function index(Request $request, string $symbol): JsonResponse
    {
        $interval = $request->query('interval', '1m');

        // Get all klines in chronological order (oldest to newest)
        $klines = Kline::query()
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->orderBy('open_time', 'asc') // Chronological order: oldest to newest
            ->get()
            ->map(fn (Kline $kline) => $kline->toApiFormat());

        return response()->json($klines);
    }
}
