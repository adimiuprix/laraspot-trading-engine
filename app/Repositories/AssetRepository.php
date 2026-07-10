<?php

namespace App\Repositories;

use App\Models\Asset;
use App\Models\User;

class AssetRepository
{
    /**
     * Get user's asset by symbol.
     */
    public function getUserAsset(User $user, string $symbol): ?Asset
    {
        return $user->assets()->where('symbol', $symbol)->first();
    }

    /**
     * Get or create user's asset.
     */
    public function getOrCreateAsset(User $user, string $symbol): Asset
    {
        return $user->assets()->firstOrCreate(
            ['symbol' => $symbol],
            ['quantity' => '0', 'locked_quantity' => '0']
        );
    }

    /**
     * Get all user's assets.
     */
    public function getUserAssets(User $user)
    {
        return $user->assets()->get();
    }

    /**
     * Lock quantity for trading.
     */
    public function lockquantity(Asset $asset, string $quantity): void
    {
        $asset->lockquantity($quantity);
    }

    /**
     * Unlock quantity (e.g., when order is cancelled).
     */
    public function unlockquantity(Asset $asset, string $quantity): void
    {
        $asset->unlockquantity($quantity);
    }

    /**
     * Release locked quantity after trade execution.
     */
    public function releaseLocked(Asset $asset, string $quantity): void
    {
        $asset->releaseLocked($quantity);
    }

    /**
     * Add quantity to balance.
     */
    public function addquantity(Asset $asset, string $quantity): void
    {
        $asset->addquantity($quantity);
    }
}
