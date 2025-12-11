<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
