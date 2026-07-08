<?php

namespace App\Http\Controllers\Api;

use App\DTOs\OrderBookQueryDTO;
use App\Http\Controllers\Controller;
use App\Services\Trading\OrderBookService;
use Illuminate\Http\Request;

class OrderBookController extends Controller
{
    public function __construct(
        private OrderBookService $orderBookService
    ) {}

    /**
     * Get orderbook for a trading pair.
     */
    public function show(Request $request, string $symbol)
    {
        $depth = $request->query('depth', 20);
        $dto = OrderBookQueryDTO::fromRequest($symbol, $depth);

        $orderbook = $this->orderBookService->getOrderBook($dto);

        return response()->json($orderbook);
    }
}
