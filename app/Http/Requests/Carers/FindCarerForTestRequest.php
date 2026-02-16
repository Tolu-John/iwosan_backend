<?php

namespace App\Http\Requests\Carers;

use Illuminate\Foundation\Http\FormRequest;

class FindCarerForTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hospital_id' => 'required|integer',
            'visit_type' => 'nullable|string|in:home,virtual',
            'availability' => 'nullable|string|in:anytime,window',
            'availability_start' => 'required_if:availability,window|date',
            'availability_end' => 'required_if:availability,window|date',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
        ];
    }
}
