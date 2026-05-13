<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPartner() ?? false;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:50000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.required' => 'Latitude wajib diisi.',
            'lng.required' => 'Longitude wajib diisi.',
        ];
    }
}
