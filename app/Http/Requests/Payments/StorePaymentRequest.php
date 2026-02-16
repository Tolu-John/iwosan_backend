<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim((string) $this->input('status')),
            'method' => trim((string) $this->input('method')),
            'type' => trim((string) $this->input('type')),
            'reference' => trim((string) $this->input('reference')),
            'gateway' => trim((string) $this->input('gateway')),
            'status_reason' => trim((string) $this->input('status_reason')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'carer_id' => 'required|integer',
            'code' => 'required|string|max:255',
            'method' => 'required|string|max:50',
            'status' => 'required|string|in:pending,processing,paid,failed,cancelled,refund_pending,refunded',
            'price' => 'required',
            'type' => 'required|string|max:100',
            'type_id' => 'required|integer',
            'reuse' => 'nullable|boolean',
            'reference' => 'nullable|string|max:255',
            'gateway' => 'nullable|string|max:100',
            'verified' => 'nullable|boolean',
            'status_reason' => 'nullable|string|max:255',
        ];
    }
}
