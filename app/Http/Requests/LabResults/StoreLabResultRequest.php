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
            'file' => 'nullable|file|max:20480',
            'files' => 'nullable|array|max:2',
            'files.*' => 'file|max:20480',
            'file_base64' => 'nullable|string',
            'file_name' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasSingle = $this->hasFile('file');
            $hasMultiple = is_array($this->file('files')) && count(array_filter($this->file('files'))) > 0;
            $hasBase64 = trim((string) $this->input('file_base64')) !== '';
            if (!$hasSingle && !$hasMultiple && !$hasBase64) {
                $validator->errors()->add('file', 'Attach at least one file.');
            }
        });
    }
}
