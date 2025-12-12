<?php

namespace App\Http\Controllers;

use App\Services\BalanceService;
use App\Services\AssetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    private BalanceService $balanceService;
    private AssetService $assetService;

    public function __construct(BalanceService $balanceService, AssetService $assetService)
    {
        $this->balanceService = $balanceService;
        $this->assetService = $assetService;
    }

    /**
     * Get user profile with balance and asset information.
     * 
     * Requirements: 1.1, 1.3, 8.4
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        try {
            $balanceData = $this->balanceService->getUserBalance($userId);
            $assetsData = $this->assetService->getUserAssets($userId);

            // Convert assets from object to array format
            $assetsArray = [];
            foreach ($assetsData as $symbol => $assetInfo) {
                $assetsArray[] = [
                    'symbol' => $symbol,
                    'amount' => $assetInfo['available_amount'] ?? $assetInfo['total_amount'],
                    'locked_amount' => $assetInfo['locked_amount'] ?? '0.00000000',
                    'total_amount' => $assetInfo['total_amount'] ?? '0.00000000',
                ];
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'balance' => $balanceData['usd_balance'], // Direct balance value
                'assets' => $assetsArray, // Array format
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve profile',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
