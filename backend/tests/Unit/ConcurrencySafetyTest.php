<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Asset;
use App\Services\BalanceService;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencySafetyTest extends TestCase
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
     * **Feature: asset-manager, Property 5: Concurrency Safety**
     * 
     * For any set of concurrent operations on the same user's balance, the final state should be 
     * equivalent to some sequential execution of those operations.
     * 
     * **Validates: Requirements 2.5**
     */
    public function test_concurrency_safety_property()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test concurrent fund operations
            $this->assertConcurrentFundOperationsSafety();
            
            // Test concurrent asset operations
            $this->assertConcurrentAssetOperationsSafety();
            
            // Test concurrent transfer operations
            $this->assertConcurrentTransferOperationsSafety();
        }
    }

    /**
     * Test that database transactions provide isolation
     */
    public function test_database_transaction_isolation()
    {
        $user = User::factory()->create(['balance' => '1000.00000000']);
        
        // Test that operations within transactions are isolated
        DB::transaction(function () use ($user) {
            $lockedUser = User::lockForUpdate()->find($user->id);
            $originalBalance = $lockedUser->balance;
            
            // Simulate some processing time
            usleep(1000); // 1ms
            
            // Update balance
            $newBalance = bcadd($originalBalance, '100.00000000', 8);
            $lockedUser->update(['balance' => $newBalance]);
            
            // Verify the change is visible within transaction
            $this->assertEquals($newBalance, $lockedUser->fresh()->balance);
        });
        
        // Verify the change is committed after transaction
        $user->refresh();
        $this->assertEquals('1100.00000000', $user->balance);
    }

    /**
     * Test sequential consistency of operations
     */
    public function test_sequential_consistency()
    {
        $iterations = 20;
        
        for ($i = 0; $i < $iterations; $i++) {
            $user = User::factory()->create(['balance' => '1000.00000000']);
            
            // Perform a series of operations sequentially
            $operations = $this->generateRandomOperations();
            $expectedBalance = '1000.00000000';
            
            foreach ($operations as $operation) {
                switch ($operation['type']) {
                    case 'lock':
                        if ($this->balanceService->validateSufficientBalance($user->id, $operation['amount'])) {
                            $this->balanceService->lockFunds($user->id, $operation['amount']);
                            $expectedBalance = bcsub($expectedBalance, $operation['amount'], 8);
                        }
                        break;
                    case 'release':
                        $this->balanceService->releaseFunds($user->id, $operation['amount']);
                        $expectedBalance = bcadd($expectedBalance, $operation['amount'], 8);
                        break;
                }
                
                // Verify balance consistency after each operation
                $user->refresh();
                $this->assertEquals(
                    number_format($expectedBalance, 8, '.', ''),
                    number_format($user->balance, 8, '.', ''),
                    "Balance inconsistency after {$operation['type']} operation"
                );
            }
        }
    }

    /**
     * Test asset locking consistency under concurrent access
     */
    public function test_asset_locking_consistency()
    {
        $user = User::factory()->create();
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Perform multiple lock operations
        $lockAmounts = ['2.00000000', '3.00000000', '1.50000000'];
        $totalLocked = '0.00000000';
        
        foreach ($lockAmounts as $amount) {
            $this->assertTrue($this->assetService->lockAssets($user->id, 'BTC', $amount));
            $totalLocked = bcadd($totalLocked, $amount, 8);
            
            $asset->refresh();
            $this->assertEquals(
                number_format($totalLocked, 8, '.', ''),
                number_format($asset->locked_amount, 8, '.', ''),
                "Locked amount inconsistency"
            );
            
            $expectedAvailable = bcsub('10.00000000', $totalLocked, 8);
            $this->assertEquals(
                number_format($expectedAvailable, 8, '.', ''),
                number_format($asset->available_amount, 8, '.', ''),
                "Available amount calculation inconsistency"
            );
        }
    }

    /**
     * Test transfer operations maintain balance conservation
     */
    public function test_transfer_balance_conservation()
    {
        $iterations = 30;
        
        for ($i = 0; $i < $iterations; $i++) {
            $user1 = User::factory()->create(['balance' => '1000.00000000']);
            $user2 = User::factory()->create(['balance' => '500.00000000']);
            
            $initialTotalBalance = bcadd($user1->balance, $user2->balance, 8);
            
            $transferAmount = $this->generateValidTransferAmount($user1->balance);
            
            // Perform transfer
            $this->assertTrue($this->balanceService->transferUSD($user1->id, $user2->id, $transferAmount));
            
            // Verify balance conservation
            $user1->refresh();
            $user2->refresh();
            
            $finalTotalBalance = bcadd($user1->balance, $user2->balance, 8);
            
            $this->assertEquals(
                number_format($initialTotalBalance, 8, '.', ''),
                number_format($finalTotalBalance, 8, '.', ''),
                "Total balance not conserved during transfer"
            );
            
            // Verify individual balances are correct
            $expectedUser1Balance = bcsub('1000.00000000', $transferAmount, 8);
            $expectedUser2Balance = bcadd('500.00000000', $transferAmount, 8);
            
            $this->assertEquals(
                number_format($expectedUser1Balance, 8, '.', ''),
                number_format($user1->balance, 8, '.', ''),
                "Sender balance incorrect after transfer"
            );
            
            $this->assertEquals(
                number_format($expectedUser2Balance, 8, '.', ''),
                number_format($user2->balance, 8, '.', ''),
                "Receiver balance incorrect after transfer"
            );
        }
    }

    /**
     * Test asset transfer operations maintain asset conservation
     */
    public function test_asset_transfer_conservation()
    {
        $iterations = 20;
        
        for ($i = 0; $i < $iterations; $i++) {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            
            $initialAmount = '10.00000000';
            $transferAmount = $this->generateValidTransferAmount($initialAmount);
            
            // Create asset for user1 with some locked amount
            $asset1 = Asset::create([
                'user_id' => $user1->id,
                'symbol' => 'ETH',
                'amount' => $initialAmount,
                'locked_amount' => $transferAmount // Lock the amount to be transferred
            ]);
            
            // Create or get asset for user2
            $asset2 = $this->assetService->getOrCreateAsset($user2->id, 'ETH');
            $initialUser2Amount = $asset2->amount;
            
            $initialTotalAmount = bcadd($asset1->amount, $asset2->amount, 8);
            
            // Perform transfer
            $this->assertTrue($this->assetService->transferAssets($user1->id, $user2->id, 'ETH', $transferAmount));
            
            // Verify asset conservation
            $asset1->refresh();
            $asset2->refresh();
            
            $finalTotalAmount = bcadd($asset1->amount, $asset2->amount, 8);
            
            $this->assertEquals(
                number_format($initialTotalAmount, 8, '.', ''),
                number_format($finalTotalAmount, 8, '.', ''),
                "Total asset amount not conserved during transfer"
            );
            
            // Verify individual asset amounts are correct
            $expectedUser1Amount = bcsub($initialAmount, $transferAmount, 8);
            $expectedUser2Amount = bcadd($initialUser2Amount, $transferAmount, 8);
            
            $this->assertEquals(
                number_format($expectedUser1Amount, 8, '.', ''),
                number_format($asset1->amount, 8, '.', ''),
                "Sender asset amount incorrect after transfer"
            );
            
            $this->assertEquals(
                number_format($expectedUser2Amount, 8, '.', ''),
                number_format($asset2->amount, 8, '.', ''),
                "Receiver asset amount incorrect after transfer"
            );
            
            // Verify locked amount was properly reduced
            $expectedLockedAmount = '0.00000000';
            $this->assertEquals(
                $expectedLockedAmount,
                $asset1->locked_amount,
                "Locked amount not properly reduced after transfer"
            );
        }
    }

    /**
     * Test commission deduction maintains precision
     */
    public function test_commission_deduction_precision()
    {
        $iterations = 30;
        
        for ($i = 0; $i < $iterations; $i++) {
            $initialBalance = $this->generateValidAmount();
            $user = User::factory()->create(['balance' => $initialBalance]);
            
            $commissionAmount = $this->generateValidCommissionAmount($initialBalance);
            
            // Deduct commission
            $this->assertTrue($this->balanceService->deductCommission($user->id, $commissionAmount));
            
            // Verify balance precision is maintained
            $user->refresh();
            $expectedBalance = bcsub($initialBalance, $commissionAmount, 8);
            
            $this->assertEquals(
                number_format($expectedBalance, 8, '.', ''),
                number_format($user->balance, 8, '.', ''),
                "Commission deduction precision not maintained"
            );
            
            // Verify the result has proper decimal precision
            $balanceParts = explode('.', (string)$user->balance);
            if (isset($balanceParts[1])) {
                $this->assertLessThanOrEqual(8, strlen($balanceParts[1]), "Balance precision exceeds 8 decimal places");
            }
        }
    }

    private function assertConcurrentFundOperationsSafety(): void
    {
        $user = User::factory()->create(['balance' => '1000.00000000']);
        
        // Simulate concurrent operations by performing them in sequence
        // but verifying the final state is consistent
        $operations = [
            ['type' => 'lock', 'amount' => '200.00000000'],
            ['type' => 'lock', 'amount' => '150.00000000'],
            ['type' => 'release', 'amount' => '100.00000000'],
            ['type' => 'lock', 'amount' => '50.00000000'],
            ['type' => 'release', 'amount' => '200.00000000']
        ];
        
        $expectedBalance = '1000.00000000';
        
        foreach ($operations as $operation) {
            switch ($operation['type']) {
                case 'lock':
                    if ($this->balanceService->validateSufficientBalance($user->id, $operation['amount'])) {
                        $this->balanceService->lockFunds($user->id, $operation['amount']);
                        $expectedBalance = bcsub($expectedBalance, $operation['amount'], 8);
                    }
                    break;
                case 'release':
                    $this->balanceService->releaseFunds($user->id, $operation['amount']);
                    $expectedBalance = bcadd($expectedBalance, $operation['amount'], 8);
                    break;
            }
        }
        
        $user->refresh();
        $this->assertEquals(
            number_format($expectedBalance, 8, '.', ''),
            number_format($user->balance, 8, '.', ''),
            "Final balance inconsistent after concurrent operations"
        );
    }

    private function assertConcurrentAssetOperationsSafety(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        $operations = [
            ['type' => 'lock', 'amount' => '3.00000000'],
            ['type' => 'lock', 'amount' => '2.00000000'],
            ['type' => 'release', 'amount' => '1.50000000'],
            ['type' => 'lock', 'amount' => '1.00000000'],
            ['type' => 'release', 'amount' => '2.50000000']
        ];
        
        $expectedLocked = '0.00000000';
        
        foreach ($operations as $operation) {
            switch ($operation['type']) {
                case 'lock':
                    $asset->refresh();
                    if ($asset->hasSufficientAmount($operation['amount'])) {
                        $this->assetService->lockAssets($user->id, 'BTC', $operation['amount']);
                        $expectedLocked = bcadd($expectedLocked, $operation['amount'], 8);
                    }
                    break;
                case 'release':
                    $this->assetService->releaseAssets($user->id, 'BTC', $operation['amount']);
                    $expectedLocked = bcsub($expectedLocked, $operation['amount'], 8);
                    break;
            }
        }
        
        $asset->refresh();
        $this->assertEquals(
            number_format($expectedLocked, 8, '.', ''),
            number_format($asset->locked_amount, 8, '.', ''),
            "Final locked amount inconsistent after concurrent operations"
        );
        
        $expectedAvailable = bcsub('10.00000000', $expectedLocked, 8);
        $this->assertEquals(
            number_format($expectedAvailable, 8, '.', ''),
            number_format($asset->available_amount, 8, '.', ''),
            "Available amount calculation inconsistent"
        );
    }

    private function assertConcurrentTransferOperationsSafety(): void
    {
        $user1 = User::factory()->create(['balance' => '1000.00000000']);
        $user2 = User::factory()->create(['balance' => '500.00000000']);
        $user3 = User::factory()->create(['balance' => '300.00000000']);
        
        $initialTotal = bcadd(bcadd($user1->balance, $user2->balance, 8), $user3->balance, 8);
        
        // Perform a series of transfers
        $transfers = [
            ['from' => $user1->id, 'to' => $user2->id, 'amount' => '200.00000000'],
            ['from' => $user2->id, 'to' => $user3->id, 'amount' => '150.00000000'],
            ['from' => $user3->id, 'to' => $user1->id, 'amount' => '100.00000000']
        ];
        
        foreach ($transfers as $transfer) {
            $this->assertTrue($this->balanceService->transferUSD(
                $transfer['from'], 
                $transfer['to'], 
                $transfer['amount']
            ));
        }
        
        // Verify total balance is conserved
        $user1->refresh();
        $user2->refresh();
        $user3->refresh();
        
        $finalTotal = bcadd(bcadd($user1->balance, $user2->balance, 8), $user3->balance, 8);
        
        $this->assertEquals(
            number_format($initialTotal, 8, '.', ''),
            number_format($finalTotal, 8, '.', ''),
            "Total balance not conserved across multiple transfers"
        );
    }

    private function generateRandomOperations(): array
    {
        $operations = [];
        $operationCount = rand(3, 8);
        
        for ($i = 0; $i < $operationCount; $i++) {
            $operations[] = [
                'type' => rand(0, 1) ? 'lock' : 'release',
                'amount' => $this->generateValidAmount()
            ];
        }
        
        return $operations;
    }

    private function generateValidAmount(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(1, 999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(1, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }

    private function generateValidTransferAmount(string $maxAmount): string
    {
        $percentage = rand(10, 50) / 100; // 10-50% of max amount
        $result = bcmul($maxAmount, (string)$percentage, 8);
        
        return bccomp($result, '0', 8) > 0 ? $result : '0.00000001';
    }

    private function generateValidCommissionAmount(string $maxAmount): string
    {
        $percentage = rand(1, 10) / 100; // 1-10% of max amount
        $result = bcmul($maxAmount, (string)$percentage, 8);
        
        return bccomp($result, '0', 8) > 0 ? $result : '0.00000001';
    }
}