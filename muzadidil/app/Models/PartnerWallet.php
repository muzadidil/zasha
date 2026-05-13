<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerWallet extends Model
{
    use HasFactory;

    public const MAX_BALANCE = 100_000;

    protected $fillable = [
        'user_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    public function topupRequests(): HasMany
    {
        return $this->hasMany(TopupRequest::class, 'wallet_id');
    }

    public function canAfford(int $amount): bool
    {
        return $this->balance >= $amount;
    }
}
