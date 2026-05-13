<?php

namespace App\Http\Requests\Customer;

use App\Models\ServiceCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'service_category_slug' => ['required', Rule::exists('service_categories', 'slug')],
            'initial_price' => ['required', 'integer', 'min:0'],
            'pickup_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'destination_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'details' => ['required', 'array'],
            'details.*' => ['sometimes'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $slug = $this->input('service_category_slug');
            if (! $slug) {
                return;
            }
            $category = ServiceCategory::where('slug', $slug)->first();
            if (! $category) {
                return;
            }

            if ($category->requires_geolocation) {
                if (! is_numeric($this->input('pickup_lat')) || ! is_numeric($this->input('pickup_lng'))) {
                    $v->errors()->add('pickup_lat', 'Lokasi pickup wajib diisi untuk kategori ini.');
                }
            }

            if ($this->input('initial_price') < $category->min_price) {
                $v->errors()->add(
                    'initial_price',
                    "Harga di bawah minimum kategori (Rp {$category->min_price}).",
                );
            }

            $detailsRules = $this->detailRulesFor($slug);
            $detailsValidator = validator($this->input('details', []), $detailsRules);
            foreach ($detailsValidator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $v->errors()->add("details.{$field}", $message);
                }
            }
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function detailRulesFor(string $slug): array
    {
        return match ($slug) {
            ServiceCategory::SLUG_WFH => [
                'task_title' => ['required', 'string', 'min:10', 'max:200'],
                'task_description' => ['required', 'string', 'min:50', 'max:5000'],
                'deadline' => ['required', 'date', 'after:now'],
                'skills_required' => ['required', 'array', 'min:1'],
                'skills_required.*' => ['string', 'max:64'],
                'attachment_urls' => ['nullable', 'array', 'max:5'],
                'attachment_urls.*' => ['url', 'max:500'],
            ],
            ServiceCategory::SLUG_TITIP => [
                'pickup_address' => ['required', 'string', 'max:500'],
                'dropoff_address' => ['required', 'string', 'max:500'],
                'estimated_weight' => ['required', 'numeric', 'between:0.1,50'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.name' => ['required', 'string', 'max:120'],
                'items.*.qty' => ['required', 'integer', 'min:1'],
                'items.*.estimated_price' => ['nullable', 'integer', 'min:0'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            ServiceCategory::SLUG_TENAGA => [
                'job_type' => ['required', Rule::in(['angkut', 'bersih', 'bangunan', 'kebun', 'lainnya'])],
                'work_address' => ['required', 'string', 'max:500'],
                'duration_hours' => ['required', 'integer', 'between:1,12'],
                'worker_count' => ['required', 'integer', 'between:1,10'],
                'start_at' => ['required', 'date', 'after:now'],
                'tools_needed' => ['nullable', 'array'],
                'tools_needed.*' => ['string', 'max:64'],
                'description' => ['required', 'string', 'min:20', 'max:2000'],
            ],
            ServiceCategory::SLUG_SERVICE => [
                'service_type' => ['required', Rule::in(['elektronik', 'kendaraan', 'perabot', 'plumbing', 'listrik', 'lainnya'])],
                'device_or_item' => ['required', 'string', 'max:200'],
                'problem_description' => ['required', 'string', 'min:30', 'max:3000'],
                'location_type' => ['required', Rule::in(['on_site', 'dropoff'])],
                'service_address' => [
                    'required_if:location_type,on_site',
                    'nullable',
                    'string',
                    'max:500',
                ],
                'photos' => ['nullable', 'array', 'max:5'],
                'photos.*' => ['url', 'max:500'],
            ],
            default => [],
        };
    }
}
