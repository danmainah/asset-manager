<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use HasFactory;

    const STATUS_OPEN = 1;
    const STATUS_FILLED = 2;
    const STATUS_CANCELLED = 3;

    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'amount' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validate order data for creation.
     */
    public static function validateOrderData($data)
    {
        // Validate symbol
        Asset::validateSymbol($data['symbol']);

        // Validate side
        if (!in_array($data['side'], ['buy', 'sell'])) {
            throw ValidationException::withMessages([
                'side' => 'Side must be either "buy" or "sell".'
            ]);
        }

        // Validate price
        if (!is_numeric($data['price']) || bccomp($data['price'], '0', 8) <= 0) {
            throw ValidationException::withMessages([
                'price' => 'Price must be a positive number.'
            ]);
        }

        // Validate amount
        if (!is_numeric($data['amount']) || bccomp($data['amount'], '0', 8) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be a positive number.'
            ]);
        }

        // Ensure precision doesn't exceed 8 decimal places for price
        $priceParts = explode('.', (string)$data['price']);
        if (isset($priceParts[1]) && strlen($priceParts[1]) > 8) {
            throw ValidationException::withMessages([
                'price' => 'Price precision cannot exceed 8 decimal places.'
            ]);
        }

        // Ensure precision doesn't exceed 8 decimal places for amount
        $amountParts = explode('.', (string)$data['amount']);
        if (isset($amountParts[1]) && strlen($amountParts[1]) > 8) {
            throw ValidationException::withMessages([
                'amount' => 'Amount precision cannot exceed 8 decimal places.'
            ]);
        }

        return true;
    }

    /**
     * Check if order is open.
     */
    public function isOpen()
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if order is filled.
     */
    public function isFilled()
    {
        return $this->status === self::STATUS_FILLED;
    }

    /**
     * Check if order is cancelled.
     */
    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get the total value of the order (price * amount).
     */
    public function getTotalValueAttribute()
    {
        return bcmul($this->price, $this->amount, 8);
    }
}
