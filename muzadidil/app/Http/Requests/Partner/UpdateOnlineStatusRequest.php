<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOnlineStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPartner() ?? false;
    }

    public function rules(): array
    {
        return [
            'is_online' => ['required', 'boolean'],
        ];
    }
}
