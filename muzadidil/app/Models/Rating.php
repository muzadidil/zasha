<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_PARTNER = 'partner';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'rater_id',
        'ratee_id',
        'rater_role',
        'stars',
        'comment',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }

    public function save(array $options = []): bool
    {
        if (! $this->created_at && ! $this->exists) {
            $this->created_at = now();
        }

        return parent::save($options);
    }
}
