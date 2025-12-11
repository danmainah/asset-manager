<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'amount',
        'locked_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'locked_amount' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns the asset.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validate asset amount for financial precision.
     */
    public static function validateAmount($amount)
    {
        if (!is_numeric($amount)) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be a valid number.'
            ]);
        }

        if (bccomp($amount, '0', 8) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount cannot be negative.'
            ]);
        }

        // Ensure precision doesn't exceed 8 decimal places
        $parts = explode('.', (string)$amount);
        if (isset($parts[1]) && strlen($parts[1]) > 8) {
            throw ValidationException::withMessages([
                'amount' => 'Amount precision cannot exceed 8 decimal places.'
            ]);
        }

        return true;
    }

    /**
     * Validate symbol is supported.
     */
    public static function validateSymbol($symbol)
    {
        $supportedSymbols = ['BTC', 'ETH'];
        
        if (!in_array(strtoupper($symbol), $supportedSymbols)) {
            throw ValidationException::withMessages([
                'symbol' => 'Symbol must be one of: ' . implode(', ', $supportedSymbols)
            ]);
        }

        return true;
    }

    /**
     * Get available amount (total - locked).
     */
    public function getAvailableAmountAttribute()
    {
        return bcsub($this->amount, $this->locked_amount, 8);
    }

    /**
     * Check if asset has sufficient available amount.
     */
    public function hasSufficientAmount($requiredAmount)
    {
        return bccomp($this->available_amount, $requiredAmount, 8) >= 0;
    }
}
