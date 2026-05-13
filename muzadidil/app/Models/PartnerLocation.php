<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PartnerLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'accuracy_meters',
        'is_online',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
            'accuracy_meters' => 'integer',
        ];
    }

    /**
     * Spatial coordinates are written as raw POINT geometry and selected as
     * WKT (so we can decode lat/lng) — handled via custom accessor below.
     */
    protected $hidden = [
        'coordinates',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function upsertCoordinates(int $userId, float $latitude, float $longitude, ?int $accuracy = null): self
    {
        $point = sprintf('POINT(%F %F)', $longitude, $latitude);

        $existing = self::where('user_id', $userId)->first();

        if ($existing) {
            DB::table('partner_locations')
                ->where('id', $existing->id)
                ->update([
                    'coordinates' => DB::raw("ST_GeomFromText('{$point}')"),
                    'accuracy_meters' => $accuracy,
                    'last_seen_at' => now(),
                    'updated_at' => now(),
                ]);

            return $existing->refresh();
        }

        $id = DB::table('partner_locations')->insertGetId([
            'user_id' => $userId,
            'coordinates' => DB::raw("ST_GeomFromText('{$point}')"),
            'accuracy_meters' => $accuracy,
            'is_online' => false,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::findOrFail($id);
    }

    /**
     * Returns [lat, lng] or null if no coordinates stored.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function latLng(): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_X(coordinates) AS lng, ST_Y(coordinates) AS lat FROM partner_locations WHERE id = ?',
            [$this->id],
        );

        if (! $row || $row->lat === null) {
            return null;
        }

        return ['lat' => (float) $row->lat, 'lng' => (float) $row->lng];
    }
}
