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
        try {
            $validated = $request->validate([
                'interval'  => ['required', 'string', 'in:1m'],
                'startTime' => ['nullable', 'integer', 'min:0'],
                'endTime'   => ['nullable', 'integer', 'min:0'],
                'limit'     => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $query = Kline::query()
                ->where('symbol', $symbol)
                ->where('interval', $validated['interval'])
                ->orderBy('open_time', 'asc');

            if (isset($validated['startTime'])) {
                $query->where('open_time', '>=', $validated['startTime']);
            }

            if (isset($validated['endTime'])) {
                $query->where('open_time', '<=', $validated['endTime']);
            }

            $limit = $validated['limit'] ?? 500;
            $klines = $query->limit($limit)->get();

            // Remove duplicates by open_time (keep the most recent one based on updated_at)
            $uniqueKlines = $klines->groupBy('open_time')
                ->map(fn ($group) => $group->sortByDesc('updated_at')->first())
                ->sortBy('open_time')
                ->values();

            $formattedKlines = $uniqueKlines->map(fn ($kline) => $kline->toApiFormat())->toArray();

            return response()->json($formattedKlines);
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
