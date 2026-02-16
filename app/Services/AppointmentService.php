<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
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

    public function create(array $data, AccessService $access): Appointment
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        $currentCarerId = $access->currentCarerId();
        if ($currentCarerId && (int) $data['carer_id'] !== (int) $currentCarerId) {
            abort(403, 'Forbidden');
        }

        $currentHospitalId = $access->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            if (!$carerIds->contains($data['carer_id'])) {
                abort(403, 'Forbidden');
            }
        }

        $paymentId = $data['payment_id'] ?? null;
        $this->assertPaymentVerifiedForStatus($data['status'], $paymentId);

        return DB::transaction(function () use ($data, $currentHospitalId, $paymentId) {
            $appointment = new Appointment();
            $adminApproved = $currentHospitalId ? $data['admin_approved'] : 0;
            $this->fillAppointment($appointment, $data, true, $adminApproved);
            $this->applyStatusMetadata($appointment, null, $data['status']);
            $appointment->save();

            $this->recordStatusChange($appointment, null, $data['status'], $data['status_reason'] ?? null);

            return $appointment;
        });
    }

    public function update(Appointment $appointment, array $data, AccessService $access): Appointment
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $appointment->patient_id !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        $currentCarerId = $access->currentCarerId();
        if ($currentCarerId && (int) $appointment->carer_id !== (int) $currentCarerId) {
            abort(403, 'Forbidden');
        }

        $currentHospitalId = $access->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            if (!$carerIds->contains($appointment->carer_id)) {
                abort(403, 'Forbidden');
            }
        }

        if ((int) $appointment->patient_id !== (int) $data['patient_id']) {
            abort(403, 'Forbidden');
        }

        if ((int) $appointment->carer_id !== (int) $data['carer_id']) {
            abort(403, 'Forbidden');
        }

        $fromStatus = $appointment->status;
        $nextStatus = $data['status'];
        $paymentId = $data['payment_id'] ?? $appointment->payment_id;
        $this->assertStatusTransitionAllowed($appointment, $fromStatus, $nextStatus, $paymentId, $access);

        return DB::transaction(function () use ($appointment, $data, $currentHospitalId, $fromStatus, $nextStatus, $paymentId) {
            $adminApproved = $currentHospitalId ? $data['admin_approved'] : $appointment->admin_approved;
            $this->fillAppointment($appointment, $data, false, $adminApproved);
            $this->applyStatusMetadata($appointment, $fromStatus, $nextStatus);
            $appointment->save();

            $this->handlePaymentSideEffects($paymentId, $fromStatus, $nextStatus);
            $this->recordStatusChange($appointment, $fromStatus, $nextStatus, $data['status_reason'] ?? null);

            return $appointment;
        });
    }

    private function fillAppointment(Appointment $appointment, array $data, bool $isCreate, $adminApproved): void
    {
        $appointment->patient_id = $data['patient_id'];
        $appointment->carer_id = $data['carer_id'];
        $appointment->status = $data['status'];
        $appointment->address = $data['address'];
        $appointment->price = $data['price'];
        $appointment->payment_id = $data['payment_id'] ?? null;
        $appointment->consult_id = $data['consult_id'] ?? null;
        $appointment->consult_type = $data['consult_type'];
        $appointment->extra_notes = $data['extra_notes'];
        $appointment->appointment_type = $data['appointment_type'];
        $appointment->channel = $data['channel'] ?? null;
        $appointment->date_time = $data['date_time'];
        if ($isCreate) {
            $appointment->admin_approved = $adminApproved;
        } else {
            $appointment->ward_id = $data['ward_id'];
            $appointment->admin_approved = $adminApproved;
        }
    }

    private function applyStatusMetadata(Appointment $appointment, ?string $fromStatus, string $toStatus): void
    {
        $appointment->status_description = self::STATUS_DESCRIPTIONS[$toStatus] ?? $toStatus;

        if ($fromStatus === $toStatus) {
            return;
        }

        $now = Carbon::now();

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed'], true) && !$appointment->scheduled_at) {
            $appointment->scheduled_at = $now;
        }

        if (in_array($toStatus, ['in_progress', 'completed'], true) && !$appointment->started_at) {
            $appointment->started_at = $now;
        }

        if ($toStatus === 'completed' && !$appointment->completed_at) {
            $appointment->completed_at = $now;
        }

        if ($toStatus === 'cancelled' && !$appointment->cancelled_at) {
            $appointment->cancelled_at = $now;
        }

        if ($toStatus === 'no_show' && !$appointment->no_show_at) {
            $appointment->no_show_at = $now;
        }
    }

    private function assertStatusTransitionAllowed(
        Appointment $appointment,
        string $fromStatus,
        string $toStatus,
        ?int $paymentId,
        AccessService $access
    ): void {
        if ($fromStatus === $toStatus) {
            return;
        }

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed'], true)) {
            $this->assertPaymentVerifiedForStatus($toStatus, $paymentId);
        }

        if ($toStatus === 'cancelled') {
            $this->assertCanCancel($appointment, $fromStatus, $access);
        }

        if ($toStatus === 'no_show') {
            $this->assertCanMarkNoShow($appointment, $fromStatus, $access);
        }
    }

    private function assertPaymentVerifiedForStatus(string $status, ?int $paymentId): void
    {
        if (!in_array($status, ['scheduled', 'in_progress', 'completed'], true)) {
            return;
        }

        if (!$paymentId) {
            abort(422, 'payment_id is required before scheduling.');
        }

        $payment = Payment::find($paymentId);
        if (!$payment || $payment->status !== 'paid') {
            abort(422, 'Payment must be verified before scheduling.');
        }
    }

    private function assertCanCancel(Appointment $appointment, string $fromStatus, AccessService $access): void
    {
        if (!in_array($fromStatus, ['pending_payment', 'scheduled'], true)) {
            abort(422, 'Only pending or scheduled appointments can be cancelled.');
        }

        $now = Carbon::now();
        $appointmentTime = $this->parseDateTime($appointment->date_time);
        $role = $this->resolveActorRole($access);

        if ($role === 'patient') {
            if ($fromStatus === 'scheduled' && $appointmentTime && $now->greaterThan($appointmentTime->copy()->subHours(6))) {
                abort(422, 'Patients can only cancel at least 6 hours before the appointment.');
            }
            return;
        }

        if (in_array($role, ['carer', 'hospital'], true)) {
            if ($fromStatus === 'scheduled' && $appointmentTime && $now->greaterThanOrEqualTo($appointmentTime)) {
                abort(422, 'Cannot cancel once the appointment start time has passed.');
            }
            return;
        }

        abort(403, 'Forbidden');
    }

    private function assertCanMarkNoShow(Appointment $appointment, string $fromStatus, AccessService $access): void
    {
        if ($fromStatus !== 'scheduled') {
            abort(422, 'Only scheduled appointments can be marked as no-show.');
        }

        $role = $this->resolveActorRole($access);
        if (!in_array($role, ['carer', 'hospital'], true)) {
            abort(403, 'Forbidden');
        }

        $appointmentTime = $this->parseDateTime($appointment->date_time);
        if ($appointmentTime && Carbon::now()->lessThan($appointmentTime->copy()->addHour())) {
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

    private function recordStatusChange(Appointment $appointment, ?string $fromStatus, string $toStatus, ?string $reason): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        $this->statusChanges->record('appointment', $appointment->id, $fromStatus, $toStatus, $reason);
        $this->notifications->notifyStatusChange(
            'appointment',
            $appointment->id,
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
