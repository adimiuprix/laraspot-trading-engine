<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ExcessiveSlippageException extends Exception
{
    protected $message = 'Order would cause excessive slippage';

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'EXCESSIVE_SLIPPAGE',
            'suggestion' => 'Consider using a limit order or reducing order size',
        ], 400);
    }
}
