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
