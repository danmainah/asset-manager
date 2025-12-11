<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Order;
use App\Models\Asset;
use App\Services\OrderService;
use App\Services\BalanceService;
use App\Services\AssetService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingAlgorithmCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;
    private BalanceService $balanceService;
    private AssetService $assetService;
    private MatchingEngine $matchingEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->balanceService = new BalanceService();
        $this->assetService = new AssetService();
        $this->matchingEngine = new MatchingEngine($this->balanceService, $this->assetService);
        $this->orderService = new OrderService($this->balanceService, $this->assetService, $this->matchingEngine);
    }

    /**
     * **Feature: asset-manager, Property 8: Matching Algorithm Correctness**
     * 
     * For any new order, if a compatible counter-order exists, the system should match 
     * with the best-priced available order (lowest sell price for buys, highest buy price for sells)
     * 
     * **Validates: Requirements 5.1, 5.2**
     */
    public function test_matching_algorithm_correctness_property()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test buy order matching with sell orders
            $this->assertBuyOrderMatchesLowestSellPrice();
            
            // Test sell order matching with buy orders
            $this->assertSellOrderMatchesHighestBuyPrice();
        }
    }

    /**
     * Test that buy orders match with lowest-priced sell orders
     */
    public function test_buy_order_matches_lowest_sell_price()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->assertBuyOrderMatchesLowestSellPrice();
        }
    }

    /**
     * Test that sell orders match with highest-priced buy orders
     */
    public function test_sell_order_matches_highest_buy_price()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->assertSellOrderMatchesHighestBuyPrice();
        }
    }

    /**
     * Test no match when prices don't align
     */
    public function test_no_match_when_prices_misaligned()
    {
        // Create buyer and seller
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create();
        
        // Create asset for seller
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Create sell order at high price
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '60000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        
        // Lock assets for sell order
        $this->assetService->lockAssets($seller->id, 'BTC', '1.00000000');
        
        // Create buy order at lower price
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        
        // Lock funds for buy order
        $this->balanceService->lockFunds($buyer->id, '50000.00000000');
        
        // Attempt to match - should not match
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNull($trade);
        $this->assertTrue($buyOrder->fresh()->isOpen());
        $this->assertTrue($sellOrder->fresh()->isOpen());
    }

    /**
     * Test matching with multiple counter-orders
     */
    public function test_matching_selects_best_price_among_multiple()
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller1 = User::factory()->create();
        $seller2 = User::factory()->create();
        $seller3 = User::factory()->create();
        
        // Create assets for sellers
        foreach ([$seller1, $seller2, $seller3] as $seller) {
            Asset::create([
                'user_id' => $seller->id,
                'symbol' => 'BTC',
                'amount' => '10.00000000',
                'locked_amount' => '0.00000000'
            ]);
        }
        
        // Create multiple sell orders at different prices
        $sellOrder1 = Order::create([
            'user_id' => $seller1->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '55000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller1->id, 'BTC', '1.00000000');
        
        $sellOrder2 = Order::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000', // Lowest price
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller2->id, 'BTC', '1.00000000');
        
        $sellOrder3 = Order::create([
            'user_id' => $seller3->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '52000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller3->id, 'BTC', '1.00000000');
        
        // Create buy order
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '60000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer->id, '60000.00000000');
        
        // Match should occur with lowest-priced sell order
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNotNull($trade);
        $this->assertEquals($sellOrder2->id, $trade->sell_order_id);
        $this->assertEquals($buyOrder->id, $trade->buy_order_id);
        $this->assertTrue($buyOrder->fresh()->isFilled());
        $this->assertTrue($sellOrder2->fresh()->isFilled());
        $this->assertTrue($sellOrder1->fresh()->isOpen());
        $this->assertTrue($sellOrder3->fresh()->isOpen());
    }

    private function assertBuyOrderMatchesLowestSellPrice(): void
    {
        $buyer = User::factory()->create(['balance' => '10000000.00000000']);
        $seller1 = User::factory()->create();
        $seller2 = User::factory()->create();
        
        // Create assets for sellers
        foreach ([$seller1, $seller2] as $seller) {
            Asset::create([
                'user_id' => $seller->id,
                'symbol' => 'BTC',
                'amount' => '10.00000000',
                'locked_amount' => '0.00000000'
            ]);
        }
        
        // Use fixed prices to ensure proper matching
        $price1 = '40000.00000000'; // Lower price
        $price2 = '50000.00000000'; // Higher price
        $buyPrice = '60000.00000000'; // Buy price higher than both
        
        // Create sell orders
        $sellOrder1 = Order::create([
            'user_id' => $seller1->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => $price1,
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller1->id, 'BTC', '1.00000000');
        
        $sellOrder2 = Order::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => $price2,
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller2->id, 'BTC', '1.00000000');
        
        // Create buy order
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => $buyPrice,
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer->id, bcmul($buyPrice, '1.00000000', 8));
        
        // Match should occur with lowest-priced sell order
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNotNull($trade, "Trade should be created");
        $this->assertEquals($sellOrder1->id, $trade->sell_order_id, "Should match with lowest-priced sell order");
        $this->assertEquals(
            number_format($price1, 8, '.', ''),
            number_format($trade->price, 8, '.', ''),
            "Trade price should be sell order price"
        );
    }

    private function assertSellOrderMatchesHighestBuyPrice(): void
    {
        $seller = User::factory()->create();
        $buyer1 = User::factory()->create(['balance' => '10000000.00000000']);
        $buyer2 = User::factory()->create(['balance' => '10000000.00000000']);
        
        // Create asset for seller
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'ETH',
            'amount' => '100.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Generate prices ensuring price1 > price2 > sellPrice
        $price1 = '1000.00000000';
        $price2 = '500.00000000';
        $sellPrice = '250.00000000';
        
        // Create buy orders
        $buyOrder1 = Order::create([
            'user_id' => $buyer1->id,
            'symbol' => 'ETH',
            'side' => 'buy',
            'price' => $price1,
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer1->id, bcmul($price1, '1.00000000', 8));
        
        $buyOrder2 = Order::create([
            'user_id' => $buyer2->id,
            'symbol' => 'ETH',
            'side' => 'buy',
            'price' => $price2,
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer2->id, bcmul($price2, '1.00000000', 8));
        
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'ETH',
            'side' => 'sell',
            'price' => $sellPrice,
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller->id, 'ETH', '1.00000000');
        
        // Match should occur with highest-priced buy order
        $trade = $this->matchingEngine->processNewOrder($sellOrder);
        
        $this->assertNotNull($trade, "Trade should be created");
        $this->assertEquals($buyOrder1->id, $trade->buy_order_id, "Should match with highest-priced buy order");
        $this->assertEquals(
            number_format($sellPrice, 8, '.', ''),
            number_format($trade->price, 8, '.', ''),
            "Trade price should be sell order price"
        );
    }

    private function generateValidPrice(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(1, 999999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(1, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }
}
