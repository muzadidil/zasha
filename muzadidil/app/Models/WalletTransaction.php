<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_TOPUP = 'topup';

    public const TYPE_FEE = 'fee';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PartnerWallet::class, 'wallet_id');
    }

    /**
     * Audit trail rows are immutable. Saving an existing record throws.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('WalletTransaction rows are immutable.');
        }

        if (! $this->created_at) {
            $this->created_at = now();
        }

        return parent::save($options);
    }
}
