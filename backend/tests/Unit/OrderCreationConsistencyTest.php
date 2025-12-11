<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Carbon\Carbon;

class OrderCreationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **Feature: asset-manager, Property 3: Order Creation Consistency**
     * 
     * For any valid order parameters, creating an order should store it with open status, 
     * current timestamp, and correct user association.
     * 
     * **Validates: Requirements 2.3**
     */
    public function test_order_creation_consistency_property()
    {
        $iterations = 100; // Property-based testing with multiple iterations
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random valid order data
            $orderData = $this->generateRandomValidOrderData();
            $user = User::factory()->create();
            
            // Record time before creation
            $beforeCreation = Carbon::now();
            
            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $orderData['symbol'],
                'side' => $orderData['side'],
                'price' => $orderData['price'],
                'amount' => $orderData['amount'],
                'status' => Order::STATUS_OPEN
            ]);
            
            // Record time after creation
            $afterCreation = Carbon::now();
            
            // Assert order creation consistency
            $this->assertOrderCreationConsistency($order, $user, $orderData, $beforeCreation, $afterCreation);
        }
    }

    /**
     * Test order validation with various invalid inputs
     */
    public function test_order_validation_rejects_invalid_data()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $invalidOrderData = $this->generateRandomInvalidOrderData();
            
            // Should throw validation exception
            $this->expectException(ValidationException::class);
            Order::validateOrderData($invalidOrderData);
        }
    }

    /**
     * Test order creation with edge case values
     */
    public function test_order_creation_with_edge_cases()
    {
        $user = User::factory()->create();
        
        $edgeCases = [
            // Minimum valid values
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '0.00000001', 'amount' => '0.00000001'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '0.00000001', 'amount' => '0.00000001'],
            
            // Maximum precision values
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '99999.99999999', 'amount' => '99999.99999999'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '99999.99999999', 'amount' => '99999.99999999'],
            
            // Integer values
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '1000', 'amount' => '5'],
            ['symbol' => 'ETH', 'side' => 'sell', 'price' => '2000', 'amount' => '10'],
        ];
        
        foreach ($edgeCases as $orderData) {
            $beforeCreation = Carbon::now();
            
            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $orderData['symbol'],
                'side' => $orderData['side'],
                'price' => $orderData['price'],
                'amount' => $orderData['amount'],
                'status' => Order::STATUS_OPEN
            ]);
            
            $afterCreation = Carbon::now();
            
            $this->assertOrderCreationConsistency($order, $user, $orderData, $beforeCreation, $afterCreation);
        }
    }

    /**
     * Test that order status constants are properly defined
     */
    public function test_order_status_constants()
    {
        $this->assertEquals(1, Order::STATUS_OPEN);
        $this->assertEquals(2, Order::STATUS_FILLED);
        $this->assertEquals(3, Order::STATUS_CANCELLED);
        
        // Test status helper methods
        $user = User::factory()->create();
        
        $openOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000',
            'amount' => '1',
            'status' => Order::STATUS_OPEN
        ]);
        
        $this->assertTrue($openOrder->isOpen());
        $this->assertFalse($openOrder->isFilled());
        $this->assertFalse($openOrder->isCancelled());
        
        // Test filled order
        $openOrder->status = Order::STATUS_FILLED;
        $openOrder->save();
        
        $this->assertFalse($openOrder->isOpen());
        $this->assertTrue($openOrder->isFilled());
        $this->assertFalse($openOrder->isCancelled());
        
        // Test cancelled order
        $openOrder->status = Order::STATUS_CANCELLED;
        $openOrder->save();
        
        $this->assertFalse($openOrder->isOpen());
        $this->assertFalse($openOrder->isFilled());
        $this->assertTrue($openOrder->isCancelled());
    }

    /**
     * Test order total value calculation
     */
    public function test_order_total_value_calculation()
    {
        $iterations = 20;
        
        for ($i = 0; $i < $iterations; $i++) {
            $price = $this->generateRandomPrice();
            $amount = $this->generateRandomAmount();
            
            $user = User::factory()->create();
            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => 'BTC',
                'side' => 'buy',
                'price' => $price,
                'amount' => $amount,
                'status' => Order::STATUS_OPEN
            ]);
            
            $expectedTotal = bcmul($price, $amount, 8);
            $this->assertEquals($expectedTotal, $order->total_value,
                "Total value calculation incorrect for price {$price} and amount {$amount}");
        }
    }

    /**
     * Assert that order creation maintains consistency
     */
    private function assertOrderCreationConsistency(Order $order, User $user, array $orderData, Carbon $beforeCreation, Carbon $afterCreation): void
    {
        // Refresh order from database
        $order = $order->fresh();
        
        // Assert basic properties
        $this->assertEquals($user->id, $order->user_id, 'Order user association incorrect');
        $this->assertEquals($orderData['symbol'], $order->symbol, 'Order symbol incorrect');
        $this->assertEquals($orderData['side'], $order->side, 'Order side incorrect');
        $this->assertEquals($orderData['price'], $order->price, 'Order price incorrect');
        $this->assertEquals($orderData['amount'], $order->amount, 'Order amount incorrect');
        $this->assertEquals(Order::STATUS_OPEN, $order->status, 'Order should be created with OPEN status');
        
        // Assert timestamps are within reasonable range
        $this->assertGreaterThanOrEqual($beforeCreation, $order->created_at, 'Order created_at should be after creation start');
        $this->assertLessThanOrEqual($afterCreation, $order->created_at, 'Order created_at should be before creation end');
        $this->assertGreaterThanOrEqual($beforeCreation, $order->updated_at, 'Order updated_at should be after creation start');
        $this->assertLessThanOrEqual($afterCreation, $order->updated_at, 'Order updated_at should be before creation end');
        
        // Assert user relationship works
        $this->assertEquals($user->id, $order->user->id, 'Order user relationship incorrect');
        
        // Assert order appears in user's orders
        $this->assertTrue($user->orders->contains($order), 'Order should appear in user orders collection');
    }

    /**
     * Generate random valid order data for property-based testing
     */
    private function generateRandomValidOrderData(): array
    {
        $symbols = ['BTC', 'ETH'];
        $sides = ['buy', 'sell'];
        
        return [
            'symbol' => $symbols[array_rand($symbols)],
            'side' => $sides[array_rand($sides)],
            'price' => $this->generateRandomPrice(),
            'amount' => $this->generateRandomAmount(),
        ];
    }

    /**
     * Generate random invalid order data for property-based testing
     */
    private function generateRandomInvalidOrderData(): array
    {
        $invalidCases = [
            // Invalid symbol
            ['symbol' => 'INVALID', 'side' => 'buy', 'price' => '100', 'amount' => '1'],
            ['symbol' => '', 'side' => 'buy', 'price' => '100', 'amount' => '1'],
            ['symbol' => 'DOGE', 'side' => 'buy', 'price' => '100', 'amount' => '1'],
            
            // Invalid side
            ['symbol' => 'BTC', 'side' => 'invalid', 'price' => '100', 'amount' => '1'],
            ['symbol' => 'BTC', 'side' => '', 'price' => '100', 'amount' => '1'],
            ['symbol' => 'BTC', 'side' => 'long', 'price' => '100', 'amount' => '1'],
            
            // Invalid price
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '0', 'amount' => '1'],
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '-100', 'amount' => '1'],
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => 'invalid', 'amount' => '1'],
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '100.123456789', 'amount' => '1'], // Too many decimals
            
            // Invalid amount
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '100', 'amount' => '0'],
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '100', 'amount' => '-1'],
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '100', 'amount' => 'invalid'],
            ['symbol' => 'BTC', 'side' => 'buy', 'price' => '100', 'amount' => '1.123456789'], // Too many decimals
        ];
        
        return $invalidCases[array_rand($invalidCases)];
    }

    /**
     * Generate random valid price
     */
    private function generateRandomPrice(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(1, 99999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(0, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }

    /**
     * Generate random valid amount
     */
    private function generateRandomAmount(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(1, 9999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(0, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }
}