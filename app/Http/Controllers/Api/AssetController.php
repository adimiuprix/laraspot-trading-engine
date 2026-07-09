<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetResource;
use App\Services\AssetService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(
        private AssetService $assetService
    ) {}

    /**
     * Get user's assets (balances).
     */
    public function index(Request $request)
    {
        $assets = $this->assetService->getUserAssets($request->user());

        return AssetResource::collection($assets);
    }

    /**
     * Get specific asset balance.
     */
    public function show(Request $request, string $symbol)
    {
        $asset = $this->assetService->getUserAsset($request->user(), $symbol);

        abort_if(! $asset, 404, 'Asset not found');

        return AssetResource::make($asset);
    }
}
