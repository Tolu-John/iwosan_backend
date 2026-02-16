<?php

namespace App\Http\Requests\LabTests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'test_name' => trim((string) $this->input('test_name')),
            'lab_recomm' => trim((string) $this->input('lab_recomm')),
            'extra_notes' => trim((string) $this->input('extra_notes')),
            'status' => trim((string) $this->input('status')),
        ]);
    }

    public function rules(): array
    {
        return [
            'consultation_id' => 'nullable|integer|required_without:ward_id',
            'ward_id' => 'nullable|integer|required_without:consultation_id',
            'test_name' => 'required|string|max:255',
            'lab_recomm' => 'required|string|max:2000',
            'extra_notes' => 'nullable|string|max:5000',
            'status' => 'required|string|in:ordered,scheduled,collected,resulted',
            'status_reason' => 'nullable|string|max:500',
        ];
    }
}
