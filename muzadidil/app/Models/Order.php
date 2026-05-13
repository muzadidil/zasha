<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SEARCHING = 'searching';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const ACTIVE_STATUSES = [
        self::STATUS_SEARCHING,
        self::STATUS_CLAIMED,
        self::STATUS_IN_PROGRESS,
    ];

    protected $fillable = [
        'customer_id',
        'service_category_id',
        'partner_id',
        'details',
        'current_price',
        'initial_price',
        'status',
        'active_radius_km',
        'current_step_index',
        'expires_at',
        'claimed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'current_price' => 'integer',
            'initial_price' => 'integer',
            'active_radius_km' => 'integer',
            'current_step_index' => 'integer',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'pickup_location',
        'destination_location',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(OrderClaim::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function radiusExpansions(): HasMany
    {
        return $this->hasMany(OrderRadiusExpansion::class);
    }

    /**
     * Decoded pickup coordinates, or null when not set (WFH).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function pickupLatLng(): ?array
    {
        return $this->decodePoint('pickup_location');
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function destinationLatLng(): ?array
    {
        return $this->decodePoint('destination_location');
    }

    private function decodePoint(string $column): ?array
    {
        $row = DB::selectOne(
            "SELECT ST_X({$column}) AS lng, ST_Y({$column}) AS lat FROM orders WHERE id = ?",
            [$this->id],
        );

        if (! $row || $row->lat === null) {
            return null;
        }

        return ['lat' => (float) $row->lat, 'lng' => (float) $row->lng];
    }
}
