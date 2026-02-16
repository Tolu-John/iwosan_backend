<?php

namespace App\Http\Requests\Consultations;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim((string) $this->input('status')),
            'treatment_type' => trim((string) $this->input('treatment_type')),
            'diagnosis' => trim((string) $this->input('diagnosis')),
            'consult_notes' => trim((string) $this->input('consult_notes')),
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
            'status' => 'required|string|in:pending_payment,scheduled,in_progress,completed,cancelled,no_show',
            'treatment_type' => 'required|string|in:Virtual visit,Home visit,Home visit Admitted',
            'diagnosis' => 'required|string|max:2000',
            'consult_notes' => 'required|string|max:5000',
            'date_time' => 'required|string|max:100',
            'vConsultation' => 'sometimes|array',
            'vConsultation.consult_type' => 'required_with:vConsultation|string|max:50',
            'vConsultation.duration' => 'required_with:vConsultation|string|max:50',
            'hConsultation' => 'sometimes|array',
            'hConsultation.address' => 'required_with:hConsultation|string|max:500',
            'hConsultation.ward_id' => 'required_with:hConsultation|integer',
            'hConsultation.admitted' => 'required_with:hConsultation',
            'status_reason' => 'nullable|string|max:255',
        ];
    }
}
