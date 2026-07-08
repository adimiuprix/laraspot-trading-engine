<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use App\Repositories\AssetRepository;

class AssetService
{
    public function __construct(
        private AssetRepository $assetRepository
    ) {}

    /**
     * Get user's asset balance.
     */
    public function getUserAsset(User $user, string $symbol): ?Asset
    {
        return $this->assetRepository->getUserAsset($user, $symbol);
    }

    /**
     * Get or create user's asset.
     */
    public function getOrCreateAsset(User $user, string $symbol): Asset
    {
        return $this->assetRepository->getOrCreateAsset($user, $symbol);
    }

    /**
     * Check if user has sufficient balance.
     */
    public function hasSufficientBalance(User $user, string $symbol, string $amount): bool
    {
        $asset = $this->getUserAsset($user, $symbol);

        if (! $asset) {
            return false;
        }

        return $asset->hasSufficientBalance($amount);
    }

    /**
     * Lock amount for order.
     */
    public function lockAmount(User $user, string $symbol, string $amount): void
    {
        $asset = $this->getOrCreateAsset($user, $symbol);
        $this->assetRepository->lockAmount($asset, $amount);
    }

    /**
     * Unlock amount (e.g., when order is cancelled).
     */
    public function unlockAmount(User $user, string $symbol, string $amount): void
    {
        $asset = $this->getUserAsset($user, $symbol);

        if ($asset) {
            $this->assetRepository->unlockAmount($asset, $amount);
        }
    }

    /**
     * Release locked amount and deduct from locked balance.
     */
    public function releaseLocked(User $user, string $symbol, string $amount): void
    {
        $asset = $this->getUserAsset($user, $symbol);

        if ($asset) {
            $this->assetRepository->releaseLocked($asset, $amount);
        }
    }

    /**
     * Add amount to user balance.
     */
    public function addAmount(User $user, string $symbol, string $amount): void
    {
        $asset = $this->getOrCreateAsset($user, $symbol);
        $this->assetRepository->addAmount($asset, $amount);
    }

    /**
     * Get all user assets.
     */
    public function getUserAssets(User $user)
    {
        return $this->assetRepository->getUserAssets($user);
    }
}
