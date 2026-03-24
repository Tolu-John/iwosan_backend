<?php

namespace App\Services;

use App\Models\Carer;
use App\Models\Payment;
use App\Models\Teletest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeletestService
{
    private const LEGACY_STATUS_MAP = [
        'requested' => 'awaiting_hospital_approval',
        'pending' => 'awaiting_payment',
        'confirmed' => 'scheduled',
        'assigned' => 'awaiting_technician_approval',
        'pending_payment' => 'awaiting_payment',
        'result_uploaded' => 'result_ready',
        'validated' => 'result_delivered',
        'completed' => 'visit_completed',
        'cancelled' => 'cancelled_by_hospital',
        'no_show' => 'no_show_patient',
    ];

    private StatusChangeService $statusChanges;
    private NotificationService $notifications;

    public function __construct(StatusChangeService $statusChanges, NotificationService $notifications)
    {
        $this->statusChanges = $statusChanges;
        $this->notifications = $notifications;
    }

    public function create(array $data, AccessService $access): Teletest
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
        $status = $this->normalizeStatus((string) $data['status']);
        if ($status === null) {
            abort(422, 'Invalid teletest status.');
        }
        $data['status'] = $status;

        $paymentId = $data['payment_id'] ?? null;
        $this->assertPaymentStateForStatus($status, $paymentId);

        return DB::transaction(function () use ($data, $currentHospitalId) {
            $teletest = new Teletest();
            $adminApproved = $currentHospitalId ? $data['admin_approved'] : 0;
            $this->fillTeletest($teletest, $data, $adminApproved);
            $this->applyStatusMetadata($teletest, null, $data['status']);
            $teletest->save();

            $this->recordStatusChange($teletest, null, $data['status'], $data['status_reason'] ?? null);

            return $teletest;
        });
    }

    public function update(Teletest $teletest, array $data, AccessService $access): Teletest
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $teletest->patient_id !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        $currentCarerId = $access->currentCarerId();
        if ($currentCarerId && (int) $teletest->carer_id !== (int) $currentCarerId) {
            abort(403, 'Forbidden');
        }

        $currentHospitalId = $access->currentHospitalId();
        if ($currentHospitalId && (int) $teletest->hospital_id !== (int) $currentHospitalId) {
            abort(403, 'Forbidden');
        }

        if ((int) $teletest->patient_id !== (int) $data['patient_id']) {
            abort(403, 'Forbidden');
        }

        if ((int) $teletest->carer_id !== (int) $data['carer_id']) {
            abort(403, 'Forbidden');
        }

        if ((int) $teletest->hospital_id !== (int) $data['hospital_id']) {
            abort(403, 'Forbidden');
        }

        $this->assertCarerBelongsToHospital($data['carer_id'], $data['hospital_id']);
        $fromStatus = $this->normalizeStatus((string) $teletest->status) ?? (string) $teletest->status;
        $nextStatus = $this->normalizeStatus((string) $data['status']);
        if ($nextStatus === null) {
            abort(422, 'Invalid teletest status.');
        }
        $data['status'] = $nextStatus;

        $paymentId = $data['payment_id'] ?? $teletest->payment_id;
        $this->assertStatusTransitionAllowed($teletest, $fromStatus, $nextStatus, $paymentId, $access);
        $this->assertPaymentStateForStatus($nextStatus, $paymentId);

        return DB::transaction(function () use ($teletest, $data, $currentHospitalId, $fromStatus, $nextStatus, $paymentId) {
            $adminApproved = $currentHospitalId ? $data['admin_approved'] : $teletest->admin_approved;
            $this->fillTeletest($teletest, $data, $adminApproved);
            $this->applyStatusMetadata($teletest, $fromStatus, $nextStatus);
            $teletest->save();

            $this->handlePaymentSideEffects($paymentId, $fromStatus, $nextStatus);
            $this->recordStatusChange($teletest, $fromStatus, $nextStatus, $data['status_reason'] ?? null);

            return $teletest;
        });
    }

    private function fillTeletest(Teletest $teletest, array $data, $adminApproved): void
    {
        $teletest->patient_id = $data['patient_id'];
        $teletest->carer_id = $data['carer_id'];
        $teletest->hospital_id = $data['hospital_id'];
        $teletest->payment_id = $data['payment_id'] ?? null;
        $teletest->review_id = $data['review_id'] ?? null;
        $teletest->address = $data['address'];
        $teletest->test_name = $data['test_name'];
        $teletest->status = $data['status'];
        if (array_key_exists('status_reason', $data)) {
            $teletest->status_reason = $data['status_reason'] ?: null;
        }
        if (array_key_exists('status_reason_note', $data)) {
            $teletest->status_reason_note = $data['status_reason_note'] ?: null;
        }
        $teletest->admin_approved = $adminApproved;
        $teletest->date_time = $data['date_time'];
    }

    private function applyStatusMetadata(Teletest $teletest, ?string $fromStatus, string $toStatus): void
    {
        $teletest->status_description = (string) (config("teletest_workflow.statuses.{$toStatus}.label") ?? $toStatus);

        if ($fromStatus === $toStatus) {
            return;
        }

        $now = Carbon::now();

        if (in_array($toStatus, ['scheduled', 'rescheduled_confirmed'], true) && !$teletest->scheduled_at) {
            $teletest->scheduled_at = $now;
        }

        if ($toStatus === 'en_route' && !$teletest->departed_at) {
            $teletest->departed_at = $now;
        }

        if ($toStatus === 'arrived' && !$teletest->arrived_at) {
            $teletest->arrived_at = $now;
        }

        if ($toStatus === 'in_progress' && !$teletest->started_at) {
            $teletest->started_at = $now;
        }

        if (in_array($toStatus, ['visit_completed', 'visit_closed'], true) && !$teletest->completed_at) {
            $teletest->completed_at = $now;
        }

        if (in_array($toStatus, ['cancelled_by_hospital', 'cancelled_by_technician'], true) && !$teletest->cancelled_at) {
            $teletest->cancelled_at = $now;
        }

        if (in_array($toStatus, ['no_show_patient', 'no_show_technician'], true) && !$teletest->no_show_at) {
            $teletest->no_show_at = $now;
        }

        if ($toStatus === 'technician_reassignment_pending') {
            $teletest->reassigned_at = $now;
            $teletest->reassigned_from = $teletest->reassigned_from ?: $teletest->carer_id;
        }
    }

    private function assertCarerBelongsToHospital(int $carerId, int $hospitalId): void
    {
        $carer = Carer::find($carerId);
        if (!$carer || (int) $carer->hospital_id !== (int) $hospitalId) {
            abort(422, 'Carer does not belong to hospital.');
        }
    }

    private function assertPaymentStateForStatus(string $status, $paymentId): void
    {
        if (!in_array($status, ['scheduled', 'rescheduled_confirmed'], true)) {
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
        Teletest $teletest,
        string $fromStatus,
        string $toStatus,
        ?int $paymentId,
        AccessService $access
    ): void {
        if ($fromStatus === $toStatus) {
            return;
        }

        $workflowTransitions = (array) config('teletest_workflow.allowed_transitions', []);
        if (array_key_exists($fromStatus, $workflowTransitions)) {
            $allowed = array_map('strval', (array) ($workflowTransitions[$fromStatus] ?? []));
            if (!in_array($toStatus, $allowed, true)) {
                abort(422, "Invalid teletest status transition: {$fromStatus} -> {$toStatus}.");
            }
        }

        if ($fromStatus === 'awaiting_payment' && $toStatus === 'scheduled') {
            $this->assertPaymentStateForStatus($toStatus, $paymentId);
        }

        if (in_array($toStatus, ['cancelled_by_hospital', 'cancelled_by_technician'], true)) {
            $this->assertCanCancel($teletest, $fromStatus, $access);
        }

        if ($toStatus === 'no_show_patient') {
            $this->assertCanMarkNoShow($teletest, $fromStatus, $access);
        }
    }

    private function assertCanCancel(Teletest $teletest, string $fromStatus, AccessService $access): void
    {
        if (!in_array($fromStatus, [
            'awaiting_hospital_approval',
            'hospital_changes_requested',
            'awaiting_technician_approval',
            'technician_reassignment_pending',
            'awaiting_payment',
            'scheduled',
            'reschedule_requested_by_patient',
            'reschedule_requested_by_hospital',
            'reschedule_requested_by_technician',
        ], true)) {
            abort(422, 'Only pending or scheduled teletests can be cancelled.');
        }

        $now = Carbon::now();
        $testTime = $this->parseDateTime($teletest->date_time);
        $role = $this->resolveActorRole($access);

        if ($role === 'patient') {
            if ($fromStatus === 'scheduled' && $testTime && $now->greaterThan($testTime->copy()->subHours(6))) {
                abort(422, 'Patients can only cancel at least 6 hours before the teletest.');
            }
            return;
        }

        if (in_array($role, ['carer', 'hospital'], true)) {
            if (in_array($fromStatus, ['scheduled', 'reschedule_requested_by_patient', 'reschedule_requested_by_hospital', 'reschedule_requested_by_technician'], true)
                && $testTime
                && $now->greaterThanOrEqualTo($testTime)) {
                abort(422, 'Cannot cancel once the teletest start time has passed.');
            }
            return;
        }

        abort(403, 'Forbidden');
    }

    private function assertCanMarkNoShow(Teletest $teletest, string $fromStatus, AccessService $access): void
    {
        if (!in_array($fromStatus, ['arrived', 'scheduled'], true)) {
            abort(422, 'No-show can only be marked from arrived or scheduled state.');
        }

        $role = $this->resolveActorRole($access);
        if (!in_array($role, ['carer', 'hospital'], true)) {
            abort(403, 'Forbidden');
        }

        $testTime = $this->parseDateTime($teletest->date_time);
        if ($testTime && Carbon::now()->lessThan($testTime->copy()->addHour())) {
            abort(422, 'No-show can only be marked at least 1 hour after the scheduled time.');
        }
    }

    private function handlePaymentSideEffects(?int $paymentId, string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        if (!in_array($toStatus, ['cancelled_by_hospital', 'cancelled_by_technician'], true) || !$paymentId) {
            return;
        }

        $payment = Payment::find($paymentId);
        if ($payment && $payment->status === 'paid') {
            $payment->status = 'refund_pending';
            $payment->save();
        }
    }

    private function recordStatusChange(Teletest $teletest, ?string $fromStatus, string $toStatus, ?string $reason): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        $this->statusChanges->record('teletest', $teletest->id, $fromStatus, $toStatus, $reason);
        $this->notifications->notifyStatusChange(
            'teletest',
            $teletest->id,
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

    private function normalizeStatus(string $status): ?string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return null;
        }

        $known = (array) config('teletest_workflow.statuses', []);
        if (array_key_exists($status, $known)) {
            return $status;
        }

        $aliases = (array) config('teletest_workflow.status_aliases', []);
        if (array_key_exists($status, $aliases)) {
            return (string) $aliases[$status];
        }

        return self::LEGACY_STATUS_MAP[$status] ?? null;
    }
}
