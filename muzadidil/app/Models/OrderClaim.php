<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderClaim extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'partner_id',
        'agreed_price',
        'fee_amount',
        'status',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'agreed_price' => 'integer',
            'fee_amount' => 'integer',
            'claimed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
