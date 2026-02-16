<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => trim((string) $this->input('type')),
            'recipient' => trim((string) $this->input('recipient')),
            'reason' => trim((string) $this->input('reason')),
            'reference' => trim((string) $this->input('reference')),
            'currency' => strtoupper(trim((string) $this->input('currency', 'NGN'))),
            'method' => trim((string) $this->input('method')),
        ]);
    }

    public function rules(): array
    {
        return [
            'payment_id' => 'required|integer',
            'type' => 'nullable|string|max:50',
            'type_id' => 'required|integer',
            'hospital_id' => 'required|integer',
            'carer_id' => 'required|integer',
            'recipient' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:2000',
            'reference' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'method' => 'nullable|string|max:50',
        ];
    }
}
