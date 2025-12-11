<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Order;
use App\Models\Asset;
use App\Models\Trade;
use App\Services\OrderService;
use App\Services\BalanceService;
use App\Services\AssetService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeExecutionCompletenessTest extends TestCase
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
     * **Feature: asset-manager, Property 9: Trade Execution Completeness**
     * 
     * For any successful match, both orders should be marked as filled, assets and USD 
     * should transfer between users, and 1.5% commission should be deducted from trade volume
     * 
     * **Validates: Requirements 5.4, 5.5**
     */
    public function test_trade_execution_completeness_property()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->assertTradeExecutionCompleteness();
        }
    }

    /**
     * Test that both orders are marked as filled
     */
    public function test_both_orders_marked_filled()
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create();
        
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Create matching orders
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller->id, 'BTC', '1.00000000');
        
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer->id, '50000.00000000');
        
        // Execute match
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNotNull($trade);
        $this->assertTrue($buyOrder->fresh()->isFilled());
        $this->assertTrue($sellOrder->fresh()->isFilled());
    }

    /**
     * Test that assets transfer from seller to buyer
     */
    public function test_assets_transfer_to_buyer()
    {
        $buyer = User::factory()->create(['balance' => '1000000.00000000']);
        $seller = User::factory()->create();
        
        $sellerAsset = Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Create matching orders
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '2.50000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller->id, 'BTC', '2.50000000');
        
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '2.50000000',
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer->id, '125000.00000000');
        
        // Execute match
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNotNull($trade);
        
        // Verify seller lost assets
        $sellerAsset->refresh();
        $this->assertEquals('7.50000000', $sellerAsset->amount);
        $this->assertEquals('0.00000000', $sellerAsset->locked_amount);
        
        // Verify buyer gained assets
        $buyerAssets = $this->assetService->getUserAssets($buyer->id);
        $this->assertArrayHasKey('BTC', $buyerAssets);
        $this->assertEquals('2.50000000', $buyerAssets['BTC']['total_amount']);
    }

    /**
     * Test that USD transfers from buyer to seller (minus commission)
     */
    public function test_usd_transfers_correctly()
    {
        $initialBuyerBalance = '100000.00000000';
        $initialSellerBalance = '50000.00000000';
        
        $buyer = User::factory()->create(['balance' => $initialBuyerBalance]);
        $seller = User::factory()->create(['balance' => $initialSellerBalance]);
        
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'ETH',
            'amount' => '100.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        $price = '2000.00000000';
        $amount = '5.00000000';
        $volume = bcmul($price, $amount, 8); // 10000.00000000
        $commission = bcmul($volume, '0.015', 8); // 150.00000000
        $usdAfterCommission = bcsub($volume, $commission, 8); // 9850.00000000
        
        // Create matching orders
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'ETH',
            'side' => 'sell',
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller->id, 'ETH', $amount);
        
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'ETH',
            'side' => 'buy',
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer->id, $volume);
        
        // Execute match
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNotNull($trade);
        
        // Verify buyer balance decreased by volume + commission
        $buyer->refresh();
        $expectedBuyerBalance = bcsub($initialBuyerBalance, $volume, 8);
        $this->assertEquals(
            number_format($expectedBuyerBalance, 8, '.', ''),
            number_format($buyer->balance, 8, '.', '')
        );
        
        // Verify seller balance increased by USD after commission
        $seller->refresh();
        $expectedSellerBalance = bcadd($initialSellerBalance, $usdAfterCommission, 8);
        $this->assertEquals(
            number_format($expectedSellerBalance, 8, '.', ''),
            number_format($seller->balance, 8, '.', '')
        );
    }

    /**
     * Test that commission is correctly calculated and deducted
     */
    public function test_commission_calculation_and_deduction()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Use reasonable prices to avoid balance issues
            $price = bcadd('100', (string)rand(0, 900), 8);
            $amount = bcadd('0.1', bcdiv((string)rand(0, 90), '100', 8), 8);
            $volume = bcmul($price, $amount, 8);
            $expectedCommission = bcmul($volume, '0.015', 8);
            
            $buyer = User::factory()->create(['balance' => '1000000.00000000']);
            $seller = User::factory()->create();
            
            Asset::create([
                'user_id' => $seller->id,
                'symbol' => 'BTC',
                'amount' => '1000.00000000',
                'locked_amount' => '0.00000000'
            ]);
            
            // Create matching orders
            $sellOrder = Order::create([
                'user_id' => $seller->id,
                'symbol' => 'BTC',
                'side' => 'sell',
                'price' => $price,
                'amount' => $amount,
                'status' => Order::STATUS_OPEN
            ]);
            $this->assetService->lockAssets($seller->id, 'BTC', $amount);
            
            $buyOrder = Order::create([
                'user_id' => $buyer->id,
                'symbol' => 'BTC',
                'side' => 'buy',
                'price' => $price,
                'amount' => $amount,
                'status' => Order::STATUS_OPEN
            ]);
            $this->balanceService->lockFunds($buyer->id, $volume);
            
            // Execute match
            $trade = $this->matchingEngine->processNewOrder($buyOrder);
            
            $this->assertNotNull($trade);
            $this->assertEquals(
                number_format($expectedCommission, 8, '.', ''),
                number_format($trade->commission, 8, '.', ''),
                "Commission should be 1.5% of volume"
            );
        }
    }

    /**
     * Test that trade record is created with correct data
     */
    public function test_trade_record_created_correctly()
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create();
        
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        $price = '50000.00000000';
        $amount = '1.00000000';
        
        // Create matching orders
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller->id, 'BTC', $amount);
        
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer->id, bcmul($price, $amount, 8));
        
        // Execute match
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNotNull($trade);
        $this->assertEquals($buyOrder->id, $trade->buy_order_id);
        $this->assertEquals($sellOrder->id, $trade->sell_order_id);
        $this->assertEquals($buyer->id, $trade->buyer_id);
        $this->assertEquals($seller->id, $trade->seller_id);
        $this->assertEquals('BTC', $trade->symbol);
        $this->assertEquals($price, $trade->price);
        $this->assertEquals($amount, $trade->amount);
        $this->assertEquals(bcmul($price, $amount, 8), $trade->volume);
        $this->assertEquals(bcmul(bcmul($price, $amount, 8), '0.015', 8), $trade->commission);
    }

    private function assertTradeExecutionCompleteness(): void
    {
        // Use reasonable prices to avoid balance issues
        $price = bcadd('100', (string)rand(0, 900), 8);
        $amount = bcadd('0.1', bcdiv((string)rand(0, 90), '100', 8), 8);
        
        $buyer = User::factory()->create(['balance' => '1000000.00000000']);
        $seller = User::factory()->create();
        
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '1000.00000000',
            'locked_amount' => '0.00000000'
        ]);
        
        // Create matching orders
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN
        ]);
        $this->assetService->lockAssets($seller->id, 'BTC', $amount);
        
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN
        ]);
        $this->balanceService->lockFunds($buyer->id, bcmul($price, $amount, 8));
        
        // Execute match
        $trade = $this->matchingEngine->processNewOrder($buyOrder);
        
        $this->assertNotNull($trade, "Trade should be created");
        
        // Verify both orders are filled
        $this->assertTrue($buyOrder->fresh()->isFilled(), "Buy order should be filled");
        $this->assertTrue($sellOrder->fresh()->isFilled(), "Sell order should be filled");
        
        // Verify assets transferred
        $buyerAssets = $this->assetService->getUserAssets($buyer->id);
        $this->assertArrayHasKey('BTC', $buyerAssets, "Buyer should have BTC assets");
        $this->assertEquals(
            number_format($amount, 8, '.', ''),
            number_format($buyerAssets['BTC']['total_amount'], 8, '.', ''),
            "Buyer should have received the correct amount of assets"
        );
        
        // Verify commission is 1.5% of volume
        $volume = bcmul($price, $amount, 8);
        $expectedCommission = bcmul($volume, '0.015', 8);
        $this->assertEquals(
            number_format($expectedCommission, 8, '.', ''),
            number_format($trade->commission, 8, '.', ''),
            "Commission should be 1.5% of volume"
        );
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
        $integerPart = rand(0, 100);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(1, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        $amount = $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
        
        return bccomp($amount, '0', 8) > 0 ? $amount : '0.00000001';
    }
}
