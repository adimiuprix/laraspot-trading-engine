<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientLiquidityException extends Exception
{
    protected $message = 'Insufficient liquidity in orderbook';

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'INSUFFICIENT_LIQUIDITY',
        ], 400);
    }
}
