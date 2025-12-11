<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    private BalanceService $balanceService;
    private AssetService $assetService;

    public function __construct(BalanceService $balanceService, AssetService $assetService)
    {
        $this->balanceService = $balanceService;
        $this->assetService = $assetService;
    }

    /**
     * Create a new order with validation and fund locking.
     */
    public function createOrder(int $userId, string $symbol, string $side, string $price, string $amount): Order
    {
        return DB::transaction(function () use ($userId, $symbol, $side, $price, $amount) {
            // Validate order data
            Order::validateOrderData([
                'symbol' => $symbol,
                'side' => $side,
                'price' => $price,
                'amount' => $amount
            ]);

            // Verify user exists
            $user = User::find($userId);
            if (!$user) {
                throw ValidationException::withMessages([
                    'user' => 'User not found.'
                ]);
            }

            // Lock funds or assets based on order side
            if ($side === 'buy') {
                // For buy orders, lock USD funds
                $totalCost = bcmul($price, $amount, 8);
                $this->balanceService->lockFunds($userId, $totalCost);
            } else {
                // For sell orders, lock assets
                $this->assetService->lockAssets($userId, $symbol, $amount);
            }

            // Create the order
            $order = Order::create([
                'user_id' => $userId,
                'symbol' => strtoupper($symbol),
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'status' => Order::STATUS_OPEN
            ]);

            return $order;
        });
    }

    /**
     * Cancel an open order and release locked funds/assets.
     */
    public function cancelOrder(int $orderId, int $userId): Order
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $order = Order::lockForUpdate()->find($orderId);

            if (!$order) {
                throw ValidationException::withMessages([
                    'order' => 'Order not found.'
                ]);
            }

            // Verify order belongs to user
            if ($order->user_id !== $userId) {
                throw ValidationException::withMessages([
                    'order' => 'Order does not belong to this user.'
                ]);
            }

            // Check if order is open
            if (!$order->isOpen()) {
                throw ValidationException::withMessages([
                    'order' => 'Only open orders can be cancelled.'
                ]);
            }

            // Release locked funds or assets
            if ($order->side === 'buy') {
                // Release locked USD funds
                $totalCost = bcmul($order->price, $order->amount, 8);
                $this->balanceService->releaseFunds($userId, $totalCost);
            } else {
                // Release locked assets
                $this->assetService->releaseAssets($userId, $order->symbol, $order->amount);
            }

            // Update order status to cancelled
            $order->update([
                'status' => Order::STATUS_CANCELLED
            ]);

            return $order;
        });
    }

    /**
     * Get orderbook for a specific symbol with proper sorting.
     */
    public function getOrderbook(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        // Validate symbol
        Asset::validateSymbol($symbol);

        // Get all open buy orders sorted by price descending
        $buyOrders = Order::where('symbol', $symbol)
            ->where('side', 'buy')
            ->where('status', Order::STATUS_OPEN)
            ->orderBy('price', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at
                ];
            })
            ->toArray();

        // Get all open sell orders sorted by price ascending
        $sellOrders = Order::where('symbol', $symbol)
            ->where('side', 'sell')
            ->where('status', Order::STATUS_OPEN)
            ->orderBy('price', 'asc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at
                ];
            })
            ->toArray();

        return [
            'symbol' => $symbol,
            'buy_orders' => $buyOrders,
            'sell_orders' => $sellOrders
        ];
    }

    /**
     * Get user's orders with optional filtering.
     */
    public function getUserOrders(int $userId, ?string $status = null): array
    {
        $query = Order::where('user_id', $userId);

        if ($status) {
            $statusMap = [
                'open' => Order::STATUS_OPEN,
                'filled' => Order::STATUS_FILLED,
                'cancelled' => Order::STATUS_CANCELLED
            ];

            if (!isset($statusMap[$status])) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid status filter.'
                ]);
            }

            $query->where('status', $statusMap[$status]);
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at
                ];
            })
            ->toArray();
    }

    /**
     * Mark an order as filled.
     */
    public function markOrderFilled(int $orderId): Order
    {
        $order = Order::find($orderId);

        if (!$order) {
            throw ValidationException::withMessages([
                'order' => 'Order not found.'
            ]);
        }

        $order->update(['status' => Order::STATUS_FILLED]);

        return $order;
    }

    /**
     * Get a single order by ID.
     */
    public function getOrder(int $orderId): Order
    {
        $order = Order::find($orderId);

        if (!$order) {
            throw ValidationException::withMessages([
                'order' => 'Order not found.'
            ]);
        }

        return $order;
    }
}
