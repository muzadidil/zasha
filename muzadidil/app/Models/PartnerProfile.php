<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ktp_number',
        'ktp_number_hash',
        'ktp_photo_url',
        'vehicle_info',
        'skills',
        'service_categories',
        'bank_name',
        'bank_account',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'ktp_number' => 'encrypted',
            'bank_account' => 'encrypted',
            'vehicle_info' => 'array',
            'skills' => 'array',
            'service_categories' => 'array',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'ktp_number_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function servesCategory(string $slug): bool
    {
        return in_array($slug, $this->service_categories ?? [], true);
    }
}
