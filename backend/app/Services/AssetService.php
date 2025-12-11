<?php

namespace App\Services;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetService
{
    /**
     * Get user's asset balances with available and locked amounts.
     */
    public function getUserAssets(int $userId): array
    {
        $user = User::find($userId);
        
        if (!$user) {
            throw ValidationException::withMessages([
                'user' => 'User not found.'
            ]);
        }

        $assets = $user->assets()->get();
        
        $result = [];
        foreach ($assets as $asset) {
            $result[$asset->symbol] = [
                'total_amount' => $asset->amount,
                'locked_amount' => $asset->locked_amount,
                'available_amount' => $asset->available_amount,
            ];
        }

        return $result;
    }

    /**
     * Get or create asset for user.
     */
    public function getOrCreateAsset(int $userId, string $symbol): Asset
    {
        // Validate symbol
        Asset::validateSymbol($symbol);

        return Asset::firstOrCreate(
            ['user_id' => $userId, 'symbol' => strtoupper($symbol)],
            ['amount' => '0.00000000', 'locked_amount' => '0.00000000']
        );
    }

    /**
     * Lock assets for a sell order.
     */
    public function lockAssets(int $userId, string $symbol, string $amount): bool
    {
        return DB::transaction(function () use ($userId, $symbol, $amount) {
            $asset = Asset::lockForUpdate()
                ->where('user_id', $userId)
                ->where('symbol', strtoupper($symbol))
                ->first();

            if (!$asset) {
                throw ValidationException::withMessages([
                    'asset' => 'Asset not found for user.'
                ]);
            }

            // Validate amount
            Asset::validateAmount($amount);

            // Check if user has sufficient available assets
            if (!$asset->hasSufficientAmount($amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient available assets.'
                ]);
            }

            // Lock the assets
            $newLockedAmount = bcadd($asset->locked_amount, $amount, 8);
            $asset->update(['locked_amount' => $newLockedAmount]);

            return true;
        });
    }

    /**
     * Release locked assets (unlock them).
     */
    public function releaseAssets(int $userId, string $symbol, string $amount): bool
    {
        return DB::transaction(function () use ($userId, $symbol, $amount) {
            $asset = Asset::lockForUpdate()
                ->where('user_id', $userId)
                ->where('symbol', strtoupper($symbol))
                ->first();

            if (!$asset) {
                throw ValidationException::withMessages([
                    'asset' => 'Asset not found for user.'
                ]);
            }

            // Validate amount
            Asset::validateAmount($amount);

            // Check if there are sufficient locked assets to release
            if (bccomp($asset->locked_amount, $amount, 8) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Cannot release more assets than are locked.'
                ]);
            }

            // Release the assets
            $newLockedAmount = bcsub($asset->locked_amount, $amount, 8);
            $asset->update(['locked_amount' => $newLockedAmount]);

            return true;
        });
    }

    /**
     * Transfer assets between users.
     */
    public function transferAssets(int $fromUserId, int $toUserId, string $symbol, string $amount): bool
    {
        return DB::transaction(function () use ($fromUserId, $toUserId, $symbol, $amount) {
            // Validate inputs
            Asset::validateSymbol($symbol);
            Asset::validateAmount($amount);

            $symbol = strtoupper($symbol);

            // Get or create assets for both users, locking in consistent order
            $userIds = [$fromUserId, $toUserId];
            sort($userIds);

            $fromAsset = Asset::lockForUpdate()
                ->where('user_id', $fromUserId)
                ->where('symbol', $symbol)
                ->first();

            $toAsset = $this->getOrCreateAsset($toUserId, $symbol);
            $toAsset = Asset::lockForUpdate()->find($toAsset->id);

            if (!$fromAsset) {
                throw ValidationException::withMessages([
                    'asset' => 'Sender does not have the specified asset.'
                ]);
            }

            // Check if sender has sufficient locked assets (assuming transfer from locked amount)
            if (bccomp($fromAsset->locked_amount, $amount, 8) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient locked assets for transfer.'
                ]);
            }

            // Perform transfer: remove from sender's locked amount and total, add to receiver's total
            $fromAsset->update([
                'amount' => bcsub($fromAsset->amount, $amount, 8),
                'locked_amount' => bcsub($fromAsset->locked_amount, $amount, 8)
            ]);

            $toAsset->update([
                'amount' => bcadd($toAsset->amount, $amount, 8)
            ]);

            return true;
        });
    }

    /**
     * Validate if user has sufficient assets for an order.
     */
    public function validateSufficientAssets(int $userId, string $symbol, string $amount): bool
    {
        $asset = Asset::where('user_id', $userId)
            ->where('symbol', strtoupper($symbol))
            ->first();

        if (!$asset) {
            return false;
        }

        return $asset->hasSufficientAmount($amount);
    }

    /**
     * Add assets to user (for initial funding or rewards).
     */
    public function addAssets(int $userId, string $symbol, string $amount): bool
    {
        return DB::transaction(function () use ($userId, $symbol, $amount) {
            // Validate inputs
            Asset::validateSymbol($symbol);
            Asset::validateAmount($amount);

            $asset = $this->getOrCreateAsset($userId, $symbol);
            $asset = Asset::lockForUpdate()->find($asset->id);

            // Add to total amount
            $newAmount = bcadd($asset->amount, $amount, 8);
            $asset->update(['amount' => $newAmount]);

            return true;
        });
    }
}