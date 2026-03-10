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
            'address_lat' => trim((string) $this->input('address_lat')),
            'address_lon' => trim((string) $this->input('address_lon')),
            'owned_by_role' => trim((string) $this->input('owned_by_role')),
            'owned_by_id' => trim((string) $this->input('owned_by_id')),
            'next_action_at' => trim((string) $this->input('next_action_at')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'carer_id' => 'required|integer',
            'status' => 'required|string|in:requested,triage,insurance_pending,insurance_approved,insurance_rejected,pending,payment_pending,pending_payment,scheduled,in_progress,completed,cancelled,no_show',
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
            'consent_accepted' => 'required|accepted',
            'attachments' => 'nullable',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,heic,heif,webp|max:20480',
            'attachments_payload' => 'nullable|array|max:5',
            'attachments_payload.*.file_base64' => 'required_with:attachments_payload|string',
            'attachments_payload.*.file_name' => 'nullable|string|max:255',
            'address_lat' => 'nullable|numeric|between:-90,90',
            'address_lon' => 'nullable|numeric|between:-180,180',
            'owned_by_role' => 'nullable|string|in:patient,carer,hospital,ops,system',
            'owned_by_id' => 'nullable|integer|min:1',
            'next_action_at' => 'nullable|date',
        ];
    }
}
