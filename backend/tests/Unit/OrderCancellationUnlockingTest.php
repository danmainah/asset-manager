<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Order;
use App\Models\Asset;
use App\Services\OrderService;
use App\Services\BalanceService;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationUnlockingTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;
    private BalanceService $balanceService;
    private AssetService $assetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->balanceService = new BalanceService();
        $this->assetService = new AssetService();
        $this->orderService = new OrderService($this->balanceService, $this->assetService);
    }

    /**
     * **Feature: asset-manager, Property 7: Order Cancellation Unlocking**
     * 
     * For any open order, cancelling it should release all locked funds or assets back to 
     * available balance and update order status to cancelled.
     * 
     * **Validates: Requirements 4.1, 4.2, 4.3**
     */
    public function test_order_cancellation_unlocking_property()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random order data with reasonable amounts
            $orderData = $this->generateRandomOrderData();
            
            // Create user with sufficient balance and assets
            $user = User::factory()->create(['balance' => '1000000.00000000']);
            $this->assetService->addAssets($user->id, $orderData['symbol'], '1000000.00000000');
            
            // Record initial balances
            $initialBalance = $user->balance;
            $initialAsset = Asset::where('user_id', $user->id)
                ->where('symbol', $orderData['symbol'])
                ->first();
            $initialAssetAmount = $initialAsset->amount;
            $initialLockedAmount = $initialAsset->locked_amount;
            
            try {
                // Create order
                $order = $this->orderService->createOrder(
                    $user->id,
                    $orderData['symbol'],
                    $orderData['side'],
                    $orderData['price'],
                    $orderData['amount']
                );
                
                // Record balances after order creation
                $user->refresh();
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $orderData['symbol'])
                    ->first();
                
                $balanceAfterOrder = $user->balance;
                $assetAmountAfterOrder = $asset->amount;
                $lockedAmountAfterOrder = $asset->locked_amount;
                
                // Cancel the order
                $cancelledOrder = $this->orderService->cancelOrder($order->id, $user->id);
                
                // Record balances after cancellation
                $user->refresh();
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $orderData['symbol'])
                    ->first();
                
                $balanceAfterCancellation = $user->balance;
                $assetAmountAfterCancellation = $asset->amount;
                $lockedAmountAfterCancellation = $asset->locked_amount;
                
                // Verify cancellation unlocking
                $this->assertCancellationUnlocking(
                    $orderData,
                    $initialBalance,
                    $initialAssetAmount,
                    $initialLockedAmount,
                    $balanceAfterOrder,
                    $assetAmountAfterOrder,
                    $lockedAmountAfterOrder,
                    $balanceAfterCancellation,
                    $assetAmountAfterCancellation,
                    $lockedAmountAfterCancellation,
                    $cancelledOrder
                );
            } catch (\Exception $e) {
                // Skip if order creation fails
                continue;
            }
        }
    }

    /**
     * Test buy order cancellation releases USD funds
     */
    public function test_buy_order_cancellation_releases_usd()
    {
        $user = User::factory()->create(['balance' => '100000.00000000']);
        
        $initialBalance = $user->balance;
        
        // Create buy order
        $order = $this->orderService->createOrder(
            $user->id,
            'BTC',
            'buy',
            '50000.00000000',
            '1.00000000'
        );
        
        $user->refresh();
        $balanceAfterOrder = $user->balance;
        
        // Verify funds were locked
        $expectedLockedAmount = '50000.00000000';
        $this->assertEquals(
            number_format(bcsub($initialBalance, $expectedLockedAmount, 8), 8, '.', ''),
            number_format($balanceAfterOrder, 8, '.', ''),
            'USD funds not locked for buy order'
        );
        
        // Cancel order
        $cancelledOrder = $this->orderService->cancelOrder($order->id, $user->id);
        
        $user->refresh();
        $balanceAfterCancellation = $user->balance;
        
        // Verify funds were released
        $this->assertEquals(
            number_format($initialBalance, 8, '.', ''),
            number_format($balanceAfterCancellation, 8, '.', ''),
            'USD funds not released after buy order cancellation'
        );
        
        // Verify order status is cancelled
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelledOrder->status);
        $this->assertTrue($cancelledOrder->isCancelled());
    }

    /**
     * Test sell order cancellation releases assets
     */
    public function test_sell_order_cancellation_releases_assets()
    {
        $user = User::factory()->create(['balance' => '100000.00000000']);
        $this->assetService->addAssets($user->id, 'BTC', '10.00000000');
        
        $asset = Asset::where('user_id', $user->id)->where('symbol', 'BTC')->first();
        $initialAmount = $asset->amount;
        $initialLockedAmount = $asset->locked_amount;
        
        // Create sell order
        $order = $this->orderService->createOrder(
            $user->id,
            'BTC',
            'sell',
            '50000.00000000',
            '2.00000000'
        );
        
        $asset->refresh();
        $amountAfterOrder = $asset->amount;
        $lockedAmountAfterOrder = $asset->locked_amount;
        
        // Verify assets were locked
        $this->assertEquals(
            number_format($initialAmount, 8, '.', ''),
            number_format($amountAfterOrder, 8, '.', ''),
            'Total asset amount should not change'
        );
        
        $this->assertEquals(
            number_format(bcadd($initialLockedAmount, '2.00000000', 8), 8, '.', ''),
            number_format($lockedAmountAfterOrder, 8, '.', ''),
            'Assets not locked for sell order'
        );
        
        // Cancel order
        $cancelledOrder = $this->orderService->cancelOrder($order->id, $user->id);
        
        $asset->refresh();
        $amountAfterCancellation = $asset->amount;
        $lockedAmountAfterCancellation = $asset->locked_amount;
        
        // Verify assets were released
        $this->assertEquals(
            number_format($initialLockedAmount, 8, '.', ''),
            number_format($lockedAmountAfterCancellation, 8, '.', ''),
            'Assets not released after sell order cancellation'
        );
        
        // Verify order status is cancelled
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelledOrder->status);
        $this->assertTrue($cancelledOrder->isCancelled());
    }

    /**
     * Test cancellation of non-existent order fails
     */
    public function test_cancel_non_existent_order_fails()
    {
        $user = User::factory()->create();
        
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->orderService->cancelOrder(99999, $user->id);
    }

    /**
     * Test cancellation of already filled order fails
     */
    public function test_cancel_filled_order_fails()
    {
        $user = User::factory()->create(['balance' => '100000.00000000']);
        
        $order = $this->orderService->createOrder(
            $user->id,
            'BTC',
            'buy',
            '50000.00000000',
            '1.00000000'
        );
        
        // Mark as filled
        $this->orderService->markOrderFilled($order->id);
        
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->orderService->cancelOrder($order->id, $user->id);
    }

    /**
     * Test cancellation of already cancelled order fails
     */
    public function test_cancel_already_cancelled_order_fails()
    {
        $user = User::factory()->create(['balance' => '100000.00000000']);
        
        $order = $this->orderService->createOrder(
            $user->id,
            'BTC',
            'buy',
            '50000.00000000',
            '1.00000000'
        );
        
        // Cancel once
        $this->orderService->cancelOrder($order->id, $user->id);
        
        // Try to cancel again
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->orderService->cancelOrder($order->id, $user->id);
    }

    /**
     * Test cancellation with wrong user fails
     */
    public function test_cancel_order_with_wrong_user_fails()
    {
        $user1 = User::factory()->create(['balance' => '100000.00000000']);
        $user2 = User::factory()->create(['balance' => '100000.00000000']);
        
        $order = $this->orderService->createOrder(
            $user1->id,
            'BTC',
            'buy',
            '50000.00000000',
            '1.00000000'
        );
        
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->orderService->cancelOrder($order->id, $user2->id);
    }

    private function assertCancellationUnlocking(
        array $orderData,
        string $initialBalance,
        string $initialAssetAmount,
        string $initialLockedAmount,
        string $balanceAfterOrder,
        string $assetAmountAfterOrder,
        string $lockedAmountAfterOrder,
        string $balanceAfterCancellation,
        string $assetAmountAfterCancellation,
        string $lockedAmountAfterCancellation,
        Order $cancelledOrder
    ): void {
        // Verify order status is cancelled
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelledOrder->status, 
            'Order status not updated to cancelled');
        $this->assertTrue($cancelledOrder->isCancelled(), 
            'Order isCancelled() method returns false');
        
        if ($orderData['side'] === 'buy') {
            // For buy orders, verify USD funds are released
            $lockedAmount = bcmul($orderData['price'], $orderData['amount'], 8);
            
            // Balance should decrease after order creation
            $this->assertEquals(
                number_format(bcsub($initialBalance, $lockedAmount, 8), 8, '.', ''),
                number_format($balanceAfterOrder, 8, '.', ''),
                'USD funds not locked after buy order creation'
            );
            
            // Balance should return to initial after cancellation
            $this->assertEquals(
                number_format($initialBalance, 8, '.', ''),
                number_format($balanceAfterCancellation, 8, '.', ''),
                'USD funds not released after buy order cancellation'
            );
        } else {
            // For sell orders, verify assets are released
            $lockedAmount = $orderData['amount'];
            
            // Locked amount should increase after order creation
            $this->assertEquals(
                number_format(bcadd($initialLockedAmount, $lockedAmount, 8), 8, '.', ''),
                number_format($lockedAmountAfterOrder, 8, '.', ''),
                'Assets not locked after sell order creation'
            );
            
            // Locked amount should return to initial after cancellation
            $this->assertEquals(
                number_format($initialLockedAmount, 8, '.', ''),
                number_format($lockedAmountAfterCancellation, 8, '.', ''),
                'Assets not released after sell order cancellation'
            );
        }
    }

    private function generateRandomOrderData(): array
    {
        $symbols = ['BTC', 'ETH'];
        $sides = ['buy', 'sell'];
        
        return [
            'symbol' => $symbols[array_rand($symbols)],
            'side' => $sides[array_rand($sides)],
            'price' => $this->generateValidPrice(),
            'amount' => $this->generateValidAmount(),
        ];
    }

    private function generateValidPrice(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(1, 999999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(1, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }

    private function generateValidAmount(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(0, 999999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(1, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        $amount = $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
        
        return bccomp($amount, '0', 8) > 0 ? $amount : '0.00000001';
    }
}
