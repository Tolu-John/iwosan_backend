<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => strtolower(trim((string) $this->input('status'))),
            'response_notes' => trim((string) $this->input('response_notes', '')),
            'resolution_notes' => trim((string) $this->input('resolution_notes', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:open,in_review,resolved,closed,rejected',
            'response_notes' => 'nullable|string|max:5000',
            'resolution_notes' => 'nullable|string|max:5000',
            'rejection_reason' => 'nullable|string|max:5000',
        ];
    }
}
