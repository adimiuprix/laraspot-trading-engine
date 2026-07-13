<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Trading\KlineService;

class YussController extends Controller
{
    public function index(KlineService $klineService)
    {
        $klineService->generateNextKline('BTCUSDT', '1m');
    }
}
