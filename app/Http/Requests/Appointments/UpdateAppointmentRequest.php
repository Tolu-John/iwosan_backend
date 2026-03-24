<?php

namespace App\Http\Requests\Appointments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
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
            'dispatch_model' => trim((string) $this->input('dispatch_model')),
            'address_source' => trim((string) $this->input('address_source')),
            'contact_profile' => trim((string) $this->input('contact_profile')),
            'visit_reason' => trim((string) $this->input('visit_reason')),
            'preferred_window' => trim((string) $this->input('preferred_window')),
            'expected_duration' => trim((string) $this->input('expected_duration')),
            'preferred_hospital_name' => trim((string) $this->input('preferred_hospital_name')),
            'preferred_clinician_name' => trim((string) $this->input('preferred_clinician_name')),
            'preference_note' => trim((string) $this->input('preference_note')),
            'additional_notes' => trim((string) $this->input('additional_notes')),
            'visit_contact_name' => trim((string) $this->input('visit_contact_name')),
            'visit_contact_phone' => trim((string) $this->input('visit_contact_phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'carer_id' => 'required|integer',
            'status' => ['required', 'string', Rule::in($this->allowedStatuses())],
            'address' => 'required|string|max:500',
            'price' => 'required',
            'consult_type' => 'required|string|max:50',
            'appointment_type' => 'required|string|max:50',
            'extra_notes' => 'required|string|max:2000',
            'date_time' => 'required|string|max:100',
            'ward_id' => 'nullable|integer',
            'admin_approved' => 'required',
            'payment_id' => 'nullable|integer',
            'consult_id' => 'nullable|integer',
            'channel' => 'nullable|string|max:50',
            'status_reason' => 'nullable|string|max:255',
            'consent_accepted' => 'nullable|boolean',
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
            'dispatch_model' => 'nullable|string|in:system_ops_assignment,manual_assignment',
            'address_source' => 'nullable|string|in:onboarding_profile,manual_entry',
            'contact_profile' => 'nullable|string|in:onboarding_defaults,custom',
            'visit_reason' => 'nullable|string|max:1000',
            'preferred_window' => 'nullable|string|max:120',
            'expected_duration' => 'nullable|string|max:120',
            'red_flags_json' => 'nullable|array|max:10',
            'red_flags_json.*' => 'string|max:120',
            'preferred_hospital_id' => 'nullable|integer|exists:hospitals,id',
            'preferred_hospital_name' => 'nullable|string|max:255',
            'preferred_clinician_id' => 'nullable|integer|exists:carers,id',
            'preferred_clinician_name' => 'nullable|string|max:255',
            'preference_note' => 'nullable|string|max:500',
            'additional_notes' => 'nullable|string|max:1000',
            'visit_contact_name' => 'nullable|string|max:255',
            'visit_contact_phone' => 'nullable|string|max:80',
        ];
    }

    /**
     * @return string[]
     */
    private function allowedStatuses(): array
    {
        $legacy = [
            'requested',
            'triage',
            'insurance_pending',
            'insurance_approved',
            'insurance_rejected',
            'pending',
            'payment_pending',
            'pending_payment',
            'scheduled',
            'in_progress',
            'completed',
            'cancelled',
            'no_show',
        ];

        $workflowStatuses = array_keys((array) config('home_visit_workflow.statuses', []));
        $aliases = array_keys((array) config('home_visit_workflow.status_aliases', []));

        return array_values(array_unique(array_merge($legacy, $workflowStatuses, $aliases)));
    }
}
