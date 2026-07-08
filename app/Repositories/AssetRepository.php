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
            ['amount' => '0', 'locked_amount' => '0']
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
     * Lock amount for trading.
     */
    public function lockAmount(Asset $asset, string $amount): void
    {
        $asset->lockAmount($amount);
    }

    /**
     * Unlock amount (e.g., when order is cancelled).
     */
    public function unlockAmount(Asset $asset, string $amount): void
    {
        $asset->unlockAmount($amount);
    }

    /**
     * Release locked amount after trade execution.
     */
    public function releaseLocked(Asset $asset, string $amount): void
    {
        $asset->releaseLocked($amount);
    }

    /**
     * Add amount to balance.
     */
    public function addAmount(Asset $asset, string $amount): void
    {
        $asset->addAmount($amount);
    }
}
