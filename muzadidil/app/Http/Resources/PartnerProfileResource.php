<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            // KTP number is decrypted by the cast; we mask it before sending to the client
            // because the partner UI only needs to confirm the last four digits.
            'ktp_number_masked' => $this->maskKtp((string) $this->ktp_number),
            'ktp_photo_url' => $this->ktp_photo_url,
            'vehicle_info' => $this->vehicle_info,
            'skills' => $this->skills,
            'service_categories' => $this->service_categories,
            'bank_name' => $this->bank_name,
            'bank_account_masked' => $this->maskBank((string) ($this->bank_account ?? '')),
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }

    private function maskKtp(string $value): string
    {
        return $value ? str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4) : '';
    }

    private function maskBank(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
