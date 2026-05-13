<?php

namespace App\Http\Requests\Partner;

use App\Models\PartnerWallet;
use Illuminate\Foundation\Http\FormRequest;

class TopupRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPartner() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1000', 'max:'.PartnerWallet::MAX_BALANCE],
            'proof_url' => ['required', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal top-up wajib diisi.',
            'amount.min' => 'Nominal top-up minimum Rp 1.000.',
            'amount.max' => 'Nominal top-up maksimum Rp '.number_format(PartnerWallet::MAX_BALANCE, 0, ',', '.').'.',
            'proof_url.required' => 'URL bukti transfer wajib diisi.',
        ];
    }
}
