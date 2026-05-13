<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasFactory;

    public const SLUG_WFH = 'wfh';

    public const SLUG_TITIP = 'titip';

    public const SLUG_TENAGA = 'tenaga';

    public const SLUG_SERVICE = 'service';

    protected $fillable = [
        'slug',
        'name',
        'min_price',
        'price_step',
        'max_partners',
        'requires_geolocation',
        'search_radius_km',
        'search_timeout_minutes',
        'commission_percent',
    ];

    protected function casts(): array
    {
        return [
            'min_price' => 'integer',
            'price_step' => 'integer',
            'max_partners' => 'integer',
            'requires_geolocation' => 'boolean',
            'search_radius_km' => 'integer',
            'search_timeout_minutes' => 'integer',
            'commission_percent' => 'decimal:2',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function feeFor(int $price): int
    {
        return (int) floor($price * ((float) $this->commission_percent) / 100);
    }
}
