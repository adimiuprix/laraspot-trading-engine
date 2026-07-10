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
    public function hasSufficientBalance(User $user, string $symbol, string $quantity): bool
    {
        $asset = $this->getUserAsset($user, $symbol);

        if (! $asset) {
            return false;
        }

        return $asset->hasSufficientBalance($quantity);
    }

    /**
     * Lock quantity for order.
     */
    public function lockquantity(User $user, string $symbol, string $quantity): void
    {
        $asset = $this->getOrCreateAsset($user, $symbol);
        $this->assetRepository->lockquantity($asset, $quantity);
    }

    /**
     * Unlock quantity (e.g., when order is cancelled).
     */
    public function unlockquantity(User $user, string $symbol, string $quantity): void
    {
        $asset = $this->getUserAsset($user, $symbol);

        if ($asset) {
            $this->assetRepository->unlockquantity($asset, $quantity);
        }
    }

    /**
     * Release locked quantity and deduct from locked balance.
     */
    public function releaseLocked(User $user, string $symbol, string $quantity): void
    {
        $asset = $this->getUserAsset($user, $symbol);

        if ($asset) {
            $this->assetRepository->releaseLocked($asset, $quantity);
        }
    }

    /**
     * Add quantity to user balance.
     */
    public function addquantity(User $user, string $symbol, string $quantity): void
    {
        $asset = $this->getOrCreateAsset($user, $symbol);
        $this->assetRepository->addquantity($asset, $quantity);
    }

    /**
     * Get all user assets.
     */
    public function getUserAssets(User $user)
    {
        return $this->assetRepository->getUserAssets($user);
    }
}
