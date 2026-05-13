<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRadiusExpansion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_radius_km',
        'to_radius_km',
        'step_index',
        'partners_notified',
        'expanded_at',
    ];

    protected function casts(): array
    {
        return [
            'from_radius_km' => 'integer',
            'to_radius_km' => 'integer',
            'step_index' => 'integer',
            'partners_notified' => 'integer',
            'expanded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
