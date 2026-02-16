<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCertliceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status') ? strtolower(trim((string) $this->input('status'))) : null,
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:verified,rejected',
            'reason' => 'nullable|string|max:1000',
        ];
    }
}
