<?php

namespace App\Services;

use App\Models\Consultation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConsultationClinicalRecordBuilder
{
    public function buildForConsultationId(?int $consultationId): array
    {
        if (!$consultationId) {
            return [];
        }

        $consultation = Consultation::find($consultationId);
        if (!$consultation) {
            return [];
        }

        if (!$this->hasRequiredTables()) {
            return [
                'summary' => [
                    'chief_complaint' => null,
                    'chief_complaint_symptoms' => [],
                    'chief_complaint_duration' => null,
                    'chief_complaint_severity' => null,
                    'history' => null,
                    'diagnosis' => $consultation->diagnosis,
                    'risk_level' => null,
                    'disposition' => null,
                    'duration_bucket' => null,
                ],
                'treatment_plan' => [
                    'plan_types' => [],
                    'clinician_note' => null,
                ],
                'plan_types' => [],
                'medications' => [],
                'lab_orders' => [],
                'referrals' => [],
                'care_advice' => [],
                'follow_up' => [
                    'next_step' => null,
                    'timing' => null,
                    'note' => null,
                    'warning_signs' => [],
                ],
                'observation' => [
                    'note' => null,
                    'warning_signs' => [],
                ],
                'observation_logs' => [],
            ];
        }

        $plans = DB::table('consultation_plans')
            ->where('consultation_id', $consultationId)
            ->orderByDesc('id')
            ->get();

        $planPayload = [];
        foreach ($plans as $plan) {
            $decoded = json_decode((string) ($plan->payload_json ?? ''), true);
            if (is_array($decoded) && !empty($decoded)) {
                $planPayload = $decoded;
                break;
            }
        }

        $summary = is_array($planPayload['clinical_summary'] ?? null)
            ? $planPayload['clinical_summary']
            : [];
        $followUp = is_array($planPayload['follow_up'] ?? null)
            ? $planPayload['follow_up']
            : [];
        $observation = is_array($planPayload['observation'] ?? null)
            ? $planPayload['observation']
            : [];
        $warningSignsRows = [];

        if (Schema::hasTable('consultation_clinical_summaries')) {
            $summaryRow = DB::table('consultation_clinical_summaries')
                ->where('consultation_id', $consultationId)
                ->orderByDesc('id')
                ->first();
            if ($summaryRow) {
                $summary = [
                    'chief_complaint' => $summaryRow->chief_complaint,
                    'chief_complaint_symptoms' => $this->decodeJsonArray($summaryRow->chief_complaint_symptoms),
                    'chief_complaint_duration' => $summaryRow->chief_complaint_duration,
                    'chief_complaint_severity' => $summaryRow->chief_complaint_severity,
                    'history' => $summaryRow->history,
                    'diagnosis' => $summaryRow->diagnosis,
                    'risk_level' => $summaryRow->risk_level,
                    'disposition' => $summaryRow->disposition,
                    'duration_bucket' => $summaryRow->duration_bucket,
                    'treatment_note' => $summaryRow->treatment_note,
                ];
            }
        }

        if (Schema::hasTable('consultation_follow_ups')) {
            $followUpRow = DB::table('consultation_follow_ups')
                ->where('consultation_id', $consultationId)
                ->orderByDesc('id')
                ->first();
            if ($followUpRow) {
                $followUp = [
                    'next_step' => $followUpRow->next_step,
                    'timing' => $followUpRow->timing,
                    'note' => $followUpRow->note,
                ];
            }
        }

        if (Schema::hasTable('consultation_warning_signs')) {
            $warningSignsRows = DB::table('consultation_warning_signs')
                ->where('consultation_id', $consultationId)
                ->orderBy('id')
                ->pluck('label')
                ->values()
                ->all();
        }

        $medications = DB::table('consultation_medications')
            ->where('consultation_id', $consultationId)
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->drug_name,
                    'strength_value' => $row->strength_value,
                    'strength_unit' => $row->strength_unit,
                    'dose_amount' => $row->dose_amount,
                    'dose_unit' => $row->dose_unit,
                    'route' => $row->route,
                    'formulation' => $row->formulation,
                    'frequency_code' => $row->frequency_code,
                    'duration_days' => $row->duration_days,
                    'prn' => (bool) $row->prn,
                    'max_daily_dose' => $row->max_daily_dose,
                    'indication' => $row->indication,
                    'instructions' => $row->instructions,
                ];
            })
            ->values()
            ->all();

        $labOrders = DB::table('consultation_lab_orders')
            ->where('consultation_id', $consultationId)
            ->orderBy('id')
            ->get()
            ->map(function ($order) {
                $tests = DB::table('consultation_lab_order_items')
                    ->where('lab_order_id', $order->id)
                    ->orderBy('id')
                    ->pluck('test_name')
                    ->values()
                    ->all();

                return [
                    'id' => (int) $order->id,
                    'urgency' => $order->urgency,
                    'fasting_required' => (bool) $order->fasting_required,
                    'clinical_question' => $order->clinical_question,
                    'tests' => $tests,
                ];
            })
            ->values()
            ->all();

        $referrals = DB::table('consultation_referrals')
            ->where('consultation_id', $consultationId)
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'destination_type' => $row->destination_type,
                    'specialty' => $row->specialty,
                    'reason' => $row->reason,
                    'urgency' => $row->urgency,
                    'status' => $row->status,
                ];
            })
            ->values()
            ->all();

        $careAdvice = DB::table('consultation_care_advices')
            ->where('consultation_id', $consultationId)
            ->orderBy('id')
            ->pluck('label')
            ->values()
            ->all();

        $observations = DB::table('consultation_observations')
            ->where('consultation_id', $consultationId)
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $payload = json_decode((string) ($row->monitoring_payload ?? ''), true);
                return is_array($payload) ? $payload : [];
            })
            ->filter(fn ($payload) => !empty($payload))
            ->values()
            ->all();

        $planTypes = $plans
            ->pluck('plan_type')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
        if (empty($planTypes) && isset($planPayload['types']) && is_array($planPayload['types'])) {
            $planTypes = collect($planPayload['types'])
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->values()
                ->all();
        }

        if (empty($medications) && isset($planPayload['medications']) && is_array($planPayload['medications'])) {
            $medications = collect($planPayload['medications'])
                ->filter(fn ($row) => is_array($row))
                ->map(function ($row) {
                    return [
                        'id' => null,
                        'name' => $row['name'] ?? null,
                        'strength_value' => $row['strength_value'] ?? $row['strengthValue'] ?? null,
                        'strength_unit' => $row['strength_unit'] ?? $row['strengthUnit'] ?? null,
                        'dose_amount' => $row['dose_amount'] ?? $row['doseAmount'] ?? null,
                        'dose_unit' => $row['dose_unit'] ?? $row['doseUnit'] ?? null,
                        'route' => $row['route'] ?? null,
                        'formulation' => $row['formulation'] ?? $row['form'] ?? null,
                        'frequency_code' => $row['frequency_code'] ?? $row['frequencyCode'] ?? null,
                        'duration_days' => $row['duration_days'] ?? $row['durationDays'] ?? null,
                        'prn' => (bool) ($row['prn'] ?? false),
                        'max_daily_dose' => $row['max_daily_dose'] ?? $row['maxDailyDose'] ?? null,
                        'indication' => $row['indication'] ?? null,
                        'instructions' => $row['instructions'] ?? $row['specialInstructions'] ?? null,
                    ];
                })
                ->values()
                ->all();
        }

        if (empty($labOrders) && isset($planPayload['lab_orders']) && is_array($planPayload['lab_orders'])) {
            $labOrders = collect($planPayload['lab_orders'])
                ->filter(fn ($row) => is_array($row))
                ->map(function ($row) {
                    $tests = isset($row['tests']) && is_array($row['tests'])
                        ? collect($row['tests'])->map(fn ($v) => trim((string) $v))->filter()->values()->all()
                        : [];
                    return [
                        'id' => null,
                        'urgency' => $row['urgency'] ?? 'routine',
                        'fasting_required' => (bool) ($row['fasting_required'] ?? $row['fastingRequired'] ?? false),
                        'clinical_question' => $row['clinical_question'] ?? $row['clinicalQuestion'] ?? null,
                        'tests' => $tests,
                    ];
                })
                ->values()
                ->all();
        }

        if (empty($labOrders) && isset($planPayload['labOrderTests']) && is_array($planPayload['labOrderTests'])) {
            $labOrders[] = [
                'id' => null,
                'urgency' => $planPayload['labUrgency'] ?? 'routine',
                'fasting_required' => (bool) ($planPayload['labFastingRequired'] ?? false),
                'clinical_question' => $planPayload['labClinicalQuestion'] ?? null,
                'tests' => collect($planPayload['labOrderTests'])
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        if (empty($referrals) && isset($planPayload['referral']) && is_array($planPayload['referral'])) {
            $referrals[] = [
                'id' => null,
                'destination_type' => $planPayload['referral']['destination_type']
                    ?? $planPayload['referral']['destinationType']
                    ?? $planPayload['referral']['target']
                    ?? null,
                'specialty' => $planPayload['referral']['specialty'] ?? null,
                'reason' => $planPayload['referral']['reason'] ?? null,
                'urgency' => $planPayload['referral']['urgency'] ?? 'routine',
                'status' => $planPayload['referral']['status'] ?? 'active',
            ];
        }

        if (empty($careAdvice) && isset($planPayload['care_advice']) && is_array($planPayload['care_advice'])) {
            $careAdvice = collect($planPayload['care_advice'])
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->values()
                ->all();
        }
        if (empty($careAdvice) && isset($planPayload['careAdvice']) && is_array($planPayload['careAdvice'])) {
            $careAdvice = collect($planPayload['careAdvice'])
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->values()
                ->all();
        }

        $chiefComplaint = $summary['chief_complaint'] ?? $planPayload['chiefComplaint'] ?? null;
        $chiefComplaintSymptoms = $summary['chief_complaint_symptoms'] ?? $planPayload['chiefComplaintSymptoms'] ?? [];
        $chiefComplaintDuration = $summary['chief_complaint_duration'] ?? $planPayload['chiefComplaintDuration'] ?? null;
        $chiefComplaintSeverity = $summary['chief_complaint_severity'] ?? $planPayload['chiefComplaintSeverity'] ?? null;
        $history = $summary['history'] ?? $planPayload['history'] ?? null;
        $diagnosis = $summary['diagnosis'] ?? $planPayload['diagnosis'] ?? $consultation->diagnosis;
        $riskLevel = $summary['risk_level'] ?? $planPayload['riskLevel'] ?? null;
        $disposition = $summary['disposition'] ?? $planPayload['disposition'] ?? null;
        $durationBucket = $summary['duration_bucket'] ?? $planPayload['durationBucket'] ?? null;

        $followUpNextStep = $followUp['next_step'] ?? $planPayload['nextStep'] ?? null;
        $followUpTiming = $followUp['timing'] ?? $planPayload['followUpTiming'] ?? null;
        $followUpNote = $followUp['note'] ?? $planPayload['followUpNote'] ?? null;
        $warningSigns = !empty($warningSignsRows)
            ? $warningSignsRows
            : ($followUp['warning_signs']
            ?? $observation['warningSigns']
            ?? $planPayload['warningSigns']
            ?? []);
        $treatmentNote = $summary['treatment_note'] ?? $planPayload['treatment_note'] ?? $planPayload['treatmentNote'] ?? null;

        return [
            'summary' => [
                'chief_complaint' => $chiefComplaint,
                'chief_complaint_symptoms' => is_array($chiefComplaintSymptoms) ? $chiefComplaintSymptoms : [],
                'chief_complaint_duration' => $chiefComplaintDuration,
                'chief_complaint_severity' => $chiefComplaintSeverity,
                'history' => $history,
                'diagnosis' => $diagnosis,
                'risk_level' => $riskLevel,
                'disposition' => $disposition,
                'duration_bucket' => $durationBucket,
            ],
            'treatment_plan' => [
                'plan_types' => $planTypes,
                'clinician_note' => $treatmentNote,
            ],
            'plan_types' => $planTypes,
            'medications' => $medications,
            'lab_orders' => $labOrders,
            'referrals' => $referrals,
            'care_advice' => $careAdvice,
            'follow_up' => [
                'next_step' => $followUpNextStep,
                'timing' => $followUpTiming,
                'note' => $followUpNote,
                'warning_signs' => is_array($warningSigns) ? $warningSigns : [],
            ],
            'observation' => [
                'note' => $observation['note'] ?? null,
                'warning_signs' => $observation['warningSigns'] ?? [],
            ],
            'observation_logs' => $observations,
        ];
    }

    private function hasRequiredTables(): bool
    {
        $required = [
            'consultation_plans',
            'consultation_medications',
            'consultation_lab_orders',
            'consultation_lab_order_items',
            'consultation_referrals',
            'consultation_care_advices',
            'consultation_observations',
        ];

        foreach ($required as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }
        return true;
    }

    private function decodeJsonArray($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
