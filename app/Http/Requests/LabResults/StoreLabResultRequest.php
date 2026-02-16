<?php

namespace App\Http\Requests\LabResults;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'lab_name' => trim((string) $this->input('lab_name')),
            'extra_notes' => trim((string) $this->input('extra_notes')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'carer_id' => 'nullable|integer',
            'teletest_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'lab_name' => 'required|string|max:255',
            'extra_notes' => 'nullable|string|max:5000',
            'source' => 'nullable|string|max:50',
            'file' => 'required_without:files|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'files' => 'required_without:file|array|max:2',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }
}
