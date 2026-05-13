<?php

namespace App\Http\Requests\Partner;

use App\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPartner() ?? false;
    }

    public function rules(): array
    {
        return [
            'ktp_number' => ['required', 'string', 'regex:/^\d{16}$/'],
            'ktp_photo_url' => ['required', 'url', 'max:500'],
            'vehicle_info' => ['nullable', 'array'],
            'vehicle_info.type' => ['nullable', 'string', 'max:32'],
            'vehicle_info.plate' => ['nullable', 'string', 'max:16'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:64'],
            'service_categories' => ['required', 'array', 'min:1'],
            'service_categories.*' => ['string', Rule::in(ServiceCategory::query()->pluck('slug')->all())],
            'bank_name' => ['nullable', 'string', 'max:64'],
            'bank_account' => ['nullable', 'string', 'regex:/^\d{6,20}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'ktp_number.regex' => 'Nomor KTP harus 16 digit angka.',
            'ktp_photo_url.required' => 'Foto KTP wajib diisi.',
            'service_categories.required' => 'Pilih minimal satu kategori jasa yang dilayani.',
            'service_categories.*.in' => 'Kategori jasa tidak valid.',
            'bank_account.regex' => 'Nomor rekening hanya boleh berisi 6-20 digit angka.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->ktp_number) {
            $this->merge(['ktp_number' => preg_replace('/\s+/', '', (string) $this->ktp_number)]);
        }
        if ($this->skills) {
            $this->merge([
                'skills' => array_map(fn ($s) => Str::squish(strip_tags((string) $s)), (array) $this->skills),
            ]);
        }
    }
}
