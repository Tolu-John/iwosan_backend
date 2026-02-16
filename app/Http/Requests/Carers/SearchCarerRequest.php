<?php

namespace App\Http\Requests\Carers;

use Illuminate\Foundation\Http\FormRequest;

class SearchCarerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => trim((string) $this->input('q')),
            'position' => trim((string) $this->input('position')),
            'hospital_name' => trim((string) $this->input('hospital_name')),
            'gender' => trim((string) $this->input('gender')),
            'visit_type' => trim((string) $this->input('visit_type')),
            'availability' => trim((string) $this->input('availability')),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'hospital_id' => 'nullable|integer',
            'hospital_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:20',
            'visit_type' => 'required|string|in:home,virtual',
            'availability' => 'required|string|in:anytime,window',
            'availability_start' => 'required_if:availability,window|date',
            'availability_end' => 'required_if:availability,window|date',
            'price_min' => 'nullable|numeric',
            'price_max' => 'nullable|numeric',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'max_distance_km' => 'nullable|numeric',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
