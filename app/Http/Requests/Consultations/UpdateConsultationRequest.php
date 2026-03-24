<?php

namespace App\Http\Requests\Consultations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsultationRequest extends FormRequest
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
            'review_id' => 'nullable|integer',
            'status' => 'required|string|in:draft,pending_payment,scheduled,in_progress,completed,cancelled,no_show',
            'treatment_type' => 'required|string|in:Virtual visit,Home visit,Home visit Admitted',
            'diagnosis' => 'required|string|max:2000',
            'consult_notes' => 'required|string|max:5000',
            'date_time' => 'required|date',
            'vConsultation' => 'nullable|array',
            'vConsultation.consult_type' => 'nullable|string|max:50',
            'vConsultation.duration' => 'nullable|integer|min:1|max:720',
            'hConsultation' => 'nullable|array',
            'hConsultation.address' => 'nullable|string|max:500',
            'hConsultation.ward_id' => 'nullable|integer',
            'hConsultation.admitted' => 'nullable',
            'treatment_plan' => 'nullable|array',
            'treatment_plan.types' => 'nullable|array',
            'treatment_plan.medications' => 'nullable|array',
            'treatment_plan.lab_orders' => 'nullable|array',
            'treatment_plan.care_advice' => 'nullable|array',
            'treatment_plan.referral' => 'nullable|array',
            'treatment_plan.observation' => 'nullable|array',
            'treatment_plan.treatment_note' => 'nullable|string|max:2000',
            'treatment_plan.clinical_summary' => 'nullable|array',
            'treatment_plan.clinical_summary.chief_complaint' => 'nullable|string|max:2000',
            'treatment_plan.clinical_summary.chief_complaint_symptoms' => 'nullable|array',
            'treatment_plan.clinical_summary.chief_complaint_duration' => 'nullable|string|max:100',
            'treatment_plan.clinical_summary.chief_complaint_severity' => 'nullable|string|max:100',
            'treatment_plan.clinical_summary.history' => 'nullable|string|max:5000',
            'treatment_plan.clinical_summary.diagnosis' => 'nullable|string|max:2000',
            'treatment_plan.clinical_summary.risk_level' => 'nullable|string|max:100',
            'treatment_plan.clinical_summary.disposition' => 'nullable|string|max:100',
            'treatment_plan.clinical_summary.duration_bucket' => 'nullable|string|max:100',
            'treatment_plan.follow_up' => 'nullable|array',
            'treatment_plan.follow_up.next_step' => 'nullable|string|max:255',
            'treatment_plan.follow_up.timing' => 'nullable|string|max:255',
            'treatment_plan.follow_up.note' => 'nullable|string|max:2000',
            'treatment_plan.follow_up.warning_signs' => 'nullable|array',
            'status_reason' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('treatment_type');
            $virtual = $this->input('vConsultation');
            $home = $this->input('hConsultation');

            if ($type === 'Virtual visit') {
                if (!is_array($virtual)) {
                    $validator->errors()->add('vConsultation', 'vConsultation is required for virtual visits.');
                } else {
                    if (!isset($virtual['consult_type']) || trim((string) $virtual['consult_type']) === '') {
                        $validator->errors()->add('vConsultation.consult_type', 'consult_type is required.');
                    }
                    if (!isset($virtual['duration']) || !is_numeric($virtual['duration']) || (int) $virtual['duration'] < 1) {
                        $validator->errors()->add('vConsultation.duration', 'duration must be a positive integer.');
                    }
                }
                if ($home !== null) {
                    $validator->errors()->add('hConsultation', 'hConsultation is not allowed for virtual visits.');
                }
            }

            if (in_array($type, ['Home visit', 'Home visit Admitted'], true)) {
                if (!is_array($home)) {
                    $validator->errors()->add('hConsultation', 'hConsultation is required for home visits.');
                } else {
                    if (!isset($home['address']) || trim((string) $home['address']) === '') {
                        $validator->errors()->add('hConsultation.address', 'address is required.');
                    }
                    if (!array_key_exists('admitted', $home)) {
                        $validator->errors()->add('hConsultation.admitted', 'admitted is required.');
                    }
                    $admitted = $this->isTruthy($home['admitted'] ?? null);
                    $requiresWard = $type === 'Home visit Admitted' || $admitted;
                    if (
                        $requiresWard &&
                        (!array_key_exists('ward_id', $home) || $home['ward_id'] === null || $home['ward_id'] === '')
                    ) {
                        $validator->errors()->add('hConsultation.ward_id', 'ward_id is required when admitted.');
                    }
                }
                if ($virtual !== null) {
                    $validator->errors()->add('vConsultation', 'vConsultation is not allowed for home visits.');
                }
            }

            $status = trim((string) $this->input('status'));
            if ($status === 'completed') {
                $summary = $this->input('treatment_plan.clinical_summary');
                if (!is_array($summary)) {
                    $validator->errors()->add('treatment_plan.clinical_summary', 'clinical_summary is required when completing a consultation.');
                    return;
                }

                $chiefComplaint = trim((string) ($summary['chief_complaint'] ?? ''));
                if ($chiefComplaint === '') {
                    $validator->errors()->add('treatment_plan.clinical_summary.chief_complaint', 'chief_complaint is required when completing a consultation.');
                }

                $symptoms = $summary['chief_complaint_symptoms'] ?? null;
                if (!is_array($symptoms) || count(array_filter(array_map(static fn($v) => trim((string) $v), $symptoms))) === 0) {
                    $validator->errors()->add('treatment_plan.clinical_summary.chief_complaint_symptoms', 'At least one chief complaint symptom is required when completing a consultation.');
                }

                if (trim((string) ($summary['chief_complaint_duration'] ?? '')) === '') {
                    $validator->errors()->add('treatment_plan.clinical_summary.chief_complaint_duration', 'chief_complaint_duration is required when completing a consultation.');
                }

                if (trim((string) ($summary['chief_complaint_severity'] ?? '')) === '') {
                    $validator->errors()->add('treatment_plan.clinical_summary.chief_complaint_severity', 'chief_complaint_severity is required when completing a consultation.');
                }

                if (trim((string) ($summary['risk_level'] ?? '')) === '') {
                    $validator->errors()->add('treatment_plan.clinical_summary.risk_level', 'risk_level is required when completing a consultation.');
                }

                if (trim((string) ($summary['disposition'] ?? '')) === '') {
                    $validator->errors()->add('treatment_plan.clinical_summary.disposition', 'disposition is required when completing a consultation.');
                }

                if (trim((string) ($summary['duration_bucket'] ?? '')) === '') {
                    $validator->errors()->add('treatment_plan.clinical_summary.duration_bucket', 'duration_bucket is required when completing a consultation.');
                }
            }
        });
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
