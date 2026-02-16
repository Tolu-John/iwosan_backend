<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadHospitalImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => (int) $this->input('id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer',
            'file' => 'required|file|mimes:jpeg,png|max:2048',
        ];
    }
}
