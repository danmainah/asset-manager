<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Asset;
use App\Services\BalanceService;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceDisplayAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private BalanceService $balanceService;
    private AssetService $assetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->balanceService = new BalanceService();
        $this->assetService = new AssetService();
    }

    /**
     * **Feature: asset-manager, Property 1: Balance Display Accuracy**
     * 
     * For any user with USD and asset balances, the profile endpoint should return exactly 
     * the current balance values including both available and locked amounts for each asset.
     * 
     * **Validates: Requirements 1.1, 1.3**
     */
    public function test_balance_display_accuracy_property()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random user with random balances
            $userData = $this->generateRandomUserData();
            $assetData = $this->generateRandomAssetData();
            
            $user = User::factory()->create([
                'balance' => $userData['balance']
            ]);
            
            // Create random assets for the user
            $expectedAssets = [];
            foreach ($assetData as $symbol => $amounts) {
                $asset = Asset::create([
                    'user_id' => $user->id,
                    'symbol' => $symbol,
                    'amount' => $amounts['total'],
                    'locked_amount' => $amounts['locked']
                ]);
                
                $expectedAssets[$symbol] = [
                    'total_amount' => $amounts['total'],
                    'locked_amount' => $amounts['locked'],
                    'available_amount' => bcsub($amounts['total'], $amounts['locked'], 8)
                ];
            }
            
            // Test balance service accuracy
            $balanceResult = $this->balanceService->getUserBalance($user->id);
            $assetResult = $this->assetService->getUserAssets($user->id);
            
            $this->assertBalanceDisplayAccuracy($user, $balanceResult, $assetResult, $expectedAssets);
        }
    }

    /**
     * Test balance display accuracy for edge cases
     */
    public function test_balance_display_accuracy_edge_cases()
    {
        // Test user with zero balance
        $user = User::factory()->create(['balance' => '0.00000000']);
        
        $balanceResult = $this->balanceService->getUserBalance($user->id);
        $assetResult = $this->assetService->getUserAssets($user->id);
        
        $this->assertEquals('0.00000000', $balanceResult['usd_balance']);
        $this->assertEquals('0.00000000', $balanceResult['available_usd']);
        $this->assertEmpty($assetResult);
        
        // Test user with maximum precision balance
        $maxPrecisionBalance = '999999.99999999';
        $user2 = User::factory()->create(['balance' => $maxPrecisionBalance]);
        
        $balanceResult2 = $this->balanceService->getUserBalance($user2->id);
        
        $this->assertEquals($maxPrecisionBalance, $balanceResult2['usd_balance']);
        
        // Test user with assets having all amounts locked
        $user3 = User::factory()->create(['balance' => '1000.00000000']);
        $asset = Asset::create([
            'user_id' => $user3->id,
            'symbol' => 'BTC',
            'amount' => '1.00000000',
            'locked_amount' => '1.00000000'
        ]);
        
        $assetResult3 = $this->assetService->getUserAssets($user3->id);
        
        $this->assertEquals('1.00000000', $assetResult3['BTC']['total_amount']);
        $this->assertEquals('1.00000000', $assetResult3['BTC']['locked_amount']);
        $this->assertEquals('0.00000000', $assetResult3['BTC']['available_amount']);
    }

    /**
     * Test that balance display reflects real-time changes
     */
    public function test_balance_display_reflects_changes()
    {
        $user = User::factory()->create(['balance' => '1000.00000000']);
        
        // Initial balance check
        $initialBalance = $this->balanceService->getUserBalance($user->id);
        $this->assertEquals('1000.00000000', $initialBalance['usd_balance']);
        
        // Modify balance and check again
        $user->update(['balance' => '500.50000000']);
        
        $updatedBalance = $this->balanceService->getUserBalance($user->id);
        $this->assertEquals('500.50000000', $updatedBalance['usd_balance']);
        
        // Test asset changes
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'ETH',
            'amount' => '10.00000000',
            'locked_amount' => '2.00000000'
        ]);
        
        $assetResult = $this->assetService->getUserAssets($user->id);
        $this->assertEquals('10.00000000', $assetResult['ETH']['total_amount']);
        $this->assertEquals('2.00000000', $assetResult['ETH']['locked_amount']);
        $this->assertEquals('8.00000000', $assetResult['ETH']['available_amount']);
        
        // Update asset and verify changes
        $asset->update(['locked_amount' => '5.00000000']);
        
        $updatedAssetResult = $this->assetService->getUserAssets($user->id);
        $this->assertEquals('5.00000000', $updatedAssetResult['ETH']['locked_amount']);
        $this->assertEquals('5.00000000', $updatedAssetResult['ETH']['available_amount']);
    }

    private function assertBalanceDisplayAccuracy(User $user, array $balanceResult, array $assetResult, array $expectedAssets): void
    {
        // Verify USD balance accuracy
        $this->assertEquals(
            number_format($user->balance, 8, '.', ''),
            number_format($balanceResult['usd_balance'], 8, '.', ''),
            "USD balance display not accurate for user {$user->id}"
        );
        
        $this->assertEquals(
            number_format($user->balance, 8, '.', ''),
            number_format($balanceResult['available_usd'], 8, '.', ''),
            "Available USD balance display not accurate for user {$user->id}"
        );
        
        // Verify asset balance accuracy
        foreach ($expectedAssets as $symbol => $expected) {
            $this->assertArrayHasKey($symbol, $assetResult, "Asset {$symbol} missing from result");
            
            $actual = $assetResult[$symbol];
            
            $this->assertEquals(
                number_format($expected['total_amount'], 8, '.', ''),
                number_format($actual['total_amount'], 8, '.', ''),
                "Total amount not accurate for asset {$symbol}"
            );
            
            $this->assertEquals(
                number_format($expected['locked_amount'], 8, '.', ''),
                number_format($actual['locked_amount'], 8, '.', ''),
                "Locked amount not accurate for asset {$symbol}"
            );
            
            $this->assertEquals(
                number_format($expected['available_amount'], 8, '.', ''),
                number_format($actual['available_amount'], 8, '.', ''),
                "Available amount not accurate for asset {$symbol}"
            );
        }
        
        // Verify no extra assets are returned
        $this->assertCount(count($expectedAssets), $assetResult, "Extra assets returned in result");
    }

    private function generateRandomUserData(): array
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(0, 999999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(0, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        $balance = $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
        
        return [
            'balance' => $balance
        ];
    }

    private function generateRandomAssetData(): array
    {
        $symbols = ['BTC', 'ETH'];
        $assets = [];
        
        // Randomly decide how many assets to create (0-2)
        $assetCount = rand(0, 2);
        $selectedSymbols = array_slice($symbols, 0, $assetCount);
        
        foreach ($selectedSymbols as $symbol) {
            $totalAmount = $this->generateValidAmount();
            $lockedAmount = $this->generateValidAmount();
            
            // Ensure locked amount doesn't exceed total amount
            if (bccomp($lockedAmount, $totalAmount, 8) > 0) {
                $lockedAmount = $totalAmount;
            }
            
            $assets[$symbol] = [
                'total' => $totalAmount,
                'locked' => $lockedAmount
            ];
        }
        
        return $assets;
    }

    private function generateValidAmount(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(0, 999999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(0, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }
}