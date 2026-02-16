<?php

namespace App\Services;

use App\Models\Carer;
use App\Models\Payment;
use App\Models\Teletest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeletestService
{
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
        $paymentId = $data['payment_id'] ?? null;
        $this->assertPaymentState($data['status'], $paymentId);

        return DB::transaction(function () use ($data, $currentHospitalId, $paymentId) {
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
        $fromStatus = $teletest->status;
        $nextStatus = $data['status'];
        $paymentId = $data['payment_id'] ?? $teletest->payment_id;
        $this->assertStatusTransitionAllowed($teletest, $fromStatus, $nextStatus, $paymentId, $access);
        $this->assertPaymentState($data['status'], $paymentId);

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
        $teletest->admin_approved = $adminApproved;
        $teletest->date_time = $data['date_time'];
    }

    private function applyStatusMetadata(Teletest $teletest, ?string $fromStatus, string $toStatus): void
    {
        $teletest->status_description = self::STATUS_DESCRIPTIONS[$toStatus] ?? $toStatus;

        if ($fromStatus === $toStatus) {
            return;
        }

        $now = Carbon::now();

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed'], true) && !$teletest->scheduled_at) {
            $teletest->scheduled_at = $now;
        }

        if (in_array($toStatus, ['in_progress', 'completed'], true) && !$teletest->started_at) {
            $teletest->started_at = $now;
        }

        if ($toStatus === 'completed' && !$teletest->completed_at) {
            $teletest->completed_at = $now;
        }

        if ($toStatus === 'cancelled' && !$teletest->cancelled_at) {
            $teletest->cancelled_at = $now;
        }

        if ($toStatus === 'no_show' && !$teletest->no_show_at) {
            $teletest->no_show_at = $now;
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
        Teletest $teletest,
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
            $this->assertCanCancel($teletest, $fromStatus, $access);
        }

        if ($toStatus === 'no_show') {
            $this->assertCanMarkNoShow($teletest, $fromStatus, $access);
        }
    }

    private function assertCanCancel(Teletest $teletest, string $fromStatus, AccessService $access): void
    {
        if (!in_array($fromStatus, ['pending_payment', 'scheduled'], true)) {
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
            if ($fromStatus === 'scheduled' && $testTime && $now->greaterThanOrEqualTo($testTime)) {
                abort(422, 'Cannot cancel once the teletest start time has passed.');
            }
            return;
        }

        abort(403, 'Forbidden');
    }

    private function assertCanMarkNoShow(Teletest $teletest, string $fromStatus, AccessService $access): void
    {
        if ($fromStatus !== 'scheduled') {
            abort(422, 'Only scheduled teletests can be marked as no-show.');
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

        if ($toStatus !== 'cancelled' || !$paymentId) {
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
}
