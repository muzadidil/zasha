<?php

namespace App\Services\Partner;

use App\Exceptions\Auth\AuthException;
use App\Models\PartnerProfile;
use App\Models\User;

class PartnerProfileService
{
    public function upsert(User $partner, array $data): PartnerProfile
    {
        if (! $partner->isPartner()) {
            throw AuthException::forbiddenRole([User::ROLE_PARTNER]);
        }

        // Deterministic hash supports a unique constraint over encrypted KTP numbers
        // without leaking plaintext in the index.
        $ktpHash = hash('sha256', $data['ktp_number']);

        $profile = PartnerProfile::where('user_id', $partner->id)->first();

        $payload = [
            'user_id' => $partner->id,
            'ktp_number' => $data['ktp_number'],
            'ktp_number_hash' => $ktpHash,
            'ktp_photo_url' => $data['ktp_photo_url'],
            'vehicle_info' => $data['vehicle_info'] ?? null,
            'skills' => $data['skills'] ?? [],
            'service_categories' => $data['service_categories'],
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
        ];

        if ($profile) {
            // Re-verification required whenever the partner edits core identity fields.
            if ($profile->ktp_number_hash !== $ktpHash) {
                $payload['is_verified'] = false;
                $payload['verified_at'] = null;
            }
            $profile->fill($payload)->save();

            return $profile->fresh();
        }

        $profile = PartnerProfile::create($payload);

        return $profile->fresh();
    }
}
