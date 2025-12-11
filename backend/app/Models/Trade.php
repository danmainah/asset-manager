<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'buy_order_id',
        'sell_order_id',
        'buyer_id',
        'seller_id',
        'symbol',
        'price',
        'amount',
        'volume',
        'commission',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'amount' => 'decimal:8',
            'volume' => 'decimal:8',
            'commission' => 'decimal:8',
        ];
    }

    /**
     * Get the buy order associated with the trade.
     */
    public function buyOrder()
    {
        return $this->belongsTo(Order::class, 'buy_order_id');
    }

    /**
     * Get the sell order associated with the trade.
     */
    public function sellOrder()
    {
        return $this->belongsTo(Order::class, 'sell_order_id');
    }

    /**
     * Get the buyer user.
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller user.
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
