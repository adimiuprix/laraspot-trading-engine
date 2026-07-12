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
     *
     * @param  string  $symbol
     * @return JsonResponse
     */
    public function index(Request $request, string $symbol): JsonResponse
    {
        $interval = $request->query('interval', '1m');
        $limit = $request->query('limit', 100);

        $klines = Kline::query()
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->orderBy('open_time', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Kline $kline) => $kline->toApiFormat());

        return response()->json($klines);
    }

    /**
     * Get latest kline for a symbol.
     *
     * @param  string  $symbol
     * @return JsonResponse
     */
    public function latest(Request $request, string $symbol): JsonResponse
    {
        $interval = $request->query('interval', '1m');

        $kline = Kline::query()
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->orderBy('open_time', 'desc')
            ->first();

        if (! $kline) {
            return response()->json([
                'message' => 'No kline data found',
            ], 404);
        }

        return response()->json($kline->toApiFormat());
    }
}
