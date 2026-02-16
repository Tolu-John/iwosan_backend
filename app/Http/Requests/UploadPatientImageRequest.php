<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPatientImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => (int) $this->input('user_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer',
            'file' => 'required|file|mimes:jpeg,png,pdf|max:2048',
        ];
    }
}
