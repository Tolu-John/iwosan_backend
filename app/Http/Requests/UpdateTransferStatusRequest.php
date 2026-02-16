<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransferStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => strtolower(trim((string) $this->input('status'))),
            'failure_reason' => trim((string) $this->input('failure_reason', '')),
            'reference' => trim((string) $this->input('reference', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,processing,paid,failed,reversed',
            'failure_reason' => 'nullable|string|max:2000',
            'reference' => 'nullable|string|max:255',
        ];
    }
}
