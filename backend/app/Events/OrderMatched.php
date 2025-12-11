<?php

namespace App\Events;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;
    public User $user;
    public array $userBalance;
    public array $userAssets;

    /**
     * Create a new event instance.
     */
    public function __construct(Trade $trade, User $user, array $userBalance, array $userAssets)
    {
        $this->trade = $trade;
        $this->user = $user;
        $this->userBalance = $userBalance;
        $this->userAssets = $userAssets;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'trade' => [
                'id' => $this->trade->id,
                'buy_order_id' => $this->trade->buy_order_id,
                'sell_order_id' => $this->trade->sell_order_id,
                'buyer_id' => $this->trade->buyer_id,
                'seller_id' => $this->trade->seller_id,
                'symbol' => $this->trade->symbol,
                'price' => $this->trade->price,
                'amount' => $this->trade->amount,
                'volume' => $this->trade->volume,
                'commission' => $this->trade->commission,
                'created_at' => $this->trade->created_at,
            ],
            'user_balance' => $this->userBalance,
            'user_assets' => $this->userAssets,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }
}
