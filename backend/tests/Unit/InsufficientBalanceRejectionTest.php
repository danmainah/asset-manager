<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Asset;
use App\Services\BalanceService;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InsufficientBalanceRejectionTest extends TestCase
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
     * **Feature: asset-manager, Property 4: Insufficient Balance Rejection**
     * 
     * For any order that exceeds available user funds or assets, the system should reject 
     * the order and leave balances unchanged.
     * 
     * **Validates: Requirements 1.4, 2.4**
     */
    public function test_insufficient_balance_rejection_property()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test insufficient USD balance rejection
            $this->assertInsufficientUSDBalanceRejection();
            
            // Test insufficient asset balance rejection
            $this->assertInsufficientAssetBalanceRejection();
        }
    }

    /**
     * Test edge cases for insufficient balance rejection
     */
    public function test_insufficient_balance_edge_cases()
    {
        // Test zero balance scenarios
        $user = User::factory()->create(['balance' => '0.00000000']);
        
        $originalBalance = $user->balance;
        
        try {
            $this->balanceService->lockFunds($user->id, '0.00000001');
            $this->fail('Should reject locking funds when balance is zero');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }
        
        // Verify balance unchanged
        $user->refresh();
        $this->assertEquals($originalBalance, $user->balance);
        
        // Test zero asset scenarios
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '0.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        $originalAmount = $asset->amount;
        $originalLocked = $asset->locked_amount;
        
        try {
            $this->assetService->lockAssets($user->id, 'BTC', '0.00000001');
            $this->fail('Should reject locking assets when amount is zero');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }
        
        // Verify asset unchanged
        $asset->refresh();
        $this->assertEquals($originalAmount, $asset->amount);
        $this->assertEquals($originalLocked, $asset->locked_amount);
    }

    /**
     * Test that partial available balance is handled correctly
     */
    public function test_partial_available_balance_scenarios()
    {
        // Test when some funds are already locked
        $user = User::factory()->create(['balance' => '1000.00000000']);
        
        // Lock some funds first
        $this->assertTrue($this->balanceService->lockFunds($user->id, '600.00000000'));
        
        $user->refresh();
        $remainingBalance = $user->balance; // Should be 400
        
        // Try to lock more than remaining balance
        try {
            $this->balanceService->lockFunds($user->id, bcadd($remainingBalance, '0.00000001', 8));
            $this->fail('Should reject locking more funds than available');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }
        
        // Verify balance unchanged after failed attempt
        $balanceAfterFailedAttempt = $user->fresh()->balance;
        $this->assertEquals($remainingBalance, $balanceAfterFailedAttempt);
        
        // Test similar scenario with assets
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'ETH',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Lock some assets first
        $this->assertTrue($this->assetService->lockAssets($user->id, 'ETH', '6.00000000'));
        
        $asset->refresh();
        $availableAmount = $asset->available_amount; // Should be 4
        
        // Try to lock more than available
        try {
            $this->assetService->lockAssets($user->id, 'ETH', bcadd($availableAmount, '0.00000001', 8));
            $this->fail('Should reject locking more assets than available');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }
        
        // Verify asset state unchanged after failed attempt
        $assetAfterFailedAttempt = $asset->fresh();
        $this->assertEquals('10.00000000', $assetAfterFailedAttempt->amount);
        $this->assertEquals('6.00000000', $assetAfterFailedAttempt->locked_amount);
        $this->assertEquals($availableAmount, $assetAfterFailedAttempt->available_amount);
    }

    /**
     * Test validation methods correctly identify insufficient balances
     */
    public function test_validation_methods_accuracy()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $balance = $this->generateValidAmount();
            $user = User::factory()->create(['balance' => $balance]);
            
            // Test amounts less than balance (should pass validation)
            $smallerAmount = $this->generateSmallerAmount($balance);
            $this->assertTrue($this->balanceService->validateSufficientBalance($user->id, $smallerAmount));
            
            // Test amounts greater than balance (should fail validation)
            $largerAmount = bcadd($balance, '0.00000001', 8);
            $this->assertFalse($this->balanceService->validateSufficientBalance($user->id, $largerAmount));
            
            // Test asset validation
            $assetAmount = $this->generateValidAmount();
            $asset = Asset::create([
                'user_id' => $user->id,
                'symbol' => 'BTC',
                'amount' => $assetAmount,
                'locked_amount' => '0.00000000'
            ]);
            
            // Test amounts less than asset amount (should pass validation)
            $smallerAssetAmount = $this->generateSmallerAmount($assetAmount);
            $this->assertTrue($this->assetService->validateSufficientAssets($user->id, 'BTC', $smallerAssetAmount));
            
            // Test amounts greater than asset amount (should fail validation)
            $largerAssetAmount = bcadd($assetAmount, '0.00000001', 8);
            $this->assertFalse($this->assetService->validateSufficientAssets($user->id, 'BTC', $largerAssetAmount));
        }
    }

    /**
     * Test that non-existent assets are handled correctly
     */
    public function test_non_existent_asset_handling()
    {
        $user = User::factory()->create();
        
        // Try to validate assets that don't exist
        $this->assertFalse($this->assetService->validateSufficientAssets($user->id, 'BTC', '1.00000000'));
        
        // Try to lock assets that don't exist
        try {
            $this->assetService->lockAssets($user->id, 'BTC', '1.00000000');
            $this->fail('Should reject locking non-existent assets');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Asset not found', $e->getMessage());
        }
    }

    private function assertInsufficientUSDBalanceRejection(): void
    {
        $balance = $this->generateValidAmount();
        $user = User::factory()->create(['balance' => $balance]);
        
        // Generate an amount larger than the balance
        $excessiveAmount = bcadd($balance, $this->generateValidAmount(), 8);
        
        $originalBalance = $user->balance;
        
        // Attempt to lock excessive funds should fail
        try {
            $this->balanceService->lockFunds($user->id, $excessiveAmount);
            $this->fail("Should reject locking funds exceeding balance. Balance: {$balance}, Attempted: {$excessiveAmount}");
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }
        
        // Verify balance remains unchanged
        $user->refresh();
        $this->assertEquals(
            number_format($originalBalance, 8, '.', ''),
            number_format($user->balance, 8, '.', ''),
            "Balance should remain unchanged after failed lock attempt"
        );
        
        // Verify validation method correctly identifies insufficient balance
        $this->assertFalse(
            $this->balanceService->validateSufficientBalance($user->id, $excessiveAmount),
            "Validation should return false for insufficient balance"
        );
    }

    private function assertInsufficientAssetBalanceRejection(): void
    {
        $totalAmount = $this->generateValidAmount();
        $lockedAmount = $this->generateSmallerAmount($totalAmount);
        
        $user = User::factory()->create();
        $symbols = ['BTC', 'ETH'];
        $symbol = $symbols[array_rand($symbols)];
        
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'amount' => $totalAmount,
            'locked_amount' => $lockedAmount
        ]);
        
        $availableAmount = $asset->available_amount;
        
        // Generate an amount larger than available
        $excessiveAmount = bcadd($availableAmount, $this->generateValidAmount(), 8);
        
        $originalAmount = $asset->amount;
        $originalLocked = $asset->locked_amount;
        
        // Attempt to lock excessive assets should fail
        try {
            $this->assetService->lockAssets($user->id, $symbol, $excessiveAmount);
            $this->fail("Should reject locking assets exceeding available amount. Available: {$availableAmount}, Attempted: {$excessiveAmount}");
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }
        
        // Verify asset state remains unchanged
        $asset->refresh();
        $this->assertEquals(
            number_format($originalAmount, 8, '.', ''),
            number_format($asset->amount, 8, '.', ''),
            "Asset amount should remain unchanged after failed lock attempt"
        );
        
        $this->assertEquals(
            number_format($originalLocked, 8, '.', ''),
            number_format($asset->locked_amount, 8, '.', ''),
            "Asset locked amount should remain unchanged after failed lock attempt"
        );
        
        // Verify validation method correctly identifies insufficient assets
        $this->assertFalse(
            $this->assetService->validateSufficientAssets($user->id, $symbol, $excessiveAmount),
            "Validation should return false for insufficient assets"
        );
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
        
        // Ensure it's not zero and not equal to max
        return bccomp($result, '0', 8) > 0 && bccomp($result, $maxAmount, 8) < 0 ? $result : '0.00000001';
    }
}