<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\KlineController;
use App\Http\Controllers\Api\OrderBookController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TradeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:web')->group(function () {
    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::delete('/orders/{orderId}', [OrderController::class, 'destroy']);

    // Assets (Balance)
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{symbol}', [AssetController::class, 'show']);

    // User Trades
    Route::get('/trades/user', [TradeController::class, 'userTrades']);
});

// Public endpoints (no auth required)
Route::get('/orderbook/{symbol}', [OrderBookController::class, 'show'])->where('symbol', '.*');
Route::get('/trades/{symbol}', [TradeController::class, 'index'])->where('symbol', '.*');
Route::get('/klines/{symbol}', [KlineController::class, 'index'])->where('symbol', '.*');
