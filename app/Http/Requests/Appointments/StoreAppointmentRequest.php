<?php

namespace App\Http\Requests\Appointments;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'address' => trim((string) $this->input('address')),
            'status' => trim((string) $this->input('status')),
            'consult_type' => trim((string) $this->input('consult_type')),
            'appointment_type' => trim((string) $this->input('appointment_type')),
            'date_time' => trim((string) $this->input('date_time')),
            'extra_notes' => trim((string) $this->input('extra_notes')),
            'channel' => trim((string) $this->input('channel')),
            'status_reason' => trim((string) $this->input('status_reason')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'carer_id' => 'required|integer',
            'status' => 'required|string|in:pending_payment,scheduled,in_progress,completed,cancelled,no_show',
            'address' => 'required|string|max:500',
            'price' => 'required',
            'consult_type' => 'required|string|max:50',
            'appointment_type' => 'required|string|max:50',
            'date_time' => 'required|string|max:100',
            'extra_notes' => 'required|string|max:2000',
            'admin_approved' => 'required',
            'payment_id' => 'nullable|integer',
            'consult_id' => 'nullable|integer',
            'channel' => 'nullable|string|max:50',
            'status_reason' => 'nullable|string|max:255',
        ];
    }
}
