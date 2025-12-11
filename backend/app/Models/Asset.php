<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
