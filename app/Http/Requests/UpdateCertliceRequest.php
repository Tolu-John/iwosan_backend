<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertliceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type') ?? $this->input('owner_type');
        $typeId = $this->input('type_id') ?? $this->input('owner_id');
        $certType = $this->input('cert_type') ?? $this->input('certificate_type') ?? $this->input('type');

        $this->merge([
            'type' => $type ? strtolower(trim((string) $type)) : null,
            'type_id' => $typeId,
            'cert_type' => $certType ? trim((string) $certType) : null,
            'issuer' => trim((string) $this->input('issuer', '')),
            'license_number' => trim((string) $this->input('license_number', '')),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:carer,hospital',
            'type_id' => 'required|integer',
            'cert_type' => 'required|string|max:255',
            'issuer' => 'required|string|max:255',
            'license_number' => 'required|string|max:255',
            'issued_at' => 'required|date',
            'expires_at' => 'nullable|date|after_or_equal:issued_at',
            'notes' => 'nullable|string',
            'file_name' => 'required|string|max:255',
            'file' => 'nullable|mimes:jpeg,png,pdf',
        ];
    }
}
