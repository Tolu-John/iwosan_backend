<?php

namespace App\Http\Requests\Teletests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeletestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'address' => trim((string) $this->input('address')),
            'test_name' => trim((string) $this->input('test_name')),
            'status' => trim((string) $this->input('status')),
            'date_time' => trim((string) $this->input('date_time')),
            'status_reason' => trim((string) $this->input('status_reason')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'carer_id' => 'required|integer',
            'hospital_id' => 'required|integer',
            'payment_id' => 'nullable|integer',
            'review_id' => 'required|integer',
            'address' => 'required|string|max:500',
            'test_name' => 'required|string|max:255',
            'status' => 'required|string|in:pending_payment,scheduled,in_progress,completed,cancelled,no_show',
            'date_time' => 'required|string|max:100',
            'admin_approved' => 'required',
            'status_reason' => 'nullable|string|max:255',
        ];
    }
}
