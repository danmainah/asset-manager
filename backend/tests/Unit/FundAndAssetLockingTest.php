<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Asset;
use App\Services\BalanceService;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FundAndAssetLockingTest extends TestCase
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
     * **Feature: asset-manager, Property 2: Fund and Asset Locking**
     * 
     * For any valid order (buy or sell), placing the order should lock the exact required 
     * funds or assets and prevent their use in other orders.
     * 
     * **Validates: Requirements 2.1, 2.2**
     */
    public function test_fund_and_asset_locking_property()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test fund locking for buy orders
            $this->assertFundLockingBehavior();
            
            // Test asset locking for sell orders
            $this->assertAssetLockingBehavior();
        }
    }

    /**
     * Test fund locking prevents double spending
     */
    public function test_fund_locking_prevents_double_spending()
    {
        $user = User::factory()->create(['balance' => '1000.00000000']);
        
        // Lock funds for first order
        $lockAmount1 = '600.00000000';
        $this->assertTrue($this->balanceService->lockFunds($user->id, $lockAmount1));
        
        // Verify balance is reduced
        $user->refresh();
        $this->assertEquals('400.00000000', $user->balance);
        
        // Try to lock more funds than available - should fail
        $lockAmount2 = '500.00000000';
        
        $this->expectException(ValidationException::class);
        $this->balanceService->lockFunds($user->id, $lockAmount2);
    }

    /**
     * Test asset locking prevents double spending
     */
    public function test_asset_locking_prevents_double_spending()
    {
        $user = User::factory()->create();
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Lock assets for first order
        $lockAmount1 = '6.00000000';
        $this->assertTrue($this->assetService->lockAssets($user->id, 'BTC', $lockAmount1));
        
        // Verify locked amount is updated
        $asset->refresh();
        $this->assertEquals('6.00000000', $asset->locked_amount);
        $this->assertEquals('4.00000000', $asset->available_amount);
        
        // Try to lock more assets than available - should fail
        $lockAmount2 = '5.00000000';
        
        $this->expectException(ValidationException::class);
        $this->assetService->lockAssets($user->id, 'BTC', $lockAmount2);
    }

    /**
     * Test fund release functionality
     */
    public function test_fund_release_functionality()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $initialBalance = $this->generateValidAmount();
            $lockAmount = $this->generateSmallerAmount($initialBalance);
            
            $user = User::factory()->create(['balance' => $initialBalance]);
            
            // Lock funds
            $this->assertTrue($this->balanceService->lockFunds($user->id, $lockAmount));
            
            $expectedBalanceAfterLock = bcsub($initialBalance, $lockAmount, 8);
            $user->refresh();
            $this->assertEquals($expectedBalanceAfterLock, $user->balance);
            
            // Release funds
            $this->assertTrue($this->balanceService->releaseFunds($user->id, $lockAmount));
            
            $user->refresh();
            $this->assertEquals(
                number_format($initialBalance, 8, '.', ''),
                number_format($user->balance, 8, '.', '')
            );
        }
    }

    /**
     * Test asset release functionality
     */
    public function test_asset_release_functionality()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $totalAmount = $this->generateValidAmount();
            $lockAmount = $this->generateSmallerAmount($totalAmount);
            
            $user = User::factory()->create();
            $asset = Asset::create([
                'user_id' => $user->id,
                'symbol' => 'ETH',
                'amount' => $totalAmount,
                'locked_amount' => '0.00000000'
            ]);
            
            // Lock assets
            $this->assertTrue($this->assetService->lockAssets($user->id, 'ETH', $lockAmount));
            
            $asset->refresh();
            $this->assertEquals($lockAmount, $asset->locked_amount);
            $expectedAvailable = bcsub($totalAmount, $lockAmount, 8);
            $this->assertEquals($expectedAvailable, $asset->available_amount);
            
            // Release assets
            $this->assertTrue($this->assetService->releaseAssets($user->id, 'ETH', $lockAmount));
            
            $asset->refresh();
            $this->assertEquals('0.00000000', $asset->locked_amount);
            $this->assertEquals(
                number_format($totalAmount, 8, '.', ''),
                number_format($asset->available_amount, 8, '.', '')
            );
        }
    }

    /**
     * Test partial locking and releasing
     */
    public function test_partial_locking_and_releasing()
    {
        // Test partial fund locking
        $user = User::factory()->create(['balance' => '1000.00000000']);
        
        // Lock funds in multiple steps
        $this->assertTrue($this->balanceService->lockFunds($user->id, '300.00000000'));
        $this->assertTrue($this->balanceService->lockFunds($user->id, '200.00000000'));
        
        $user->refresh();
        $this->assertEquals('500.00000000', $user->balance);
        
        // Release funds partially
        $this->assertTrue($this->balanceService->releaseFunds($user->id, '150.00000000'));
        
        $user->refresh();
        $this->assertEquals('650.00000000', $user->balance);
        
        // Test partial asset locking
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '5.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Lock assets in multiple steps
        $this->assertTrue($this->assetService->lockAssets($user->id, 'BTC', '2.00000000'));
        $this->assertTrue($this->assetService->lockAssets($user->id, 'BTC', '1.50000000'));
        
        $asset->refresh();
        $this->assertEquals('3.50000000', $asset->locked_amount);
        $this->assertEquals('1.50000000', $asset->available_amount);
        
        // Release assets partially
        $this->assertTrue($this->assetService->releaseAssets($user->id, 'BTC', '1.00000000'));
        
        $asset->refresh();
        $this->assertEquals('2.50000000', $asset->locked_amount);
        $this->assertEquals('2.50000000', $asset->available_amount);
    }

    private function assertFundLockingBehavior(): void
    {
        $initialBalance = $this->generateValidAmount();
        $lockAmount = $this->generateSmallerAmount($initialBalance);
        
        $user = User::factory()->create(['balance' => $initialBalance]);
        
        // Verify initial state
        $this->assertTrue($this->balanceService->validateSufficientBalance($user->id, $lockAmount));
        
        // Lock funds
        $this->assertTrue($this->balanceService->lockFunds($user->id, $lockAmount));
        
        // Verify funds are locked (balance reduced)
        $user->refresh();
        $expectedBalance = bcsub($initialBalance, $lockAmount, 8);
        $this->assertEquals(
            number_format($expectedBalance, 8, '.', ''),
            number_format($user->balance, 8, '.', ''),
            "Fund locking did not reduce balance correctly"
        );
        
        // Verify locked funds cannot be used again
        $remainingBalance = $user->balance;
        if (bccomp($remainingBalance, '0', 8) > 0) {
            $additionalLockAmount = bcadd($remainingBalance, '0.00000001', 8);
            
            try {
                $this->balanceService->lockFunds($user->id, $additionalLockAmount);
                $this->fail("Should not be able to lock more funds than available");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Insufficient', $e->getMessage());
            }
        }
    }

    private function assertAssetLockingBehavior(): void
    {
        $totalAmount = $this->generateValidAmount();
        $lockAmount = $this->generateSmallerAmount($totalAmount);
        
        $user = User::factory()->create();
        $symbols = ['BTC', 'ETH'];
        $symbol = $symbols[array_rand($symbols)];
        
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'amount' => $totalAmount,
            'locked_amount' => '0.00000000'
        ]);
        
        // Verify initial state
        $this->assertTrue($this->assetService->validateSufficientAssets($user->id, $symbol, $lockAmount));
        
        // Lock assets
        $this->assertTrue($this->assetService->lockAssets($user->id, $symbol, $lockAmount));
        
        // Verify assets are locked
        $asset->refresh();
        $this->assertEquals(
            number_format($lockAmount, 8, '.', ''),
            number_format($asset->locked_amount, 8, '.', ''),
            "Asset locking did not update locked_amount correctly"
        );
        
        $expectedAvailable = bcsub($totalAmount, $lockAmount, 8);
        $this->assertEquals(
            number_format($expectedAvailable, 8, '.', ''),
            number_format($asset->available_amount, 8, '.', ''),
            "Asset locking did not calculate available_amount correctly"
        );
        
        // Verify locked assets cannot be used again
        $availableAmount = $asset->available_amount;
        if (bccomp($availableAmount, '0', 8) > 0) {
            $additionalLockAmount = bcadd($availableAmount, '0.00000001', 8);
            
            try {
                $this->assetService->lockAssets($user->id, $symbol, $additionalLockAmount);
                $this->fail("Should not be able to lock more assets than available");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Insufficient', $e->getMessage());
            }
        }
    }

    private function generateValidAmount(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(1, 999999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(1, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }

    private function generateSmallerAmount(string $maxAmount): string
    {
        // Generate an amount that's 10-90% of the max amount
        $percentage = rand(10, 90) / 100;
        $result = bcmul($maxAmount, (string)$percentage, 8);
        
        // Ensure it's not zero
        return bccomp($result, '0', 8) > 0 ? $result : '0.00000001';
    }
}