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
        'pending_payment' => 'Awaiting payment',
        'scheduled' => 'Scheduled',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
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

        if ($currentCarerId) {
            abort(403, 'Forbidden');
        }

        if (!$currentPatientId && !$currentHospitalId) {
            abort(403, 'Forbidden');
        }

        if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
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
        $consultation->payment_id = $data['payment_id'];
        $consultation->treatment_type = $data['treatment_type'];
        $consultation->diagnosis = $data['diagnosis'];
        $consultation->consult_notes = $data['consult_notes'];
        $consultation->date_time = $data['date_time'];

        if (!$isCreate) {
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

            $h = HConsultation::firstOrNew(['consultation_id' => $consultation->id]);
            $h->consultation_id = $consultation->id;
            $h->address = $data['hConsultation']['address'];
            if (array_key_exists('ward_id', $data['hConsultation'])) {
                $h->ward_id = $data['hConsultation']['ward_id'];
            }
            if (array_key_exists('admitted', $data['hConsultation'])) {
                $h->admitted = $data['hConsultation']['admitted'];
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
        if (!in_array($fromStatus, ['pending_payment', 'scheduled'], true)) {
            abort(422, 'Only pending or scheduled consultations can be cancelled.');
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
}
