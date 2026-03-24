<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\Carer;
use App\Models\HConsultation;
use App\Models\Payment;
use App\Models\VConsultation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsultationService
{
    public const TYPE_VIRTUAL = 'Virtual visit';
    public const TYPE_HOME = 'Home visit';
    public const TYPE_HOME_ADMITTED = 'Home visit Admitted';

    private const STATUS_DESCRIPTIONS = [
        'draft' => 'Draft',
        'pending_payment' => 'Awaiting payment',
        'scheduled' => 'Scheduled',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ];

    private const STATUS_TRANSITIONS = [
        'draft' => ['pending_payment', 'scheduled', 'in_progress', 'completed', 'cancelled'],
        'pending_payment' => ['draft', 'scheduled', 'cancelled'],
        'scheduled' => ['draft', 'in_progress', 'completed', 'cancelled', 'no_show'],
        'in_progress' => ['draft', 'completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    private StatusChangeService $statusChanges;
    private NotificationService $notifications;

    public function __construct(StatusChangeService $statusChanges, NotificationService $notifications)
    {
        $this->statusChanges = $statusChanges;
        $this->notifications = $notifications;
    }

    public function create(array $data, AccessService $access): Consultation
    {
        $currentPatientId = $access->currentPatientId();
        $currentCarerId = $access->currentCarerId();
        $currentHospitalId = $access->currentHospitalId();

        if (!$currentPatientId && !$currentCarerId && !$currentHospitalId) {
            abort(403, 'Forbidden');
        }

        if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        if ($currentCarerId && (int) $data['carer_id'] !== (int) $currentCarerId) {
            abort(403, 'Forbidden');
        }

        if ($currentHospitalId && (int) $data['hospital_id'] !== (int) $currentHospitalId) {
            abort(403, 'Forbidden');
        }

        $this->assertCarerBelongsToHospital($data['carer_id'], $data['hospital_id']);

        $this->assertValidTreatmentType($data['treatment_type']);
        $paymentId = $data['payment_id'] ?? null;
        $this->assertPaymentState($data['status'], $paymentId);

        return DB::transaction(function () use ($data, $paymentId) {
            $consultation = new Consultation();
            $this->fillConsultation($consultation, $data, true);
            $this->applyStatusMetadata($consultation, null, $data['status']);
            $consultation->save();

            $this->syncSubtype($consultation, $data);
            $this->syncTreatmentPlanArtifacts($consultation, $data['treatment_plan'] ?? null);
            $this->recordStatusChange($consultation, null, $data['status'], $data['status_reason'] ?? null);

            return $consultation;
        });
    }

    public function update(Consultation $consultation, array $data, AccessService $access): Consultation
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $consultation->patient_id !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        $currentCarerId = $access->currentCarerId();
        if ($currentCarerId && (int) $consultation->carer_id !== (int) $currentCarerId) {
            abort(403, 'Forbidden');
        }

        $currentHospitalId = $access->currentHospitalId();
        if ($currentHospitalId && (int) $consultation->hospital_id !== (int) $currentHospitalId) {
            abort(403, 'Forbidden');
        }

        if ((int) $consultation->patient_id !== (int) $data['patient_id']) {
            abort(403, 'Forbidden');
        }

        if ((int) $consultation->carer_id !== (int) $data['carer_id']) {
            abort(403, 'Forbidden');
        }

        if ((int) $consultation->hospital_id !== (int) $data['hospital_id']) {
            abort(403, 'Forbidden');
        }

        if ($data['treatment_type'] !== $consultation->treatment_type) {
            abort(422, 'Treatment type cannot be changed.');
        }

        $this->assertCarerBelongsToHospital($data['carer_id'], $data['hospital_id']);
        $fromStatus = $consultation->status;
        $nextStatus = $data['status'];
        $paymentId = $data['payment_id'] ?? $consultation->payment_id;
        $this->assertStatusTransitionAllowed($consultation, $fromStatus, $nextStatus, $paymentId, $access);

        return DB::transaction(function () use ($consultation, $data, $fromStatus, $nextStatus, $paymentId) {
            $this->assertPaymentState($data['status'], $paymentId);
            $this->fillConsultation($consultation, $data, false);
            $this->applyStatusMetadata($consultation, $fromStatus, $nextStatus);
            $consultation->save();

            $this->syncSubtype($consultation, $data);
            $this->syncTreatmentPlanArtifacts($consultation, $data['treatment_plan'] ?? null);
            $this->handlePaymentSideEffects($paymentId, $fromStatus, $nextStatus);
            $this->recordStatusChange($consultation, $fromStatus, $nextStatus, $data['status_reason'] ?? null);

            return $consultation;
        });
    }

    private function fillConsultation(Consultation $consultation, array $data, bool $isCreate): void
    {
        $consultation->patient_id = $data['patient_id'];
        $consultation->carer_id = $data['carer_id'];
        $consultation->hospital_id = $data['hospital_id'];
        $consultation->status = $data['status'];
        if ($isCreate || array_key_exists('payment_id', $data)) {
            $consultation->payment_id = $data['payment_id'] ?? null;
        }
        $consultation->treatment_type = $data['treatment_type'];
        $consultation->diagnosis = $data['diagnosis'];
        $consultation->consult_notes = $data['consult_notes'];
        $consultation->date_time = $data['date_time'];

        if (!$isCreate && array_key_exists('review_id', $data) && $data['review_id'] !== null) {
            $consultation->review_id = $data['review_id'];
        }
    }

    private function applyStatusMetadata(Consultation $consultation, ?string $fromStatus, string $toStatus): void
    {
        $consultation->status_description = self::STATUS_DESCRIPTIONS[$toStatus] ?? $toStatus;

        if ($fromStatus === $toStatus) {
            return;
        }

        $now = Carbon::now();

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed'], true) && !$consultation->scheduled_at) {
            $consultation->scheduled_at = $now;
        }

        if (in_array($toStatus, ['in_progress', 'completed'], true) && !$consultation->started_at) {
            $consultation->started_at = $now;
        }

        if ($toStatus === 'completed' && !$consultation->completed_at) {
            $consultation->completed_at = $now;
        }

        if ($toStatus === 'cancelled' && !$consultation->cancelled_at) {
            $consultation->cancelled_at = $now;
        }

        if ($toStatus === 'no_show' && !$consultation->no_show_at) {
            $consultation->no_show_at = $now;
        }
    }

    private function syncSubtype(Consultation $consultation, array $data): void
    {
        if ($consultation->treatment_type === self::TYPE_VIRTUAL) {
            if (empty($data['vConsultation'])) {
                abort(422, 'vConsultation is required for virtual visits.');
            }

            $v = VConsultation::firstOrNew(['consultation_id' => $consultation->id]);
            $v->consultation_id = $consultation->id;
            $v->consult_type = $data['vConsultation']['consult_type'];
            $v->duration = $data['vConsultation']['duration'];
            $v->save();
            return;
        }

        if (in_array($consultation->treatment_type, [self::TYPE_HOME, self::TYPE_HOME_ADMITTED], true)) {
            if (empty($data['hConsultation'])) {
                abort(422, 'hConsultation is required for home visits.');
            }

            $homePayload = (array) $data['hConsultation'];
            $admitted = $this->isTruthy($homePayload['admitted'] ?? null);
            $requiresWard = $consultation->treatment_type === self::TYPE_HOME_ADMITTED || $admitted;
            $wardProvided = array_key_exists('ward_id', $homePayload)
                && $homePayload['ward_id'] !== null
                && $homePayload['ward_id'] !== '';

            if ($requiresWard && !$wardProvided) {
                abort(422, 'ward_id is required when admitted.');
            }

            $existing = HConsultation::where('consultation_id', $consultation->id)->first();
            if (!$requiresWard && !$wardProvided && !$existing) {
                // Non-admitted home consultation can be recorded without ward linkage.
                return;
            }

            $h = $existing ?? new HConsultation();
            $h->consultation_id = $consultation->id;
            $h->address = $homePayload['address'];
            $h->admitted = $admitted ? 1 : 0;
            if ($wardProvided) {
                $h->ward_id = (int) $homePayload['ward_id'];
            }
            $h->save();
        }
    }

    private function assertValidTreatmentType(string $type): void
    {
        if (!in_array($type, [self::TYPE_VIRTUAL, self::TYPE_HOME, self::TYPE_HOME_ADMITTED], true)) {
            abort(422, 'Invalid treatment type.');
        }
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

    private function assertCarerBelongsToHospital(int $carerId, int $hospitalId): void
    {
        $carer = Carer::find($carerId);
        if (!$carer || (int) $carer->hospital_id !== (int) $hospitalId) {
            abort(422, 'Carer does not belong to hospital.');
        }
    }

    private function assertPaymentState(string $status, $paymentId): void
    {
        if (!in_array($status, ['scheduled', 'in_progress', 'completed'], true)) {
            return;
        }

        if (empty($paymentId)) {
            abort(422, 'payment_id is required before scheduling.');
        }

        $payment = Payment::find($paymentId);
        if (!$payment || $payment->status !== 'paid') {
            abort(422, 'Payment must be verified before scheduling.');
        }
    }

    private function assertStatusTransitionAllowed(
        Consultation $consultation,
        string $fromStatus,
        string $toStatus,
        ?int $paymentId,
        AccessService $access
    ): void {
        if ($fromStatus === $toStatus) {
            return;
        }

        $allowedNext = self::STATUS_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($toStatus, $allowedNext, true)) {
            abort(422, "Invalid consultation status transition: {$fromStatus} -> {$toStatus}.");
        }

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed'], true)) {
            $this->assertPaymentState($toStatus, $paymentId);
        }

        if ($toStatus === 'cancelled') {
            $this->assertCanCancel($consultation, $fromStatus, $access);
        }

        if ($toStatus === 'no_show') {
            $this->assertCanMarkNoShow($consultation, $fromStatus, $access);
        }
    }

    private function assertCanCancel(Consultation $consultation, string $fromStatus, AccessService $access): void
    {
        if (!in_array($fromStatus, ['draft', 'pending_payment', 'scheduled', 'in_progress'], true)) {
            abort(422, 'Only draft, pending, scheduled, or in-progress consultations can be cancelled.');
        }

        $now = Carbon::now();
        $consultationTime = $this->parseDateTime($consultation->date_time);
        $role = $this->resolveActorRole($access);

        if ($role === 'patient') {
            if ($fromStatus === 'scheduled' && $consultationTime && $now->greaterThan($consultationTime->copy()->subHours(6))) {
                abort(422, 'Patients can only cancel at least 6 hours before the consultation.');
            }
            return;
        }

        if (in_array($role, ['carer', 'hospital'], true)) {
            if ($fromStatus === 'scheduled' && $consultationTime && $now->greaterThanOrEqualTo($consultationTime)) {
                abort(422, 'Cannot cancel once the consultation start time has passed.');
            }
            return;
        }

        abort(403, 'Forbidden');
    }

    private function assertCanMarkNoShow(Consultation $consultation, string $fromStatus, AccessService $access): void
    {
        if ($fromStatus !== 'scheduled') {
            abort(422, 'Only scheduled consultations can be marked as no-show.');
        }

        $role = $this->resolveActorRole($access);
        if (!in_array($role, ['carer', 'hospital'], true)) {
            abort(403, 'Forbidden');
        }

        $consultationTime = $this->parseDateTime($consultation->date_time);
        if ($consultationTime && Carbon::now()->lessThan($consultationTime->copy()->addHour())) {
            abort(422, 'No-show can only be marked at least 1 hour after the scheduled time.');
        }
    }

    private function handlePaymentSideEffects(?int $paymentId, string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        if ($toStatus !== 'cancelled' || !$paymentId) {
            return;
        }

        $payment = Payment::find($paymentId);
        if ($payment && $payment->status === 'paid') {
            $payment->status = 'refund_pending';
            $payment->save();
        }
    }

    private function recordStatusChange(Consultation $consultation, ?string $fromStatus, string $toStatus, ?string $reason): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        $this->statusChanges->record('consultation', $consultation->id, $fromStatus, $toStatus, $reason);
        $this->notifications->notifyStatusChange(
            'consultation',
            $consultation->id,
            $fromStatus,
            $toStatus,
            ['reason' => $reason]
        );
    }

    private function resolveActorRole(AccessService $access): ?string
    {
        if ($access->currentPatientId()) {
            return 'patient';
        }

        if ($access->currentCarerId()) {
            return 'carer';
        }

        if ($access->currentHospitalId()) {
            return 'hospital';
        }

        return null;
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function syncTreatmentPlanArtifacts(Consultation $consultation, ?array $plan): void
    {
        if (!is_array($plan)) {
            return;
        }

        DB::table('consultation_plans')
            ->where('consultation_id', $consultation->id)
            ->delete();
        DB::table('consultation_medications')
            ->where('consultation_id', $consultation->id)
            ->delete();
        DB::table('consultation_lab_orders')
            ->where('consultation_id', $consultation->id)
            ->delete();
        DB::table('consultation_referrals')
            ->where('consultation_id', $consultation->id)
            ->delete();
        DB::table('consultation_care_advices')
            ->where('consultation_id', $consultation->id)
            ->delete();
        DB::table('consultation_observations')
            ->where('consultation_id', $consultation->id)
            ->delete();
        if (\Illuminate\Support\Facades\Schema::hasTable('consultation_clinical_summaries')) {
            DB::table('consultation_clinical_summaries')
                ->where('consultation_id', $consultation->id)
                ->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('consultation_follow_ups')) {
            DB::table('consultation_follow_ups')
                ->where('consultation_id', $consultation->id)
                ->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('consultation_warning_signs')) {
            DB::table('consultation_warning_signs')
                ->where('consultation_id', $consultation->id)
                ->delete();
        }

        $now = now();
        $notesFollowUp = $this->extractLineValue($consultation->consult_notes, 'Follow-up:');
        $types = [];
        if (isset($plan['types']) && is_array($plan['types'])) {
            foreach ($plan['types'] as $type) {
                $value = trim((string) $type);
                if ($value !== '') {
                    $types[$value] = true;
                }
            }
        }

        $planIds = [];
        foreach (array_keys($types) as $type) {
            $planId = DB::table('consultation_plans')->insertGetId([
                'consultation_id' => $consultation->id,
                'plan_type' => $type,
                'status' => 'active',
                'version' => 1,
                'payload_json' => json_encode($plan, JSON_UNESCAPED_UNICODE),
                'entered_by' => $consultation->carer_id,
                'entered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $planIds[$type] = $planId;
        }

        if (isset($plan['medications']) && is_array($plan['medications'])) {
            foreach ($plan['medications'] as $med) {
                if (!is_array($med)) {
                    continue;
                }
                $drugName = trim((string) ($med['name'] ?? ''));
                if ($drugName === '') {
                    continue;
                }
                DB::table('consultation_medications')->insert([
                    'consultation_id' => $consultation->id,
                    'plan_id' => $planIds['medication'] ?? null,
                    'drug_name' => $drugName,
                    'strength_value' => is_numeric($med['strengthValue'] ?? null) ? (float) $med['strengthValue'] : null,
                    'strength_unit' => $med['strengthUnit'] ?? null,
                    'dose_amount' => is_numeric($med['doseAmount'] ?? null) ? (float) $med['doseAmount'] : null,
                    'dose_unit' => $med['doseUnit'] ?? null,
                    'route' => $med['route'] ?? null,
                    'formulation' => $med['form'] ?? null,
                    'frequency_code' => $med['frequencyCode'] ?? null,
                    'duration_days' => is_numeric($med['durationDays'] ?? null) ? (int) $med['durationDays'] : null,
                    'prn' => (bool) ($med['prn'] ?? false),
                    'max_daily_dose' => $med['maxDailyDose'] ?? null,
                    'indication' => $med['indication'] ?? null,
                    'instructions' => $med['specialInstructions'] ?? null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (isset($plan['lab_orders']) && is_array($plan['lab_orders'])) {
            foreach ($plan['lab_orders'] as $order) {
                if (!is_array($order)) {
                    continue;
                }
                $orderId = DB::table('consultation_lab_orders')->insertGetId([
                    'consultation_id' => $consultation->id,
                    'plan_id' => $planIds['lab_order'] ?? null,
                    'urgency' => trim((string) ($order['urgency'] ?? 'routine')) ?: 'routine',
                    'fasting_required' => (bool) ($order['fastingRequired'] ?? false),
                    'clinical_question' => $order['clinicalQuestion'] ?? null,
                    'status' => 'ordered',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (isset($order['tests']) && is_array($order['tests'])) {
                    foreach ($order['tests'] as $test) {
                        $testName = trim((string) $test);
                        if ($testName === '') {
                            continue;
                        }
                        DB::table('consultation_lab_order_items')->insert([
                            'lab_order_id' => $orderId,
                            'test_name' => $testName,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }

        if (isset($plan['care_advice']) && is_array($plan['care_advice'])) {
            foreach ($plan['care_advice'] as $advice) {
                $label = trim((string) $advice);
                if ($label === '') {
                    continue;
                }
                DB::table('consultation_care_advices')->insert([
                    'consultation_id' => $consultation->id,
                    'plan_id' => $planIds['care_advice'] ?? null,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (isset($plan['referral']) && is_array($plan['referral'])) {
            DB::table('consultation_referrals')->insert([
                'consultation_id' => $consultation->id,
                'plan_id' => $planIds['referral'] ?? null,
                'destination_type' => $plan['referral']['destinationType'] ?? $plan['referral']['target'] ?? null,
                'specialty' => $plan['referral']['specialty'] ?? null,
                'reason' => $plan['referral']['reason'] ?? null,
                'urgency' => trim((string) ($plan['referral']['urgency'] ?? 'routine')) ?: 'routine',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (isset($plan['observation']) && is_array($plan['observation'])) {
            DB::table('consultation_observations')->insert([
                'consultation_id' => $consultation->id,
                'plan_id' => $planIds['observation'] ?? null,
                'monitoring_payload' => json_encode($plan['observation'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('consultation_clinical_summaries')) {
            $summary = is_array($plan['clinical_summary'] ?? null) ? $plan['clinical_summary'] : [];
            $notesChiefComplaint = $this->extractLineValue($consultation->consult_notes, 'Chief complaint:');
            $notesHistory = $this->extractLineValue($consultation->consult_notes, 'History:');
            $notesDiagnosis = $this->extractLineValue($consultation->consult_notes, 'Assessment/Diagnosis:');
            $notesRiskLevel = $this->extractLineValue($consultation->consult_notes, 'Risk level:');
            $notesDisposition = $this->extractLineValue($consultation->consult_notes, 'Disposition:');
            $notesEncounterDuration = $this->extractLineValue($consultation->consult_notes, 'Encounter duration:');
            if ($notesEncounterDuration !== null && str_contains($notesEncounterDuration, '(est.')) {
                $notesEncounterDuration = trim(explode('(est.', $notesEncounterDuration)[0]);
            }
            DB::table('consultation_clinical_summaries')->insert([
                'consultation_id' => $consultation->id,
                'chief_complaint' => $summary['chief_complaint'] ?? $plan['chiefComplaint'] ?? $notesChiefComplaint,
                'chief_complaint_symptoms' => json_encode($summary['chief_complaint_symptoms'] ?? $plan['chiefComplaintSymptoms'] ?? [], JSON_UNESCAPED_UNICODE),
                'chief_complaint_duration' => $summary['chief_complaint_duration'] ?? $plan['chiefComplaintDuration'] ?? null,
                'chief_complaint_severity' => $summary['chief_complaint_severity'] ?? $plan['chiefComplaintSeverity'] ?? null,
                'history' => $summary['history'] ?? $plan['history'] ?? $notesHistory,
                'diagnosis' => $summary['diagnosis'] ?? $plan['diagnosis'] ?? $notesDiagnosis ?? $consultation->diagnosis,
                'risk_level' => $summary['risk_level'] ?? $plan['riskLevel'] ?? $notesRiskLevel,
                'disposition' => $summary['disposition'] ?? $plan['disposition'] ?? $notesDisposition,
                'duration_bucket' => $summary['duration_bucket'] ?? $plan['durationBucket'] ?? $notesEncounterDuration,
                'treatment_note' => $plan['treatment_note'] ?? $plan['treatmentNote'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('consultation_follow_ups')) {
            $followUp = is_array($plan['follow_up'] ?? null) ? $plan['follow_up'] : [];
            $nextStep = $followUp['next_step'] ?? $plan['nextStep'] ?? null;
            $timing = $followUp['timing'] ?? $plan['followUpTiming'] ?? null;
            $note = $followUp['note'] ?? $plan['followUpNote'] ?? $notesFollowUp;

            if (!$nextStep && $notesFollowUp) {
                $nextStepMatch = [];
                if (preg_match('/next step:\s*([^.]+)/i', $notesFollowUp, $nextStepMatch) === 1) {
                    $nextStep = trim((string) ($nextStepMatch[1] ?? ''));
                }
            }
            if (!$timing && $notesFollowUp) {
                $timingMatch = [];
                if (preg_match('/follow-up:\s*([^.]+)/i', $notesFollowUp, $timingMatch) === 1) {
                    $timing = trim((string) ($timingMatch[1] ?? ''));
                }
            }
            if ($nextStep !== null || $timing !== null || $note !== null) {
                DB::table('consultation_follow_ups')->insert([
                    'consultation_id' => $consultation->id,
                    'next_step' => $nextStep,
                    'timing' => $timing,
                    'note' => $note,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('consultation_warning_signs')) {
            $warningSigns = $plan['follow_up']['warning_signs']
                ?? $plan['observation']['warningSigns']
                ?? $plan['warningSigns']
                ?? [];
            if ((!is_array($warningSigns) || empty($warningSigns)) && !empty($notesFollowUp)) {
                $warningMatch = [];
                if (preg_match('/return\/seek urgent care if:\s*(.+)$/i', $notesFollowUp, $warningMatch) === 1) {
                    $warningSigns = array_filter(array_map('trim', explode(',', (string) ($warningMatch[1] ?? ''))));
                }
            }
            if (is_array($warningSigns)) {
                foreach ($warningSigns as $warningSign) {
                    $label = trim((string) $warningSign);
                    if ($label === '') {
                        continue;
                    }
                    DB::table('consultation_warning_signs')->insert([
                        'consultation_id' => $consultation->id,
                        'label' => $label,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function extractLineValue(?string $notes, string $prefix): ?string
    {
        if (!$notes) {
            return null;
        }
        foreach (preg_split('/\r\n|\r|\n/', $notes) as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || stripos($trimmed, $prefix) !== 0) {
                continue;
            }
            $value = trim(substr($trimmed, strlen($prefix)));
            return $value !== '' ? $value : null;
        }
        return null;
    }
}
