<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:8',
        ];
    }

    /**
     * Get the user's assets.
     */
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get the user's orders.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Validate balance amount for financial precision.
     */
    public static function validateBalance($balance)
    {
        if (!is_numeric($balance)) {
            throw ValidationException::withMessages([
                'balance' => 'Balance must be a valid number.'
            ]);
        }

        if (bccomp($balance, '0', 8) < 0) {
            throw ValidationException::withMessages([
                'balance' => 'Balance cannot be negative.'
            ]);
        }

        // Ensure precision doesn't exceed 8 decimal places
        $parts = explode('.', (string)$balance);
        if (isset($parts[1]) && strlen($parts[1]) > 8) {
            throw ValidationException::withMessages([
                'balance' => 'Balance precision cannot exceed 8 decimal places.'
            ]);
        }

        return true;
    }

    /**
     * Check if user has sufficient balance for an amount.
     */
    public function hasSufficientBalance($amount)
    {
        return bccomp($this->balance, $amount, 8) >= 0;
    }
}
