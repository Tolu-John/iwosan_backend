<?php

namespace App\Http\Requests\Tests;

use Illuminate\Foundation\Http\FormRequest;

class SearchTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => trim((string) $this->input('q')),
            'hospital_name' => trim((string) $this->input('hospital_name')),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'price_min' => 'nullable|numeric',
            'price_max' => 'nullable|numeric',
            'hospital_id' => 'nullable|integer',
            'hospital_name' => 'nullable|string|max:255',
            'rating_min' => 'nullable|numeric|min:0|max:5',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'max_distance_km' => 'nullable|numeric',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
