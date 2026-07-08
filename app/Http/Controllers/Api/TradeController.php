<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradeResource;
use App\Repositories\TradeRepository;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(
        private TradeRepository $tradeRepository
    ) {}

    /**
     * Get recent trades for a symbol.
     */
    public function index(Request $request, string $symbol)
    {
        $limit = $request->query('limit', 50);
        $trades = $this->tradeRepository->getRecentTrades($symbol, $limit);

        return TradeResource::collection($trades);
    }

    /**
     * Get user's trade history.
     */
    public function userTrades(Request $request)
    {
        $trades = $this->tradeRepository->getUserTrades($request->user()->id);

        return TradeResource::collection($trades);
    }
}
