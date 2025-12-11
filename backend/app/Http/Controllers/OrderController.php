<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Services\MatchingEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private OrderService $orderService;
    private MatchingEngine $matchingEngine;

    public function __construct(OrderService $orderService, MatchingEngine $matchingEngine)
    {
        $this->orderService = $orderService;
        $this->matchingEngine = $matchingEngine;
    }

    /**
     * Create a new order.
     * 
     * Requirements: 2.3, 2.4, 8.4
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        try {
            // Validate request input
            $validated = $request->validate([
                'symbol' => 'required|string|in:BTC,ETH',
                'side' => 'required|string|in:buy,sell',
                'price' => 'required|numeric|min:0.00000001',
                'amount' => 'required|numeric|min:0.00000001',
            ]);

            // Create the order
            $order = $this->orderService->createOrder(
                $userId,
                $validated['symbol'],
                $validated['side'],
                (string)$validated['price'],
                (string)$validated['amount']
            );

            return response()->json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create order',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get orderbook for a specific symbol.
     * 
     * Requirements: 3.1, 3.2, 3.3
     */
    public function getOrderbook(Request $request): JsonResponse
    {
        try {
            // Validate request input
            $validated = $request->validate([
                'symbol' => 'required|string|in:BTC,ETH',
            ]);

            $orderbook = $this->orderService->getOrderbook($validated['symbol']);

            return response()->json($orderbook, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve orderbook',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's orders with optional filtering.
     * 
     * Requirements: 8.4
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        try {
            // Validate optional status filter
            $validated = $request->validate([
                'status' => 'nullable|string|in:open,filled,cancelled',
            ]);

            $orders = $this->orderService->getUserOrders($userId, $validated['status'] ?? null);

            return response()->json([
                'orders' => $orders,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve orders',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel an open order.
     * 
     * Requirements: 4.1, 4.2, 4.3, 4.4
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        try {
            $order = $this->orderService->cancelOrder($orderId, $userId);

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => [
                    'id' => $order->id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'updated_at' => $order->updated_at,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to cancel order',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Internal endpoint to trigger order matching.
     * This is called by the system to process pending orders.
     * 
     * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
     */
    public function match(Request $request): JsonResponse
    {
        try {
            // This endpoint should only be called internally
            // In a production system, this would be protected by a system token or internal-only middleware
            
            $validated = $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
            ]);

            $order = $this->orderService->getOrder($validated['order_id']);

            // Process matching
            $trade = $this->matchingEngine->processNewOrder($order);

            if ($trade) {
                return response()->json([
                    'message' => 'Order matched successfully',
                    'trade' => [
                        'id' => $trade->id,
                        'buy_order_id' => $trade->buy_order_id,
                        'sell_order_id' => $trade->sell_order_id,
                        'price' => $trade->price,
                        'amount' => $trade->amount,
                        'commission' => $trade->commission,
                    ],
                ], 200);
            } else {
                return response()->json([
                    'message' => 'No matching orders found',
                ], 200);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process matching',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
