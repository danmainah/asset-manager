<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **Feature: asset-manager, Property 12: Data Isolation**
     * 
     * For any authenticated user, they should only be able to access their own balance, 
     * orders, and trading data.
     * 
     * **Validates: Requirements 8.4**
     */
    public function test_data_isolation_property()
    {
        // Create a user with orders
        $user = User::factory()->create(['balance' => '1000.00000000']);
        $userOrders = Order::factory()->count(3)->create(['user_id' => $user->id]);

        // Create another user with different orders
        $otherUser = User::factory()->create(['balance' => '2000.00000000']);
        Order::factory()->count(2)->create(['user_id' => $otherUser->id]);

        // Create assets for the user
        Asset::factory()->create(['user_id' => $user->id, 'symbol' => 'BTC']);

        // Test that user can only see their own orders
        $this->assertUserCanOnlyAccessOwnOrders($user, $userOrders);
    }

    /**
     * Test that users can access their own profile data
     */
    public function test_users_can_access_own_profile()
    {
        $user = User::factory()->create(['balance' => '1000.00000000']);
        $token = $user->createToken('test-token')->plainTextToken;

        // User accesses their own profile
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->json('GET', '/api/profile');

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->json();
        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertEquals($user->balance, $data['balance']['usd_balance']);
    }

    /**
     * Test that users can only access their own orders
     */
    public function test_users_can_only_access_own_orders()
    {
        $user = User::factory()->create();
        $userOrders = Order::factory()->count(3)->create(['user_id' => $user->id]);
        
        // Create orders for another user
        $otherUser = User::factory()->create();
        Order::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $token = $user->createToken('test-token')->plainTextToken;

        // User retrieves their orders
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->json('GET', '/api/orders');

        $this->assertEquals(200, $response->getStatusCode());
        $orders = $response->json('orders');
        $this->assertCount(3, $orders);
        
        // Verify all returned orders belong to this user
        foreach ($orders as $order) {
            $this->assertTrue(
                $userOrders->pluck('id')->contains($order['id']),
                "User should only see their own orders"
            );
        }
    }

    /**
     * Test that users cannot cancel other users' orders
     */
    public function test_users_cannot_cancel_other_users_orders()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user2Order = Order::factory()->create([
            'user_id' => $user2->id,
            'status' => Order::STATUS_OPEN
        ]);

        $token1 = $user1->createToken('test-token')->plainTextToken;

        // User1 attempts to cancel user2's order
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
            'Accept' => 'application/json',
        ])->json('POST', "/api/orders/{$user2Order->id}/cancel");

        // Should fail with validation error
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('does not belong to this user', 
            json_encode($response->json()));

        // Verify order is still open
        $this->assertEquals(Order::STATUS_OPEN, $user2Order->fresh()->status);
    }

    /**
     * Test that users can only filter their own orders
     */
    public function test_users_can_only_filter_their_own_orders()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create orders with different statuses for user1
        Order::factory()->create(['user_id' => $user1->id, 'status' => Order::STATUS_OPEN]);
        Order::factory()->create(['user_id' => $user1->id, 'status' => Order::STATUS_OPEN]);
        Order::factory()->create(['user_id' => $user1->id, 'status' => Order::STATUS_FILLED]);

        // Create orders for user2
        Order::factory()->create(['user_id' => $user2->id, 'status' => Order::STATUS_OPEN]);
        Order::factory()->create(['user_id' => $user2->id, 'status' => Order::STATUS_CANCELLED]);

        $token1 = $user1->createToken('test-token')->plainTextToken;

        // User1 filters for open orders
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
            'Accept' => 'application/json',
        ])->json('GET', '/api/orders?status=open');

        $this->assertEquals(200, $response->getStatusCode());
        $orders = $response->json('orders');
        
        // Should only see their own open orders (2)
        $this->assertCount(2, $orders);
        foreach ($orders as $order) {
            $this->assertEquals(Order::STATUS_OPEN, $order['status']);
        }
    }

    /**
     * Property-based test: Generate random users and verify data isolation
     */
    public function test_data_isolation_with_random_users()
    {
        // Create a user with random orders
        $user = User::factory()->create();
        $orderCount = rand(1, 5);
        $userOrders = Order::factory()->count($orderCount)->create(['user_id' => $user->id]);
        
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->json('GET', '/api/orders');
        
        $this->assertEquals(200, $response->getStatusCode());
        $orders = $response->json('orders');
        
        // Verify all returned orders belong to this user
        $this->assertCount($orderCount, $orders);
        foreach ($orders as $order) {
            $this->assertTrue(
                $userOrders->pluck('id')->contains($order['id']),
                "User {$user->id} should only see their own orders"
            );
        }
    }

    /**
     * Helper method to assert user can only access their own orders
     */
    private function assertUserCanOnlyAccessOwnOrders(User $user, $userOrders): void
    {
        $token = $user->createToken('test-token')->plainTextToken;

        // Get user's orders
        $ordersResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->json('GET', '/api/orders');

        $this->assertEquals(200, $ordersResponse->getStatusCode());
        $orders = $ordersResponse->json('orders');
        
        // Verify all orders belong to this user
        foreach ($orders as $order) {
            $this->assertTrue(
                $userOrders->pluck('id')->contains($order['id']),
                "User {$user->id} should only see their own orders"
            );
        }
    }
}
