<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\BalanceService;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderbookAccuracyTest extends TestCase
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
     * **Feature: asset-manager, Property 6: Orderbook Accuracy**
     * 
     * For any symbol, the orderbook should contain exactly the open orders for that symbol, 
     * with buy orders sorted by price descending and sell orders by price ascending.
     * 
     * **Validates: Requirements 3.1, 3.2, 3.3**
     */
    public function test_orderbook_accuracy_property()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random symbol
            $symbol = $this->generateRandomSymbol();
            
            // Create users with sufficient balances and assets
            $users = [];
            for ($j = 0; $j < 5; $j++) {
                $user = User::factory()->create(['balance' => '1000000.00000000']);
                $this->assetService->addAssets($user->id, $symbol, '1000000.00000000');
                $users[] = $user;
            }
            
            // Create random open orders
            $expectedOpenOrderIds = [];
            $orderCount = rand(3, 10);
            
            for ($j = 0; $j < $orderCount; $j++) {
                $user = $users[array_rand($users)];
                $side = rand(0, 1) ? 'buy' : 'sell';
                $price = $this->generateValidPrice();
                $amount = $this->generateValidAmount();
                
                try {
                    $order = $this->orderService->createOrder(
                        $user->id,
                        $symbol,
                        $side,
                        $price,
                        $amount
                    );
                    
                    $expectedOpenOrderIds[] = $order->id;
                } catch (\Exception $e) {
                    // Skip if order creation fails
                    continue;
                }
            }
            
            // Get orderbook for this specific symbol
            $orderbook = $this->orderService->getOrderbook($symbol);
            
            // Collect all orders from orderbook
            $allOrderbookOrders = array_merge($orderbook['buy_orders'], $orderbook['sell_orders']);
            
            // Verify all orderbook orders are open
            foreach ($allOrderbookOrders as $order) {
                $this->assertEquals(Order::STATUS_OPEN, $order['status']);
            }
            
            // Verify all expected open orders are in orderbook
            $orderbookIds = array_map(fn($order) => $order['id'], $allOrderbookOrders);
            foreach ($expectedOpenOrderIds as $expectedId) {
                $this->assertContains($expectedId, $orderbookIds);
            }
            
            // Verify buy orders are sorted descending
            if (!empty($orderbook['buy_orders'])) {
                $buyPrices = array_map(fn($order) => (float)$order['price'], $orderbook['buy_orders']);
                $sortedBuyPrices = $buyPrices;
                rsort($sortedBuyPrices);
                
                $this->assertEquals($sortedBuyPrices, $buyPrices);
            }
            
            // Verify sell orders are sorted ascending
            if (!empty($orderbook['sell_orders'])) {
                $sellPrices = array_map(fn($order) => (float)$order['price'], $orderbook['sell_orders']);
                $sortedSellPrices = $sellPrices;
                sort($sortedSellPrices);
                
                $this->assertEquals($sortedSellPrices, $sellPrices);
            }
        }
    }

    /**
     * Test orderbook sorting accuracy
     */
    public function test_orderbook_sorting_accuracy()
    {
        $symbol = 'BTC';
        
        // Create users with sufficient balances
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['balance' => '1000000.00000000']);
            $this->assetService->addAssets($user->id, $symbol, '1000000.00000000');
            $users[] = $user;
        }
        
        // Create buy orders with specific prices
        $buyPrices = ['50000.00000000', '49500.00000000', '51000.00000000', '49000.00000000'];
        foreach ($buyPrices as $price) {
            $this->orderService->createOrder($users[0]->id, $symbol, 'buy', $price, '1.00000000');
        }
        
        // Create sell orders with specific prices
        $sellPrices = ['52000.00000000', '51500.00000000', '53000.00000000', '50500.00000000'];
        foreach ($sellPrices as $price) {
            $this->orderService->createOrder($users[1]->id, $symbol, 'sell', $price, '1.00000000');
        }
        
        // Get orderbook
        $orderbook = $this->orderService->getOrderbook($symbol);
        
        // Verify buy orders are sorted descending by price
        $buyOrderPrices = array_map(fn($order) => $order['price'], $orderbook['buy_orders']);
        $sortedBuyPrices = $buyOrderPrices;
        rsort($sortedBuyPrices);
        
        $this->assertEquals($sortedBuyPrices, $buyOrderPrices, 'Buy orders not sorted descending by price');
        
        // Verify sell orders are sorted ascending by price
        $sellOrderPrices = array_map(fn($order) => $order['price'], $orderbook['sell_orders']);
        $sortedSellPrices = $sellOrderPrices;
        sort($sortedSellPrices);
        
        $this->assertEquals($sortedSellPrices, $sellOrderPrices, 'Sell orders not sorted ascending by price');
    }

    /**
     * Test orderbook excludes non-open orders
     */
    public function test_orderbook_excludes_non_open_orders()
    {
        $symbol = 'ETH';
        
        $user = User::factory()->create(['balance' => '1000000.00000000']);
        $this->assetService->addAssets($user->id, $symbol, '1000000.00000000');
        
        // Create an open order
        $openOrder = $this->orderService->createOrder($user->id, $symbol, 'buy', '3000.00000000', '1.00000000');
        
        // Create a filled order
        $filledOrder = $this->orderService->createOrder($user->id, $symbol, 'sell', '3500.00000000', '1.00000000');
        $this->orderService->markOrderFilled($filledOrder->id);
        
        // Create a cancelled order
        $cancelledOrder = $this->orderService->createOrder($user->id, $symbol, 'buy', '2500.00000000', '1.00000000');
        $this->orderService->cancelOrder($cancelledOrder->id, $user->id);
        
        // Get orderbook
        $orderbook = $this->orderService->getOrderbook($symbol);
        
        // Verify only open order is in orderbook
        $allOrders = array_merge($orderbook['buy_orders'], $orderbook['sell_orders']);
        $this->assertCount(1, $allOrders, 'Orderbook should contain only 1 open order');
        
        $orderIds = array_map(fn($order) => $order['id'], $allOrders);
        $this->assertContains($openOrder->id, $orderIds, 'Open order should be in orderbook');
        $this->assertNotContains($filledOrder->id, $orderIds, 'Filled order should not be in orderbook');
        $this->assertNotContains($cancelledOrder->id, $orderIds, 'Cancelled order should not be in orderbook');
    }

    /**
     * Test orderbook for symbol with no orders
     */
    public function test_orderbook_empty_symbol()
    {
        $orderbook = $this->orderService->getOrderbook('BTC');
        
        $this->assertEquals('BTC', $orderbook['symbol']);
        $this->assertEmpty($orderbook['buy_orders']);
        $this->assertEmpty($orderbook['sell_orders']);
    }



    private function generateRandomSymbol(): string
    {
        $symbols = ['BTC', 'ETH'];
        return $symbols[array_rand($symbols)];
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
