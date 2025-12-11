<?php

namespace App\Services;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BalanceService
{
    /**
     * Get user's USD balance with available and locked amounts.
     */
    public function getUserBalance(int $userId): array
    {
        $user = User::lockForUpdate()->find($userId);
        
        if (!$user) {
            throw ValidationException::withMessages([
                'user' => 'User not found.'
            ]);
        }

        return [
            'usd_balance' => $user->balance,
            'available_usd' => $user->balance, // For now, assuming no locked USD tracking in user table
        ];
    }

    /**
     * Lock USD funds for a buy order.
     */
    public function lockFunds(int $userId, string $amount): bool
    {
        return DB::transaction(function () use ($userId, $amount) {
            $user = User::lockForUpdate()->find($userId);
            
            if (!$user) {
                throw ValidationException::withMessages([
                    'user' => 'User not found.'
                ]);
            }

            // Validate amount
            User::validateBalance($amount);

            // Check if user has sufficient balance
            if (!$user->hasSufficientBalance($amount)) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient USD balance.'
                ]);
            }

            // Deduct from balance (this represents locking the funds)
            $newBalance = bcsub($user->balance, $amount, 8);
            $user->update(['balance' => $newBalance]);

            return true;
        });
    }

    /**
     * Release locked USD funds (add back to balance).
     */
    public function releaseFunds(int $userId, string $amount): bool
    {
        return DB::transaction(function () use ($userId, $amount) {
            $user = User::lockForUpdate()->find($userId);
            
            if (!$user) {
                throw ValidationException::withMessages([
                    'user' => 'User not found.'
                ]);
            }

            // Validate amount
            User::validateBalance($amount);

            // Add back to balance
            $newBalance = bcadd($user->balance, $amount, 8);
            $user->update(['balance' => $newBalance]);

            return true;
        });
    }

    /**
     * Transfer USD between users.
     */
    public function transferUSD(int $fromUserId, int $toUserId, string $amount): bool
    {
        return DB::transaction(function () use ($fromUserId, $toUserId, $amount) {
            // Lock users in consistent order to prevent deadlocks
            $userIds = [$fromUserId, $toUserId];
            sort($userIds);
            
            $users = User::lockForUpdate()->whereIn('id', $userIds)->get()->keyBy('id');
            
            $fromUser = $users->get($fromUserId);
            $toUser = $users->get($toUserId);

            if (!$fromUser || !$toUser) {
                throw ValidationException::withMessages([
                    'user' => 'One or both users not found.'
                ]);
            }

            // Validate amount
            User::validateBalance($amount);

            // Check if sender has sufficient balance
            if (!$fromUser->hasSufficientBalance($amount)) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient USD balance for transfer.'
                ]);
            }

            // Perform transfer
            $fromUser->update(['balance' => bcsub($fromUser->balance, $amount, 8)]);
            $toUser->update(['balance' => bcadd($toUser->balance, $amount, 8)]);

            return true;
        });
    }

    /**
     * Validate if user has sufficient balance for an order.
     */
    public function validateSufficientBalance(int $userId, string $amount): bool
    {
        $user = User::find($userId);
        
        if (!$user) {
            throw ValidationException::withMessages([
                'user' => 'User not found.'
            ]);
        }

        return $user->hasSufficientBalance($amount);
    }

    /**
     * Deduct commission from user balance.
     */
    public function deductCommission(int $userId, string $commissionAmount): bool
    {
        return DB::transaction(function () use ($userId, $commissionAmount) {
            $user = User::lockForUpdate()->find($userId);
            
            if (!$user) {
                throw ValidationException::withMessages([
                    'user' => 'User not found.'
                ]);
            }

            // Validate commission amount
            User::validateBalance($commissionAmount);

            // Check if user has sufficient balance for commission
            if (!$user->hasSufficientBalance($commissionAmount)) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance for commission deduction.'
                ]);
            }

            // Deduct commission
            $newBalance = bcsub($user->balance, $commissionAmount, 8);
            $user->update(['balance' => $newBalance]);

            return true;
        });
    }
}