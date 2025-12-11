<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $orderData = $this->generateRandomValidOrderData();
            $user = User::factory()->create();
            
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

    public function test_order_status_consistency()
    {
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
    }

    private function assertOrderCreationConsistency(Order $order, User $user, array $orderData, Carbon $beforeCreation, Carbon $afterCreation): void
    {
        $order = $order->fresh();
        
        $this->assertNotNull($order->id);
        $this->assertEquals($user->id, $order->user_id);
        $this->assertEquals($orderData['symbol'], $order->symbol);
        $this->assertEquals($orderData['side'], $order->side);
        $this->assertEquals(
            number_format($orderData['price'], 8, '.', ''),
            number_format($order->price, 8, '.', '')
        );
        $this->assertEquals(
            number_format($orderData['amount'], 8, '.', ''),
            number_format($order->amount, 8, '.', '')
        );
        $this->assertEquals(Order::STATUS_OPEN, $order->status);
        $this->assertTrue($order->isOpen());
        $this->assertNotNull($order->created_at);
        $this->assertNotNull($order->updated_at);
        $this->assertTrue(
            $order->created_at->between($beforeCreation->subSecond(), $afterCreation->addSecond()),
            'Order created_at should be within reasonable time range'
        );
        $this->assertEquals($user->id, $order->user->id);
    }

    private function generateRandomValidOrderData(): array
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