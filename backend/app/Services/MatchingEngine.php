<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Trade;
use App\Models\AuditLog;
use App\Events\OrderMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchingEngine
{
    private BalanceService $balanceService;
    private AssetService $assetService;

    public function __construct(BalanceService $balanceService, AssetService $assetService)
    {
        $this->balanceService = $balanceService;
        $this->assetService = $assetService;
    }

    /**
     * Process a new order and attempt to match it with existing orders.
     * Implements price-priority matching algorithm.
     */
    public function processNewOrder(Order $order): ?Trade
    {
        return DB::transaction(function () use ($order) {
            // Refresh order to ensure we have latest data
            $order = Order::lockForUpdate()->find($order->id);

            if (!$order->isOpen()) {
                return null;
            }

            // Find matching counter-order
            $counterOrder = $this->findBestCounterOrder($order);

            if (!$counterOrder) {
                return null;
            }

            // Execute the match
            return $this->executeMatch($order, $counterOrder);
        });
    }

    /**
     * Find the best counter-order for matching.
     * For buy orders: find lowest-priced sell order where sell price <= buy price
     * For sell orders: find highest-priced buy order where buy price >= sell price
     */
    private function findBestCounterOrder(Order $order): ?Order
    {
        if ($order->side === 'buy') {
            // Find all sell orders that could match
            $potentialMatches = Order::lockForUpdate()
                ->where('symbol', $order->symbol)
                ->where('side', 'sell')
                ->where('status', Order::STATUS_OPEN)
                ->orderBy('price', 'asc')
                ->get();
            
            // Filter by price using bccomp for precision
            foreach ($potentialMatches as $match) {
                if (bccomp($match->price, $order->price, 8) <= 0) {
                    return $match;
                }
            }
            return null;
        } else {
            // Find all buy orders that could match
            $potentialMatches = Order::lockForUpdate()
                ->where('symbol', $order->symbol)
                ->where('side', 'buy')
                ->where('status', Order::STATUS_OPEN)
                ->orderBy('price', 'desc')
                ->get();
            
            // Filter by price using bccomp for precision
            foreach ($potentialMatches as $match) {
                if (bccomp($match->price, $order->price, 8) >= 0) {
                    return $match;
                }
            }
            return null;
        }
    }

    /**
     * Execute a full match between two orders.
     * Transfers assets and USD, calculates commission, and marks orders as filled.
     */
    private function executeMatch(Order $buyOrder, Order $sellOrder): Trade
    {
        // Determine which order is buy and which is sell
        if ($buyOrder->side === 'sell') {
            [$buyOrder, $sellOrder] = [$sellOrder, $buyOrder];
        }

        // Verify both orders are still open
        if (!$buyOrder->isOpen() || !$sellOrder->isOpen()) {
            throw ValidationException::withMessages([
                'order' => 'One or both orders are no longer open.'
            ]);
        }

        // Verify amounts match (full-match-only)
        if (bccomp($buyOrder->amount, $sellOrder->amount, 8) !== 0) {
            throw ValidationException::withMessages([
                'order' => 'Orders must have matching amounts for full execution.'
            ]);
        }

        // Calculate trade details
        $matchPrice = $sellOrder->price; // Sell order price takes precedence
        $matchAmount = $buyOrder->amount;
        $volume = bcmul($matchPrice, $matchAmount, 8);
        $commission = bcmul($volume, '0.015', 8); // 1.5% commission

        try {
            // Transfer assets from seller to buyer
            $this->assetService->transferAssets(
                $sellOrder->user_id,
                $buyOrder->user_id,
                $buyOrder->symbol,
                $matchAmount
            );

            // Calculate USD amount (at match price)
            $usdAmount = bcmul($matchPrice, $matchAmount, 8);

            // Release locked funds from buyer (they were locked during order creation)
            $this->balanceService->releaseFunds($buyOrder->user_id, $usdAmount);

            // Transfer USD from buyer to seller (minus commission)
            $usdAfterCommission = bcsub($usdAmount, $commission, 8);
            $this->balanceService->transferUSD(
                $buyOrder->user_id,
                $sellOrder->user_id,
                $usdAfterCommission
            );

            // Deduct commission from buyer
            $this->balanceService->deductCommission($buyOrder->user_id, $commission);

            // Mark both orders as filled
            $buyOrder->update(['status' => Order::STATUS_FILLED]);
            $sellOrder->update(['status' => Order::STATUS_FILLED]);

            // Create trade record
            $trade = Trade::create([
                'buy_order_id' => $buyOrder->id,
                'sell_order_id' => $sellOrder->id,
                'buyer_id' => $buyOrder->user_id,
                'seller_id' => $sellOrder->user_id,
                'symbol' => $buyOrder->symbol,
                'price' => $matchPrice,
                'amount' => $matchAmount,
                'volume' => $volume,
                'commission' => $commission,
            ]);

            // Audit log for buyer
            AuditLog::create([
                'user_id' => $buyOrder->user_id,
                'action' => 'TRADE_EXECUTED_BUY',
                'entity_type' => Trade::class,
                'entity_id' => $trade->id,
                'details' => [
                    'order_id' => $buyOrder->id,
                    'symbol' => $buyOrder->symbol,
                    'price' => $matchPrice,
                    'amount' => $matchAmount,
                    'volume' => $volume,
                    'commission' => $commission,
                ],
                'ip_address' => request()->ip(),
            ]);

            // Audit log for seller
            AuditLog::create([
                'user_id' => $sellOrder->user_id,
                'action' => 'TRADE_EXECUTED_SELL',
                'entity_type' => Trade::class,
                'entity_id' => $trade->id,
                'details' => [
                    'order_id' => $sellOrder->id,
                    'symbol' => $sellOrder->symbol,
                    'price' => $matchPrice,
                    'amount' => $matchAmount,
                    'volume' => $volume,
                ],
                'ip_address' => request()->ip(),
            ]);

            return $trade;
        } catch (\Exception $e) {
            // Transaction will be rolled back automatically
            throw $e;
        }
    }

    /**
     * Broadcast order matched event to both trading parties.
     * Called after successful trade execution.
     */
    public function broadcastOrderMatched(Trade $trade): void
    {
        try {
            // Broadcast to buyer
            $buyer = $trade->buyer;
            $buyerBalance = ['usd_balance' => $buyer->balance];
            $buyerAssets = $this->assetService->getUserAssets($buyer->id);
            
            broadcast(new OrderMatched($trade, $buyer, $buyerBalance, $buyerAssets))->toOthers();

            // Broadcast to seller
            $seller = $trade->seller;
            $sellerBalance = ['usd_balance' => $seller->balance];
            $sellerAssets = $this->assetService->getUserAssets($seller->id);
            
            broadcast(new OrderMatched($trade, $seller, $sellerBalance, $sellerAssets))->toOthers();
        } catch (\Exception $e) {
            // Log the error but don't fail the trade execution
            \Log::warning('Failed to broadcast OrderMatched event: ' . $e->getMessage());
        }
    }

    /**
     * Get all trades for a specific symbol.
     */
    public function getTradesBySymbol(string $symbol): array
    {
        return Trade::where('symbol', strtoupper($symbol))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($trade) {
                return [
                    'id' => $trade->id,
                    'buy_order_id' => $trade->buy_order_id,
                    'sell_order_id' => $trade->sell_order_id,
                    'buyer_id' => $trade->buyer_id,
                    'seller_id' => $trade->seller_id,
                    'symbol' => $trade->symbol,
                    'price' => $trade->price,
                    'amount' => $trade->amount,
                    'volume' => $trade->volume,
                    'commission' => $trade->commission,
                    'created_at' => $trade->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get all trades for a specific user.
     */
    public function getUserTrades(int $userId): array
    {
        return Trade::where('buyer_id', $userId)
            ->orWhere('seller_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($trade) {
                return [
                    'id' => $trade->id,
                    'buy_order_id' => $trade->buy_order_id,
                    'sell_order_id' => $trade->sell_order_id,
                    'buyer_id' => $trade->buyer_id,
                    'seller_id' => $trade->seller_id,
                    'symbol' => $trade->symbol,
                    'price' => $trade->price,
                    'amount' => $trade->amount,
                    'volume' => $trade->volume,
                    'commission' => $trade->commission,
                    'created_at' => $trade->created_at,
                ];
            })
            ->toArray();
    }
}
