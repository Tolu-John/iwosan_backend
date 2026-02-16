<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'complaint' => trim((string) $this->input('complaint')),
            'category' => strtolower(trim((string) $this->input('category', 'other'))),
            'severity' => strtolower(trim((string) $this->input('severity', 'low'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'hospital_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'complaint' => 'required|string|max:5000',
            'category' => 'required|in:care_quality,billing,behavior,safety,tech,other',
            'severity' => 'required|in:low,medium,high,critical',
        ];
    }
}
