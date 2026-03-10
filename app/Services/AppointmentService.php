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
        'requested' => 'Request submitted',
        'triage' => 'Awaiting triage',
        'insurance_pending' => 'Insurance review pending',
        'insurance_approved' => 'Insurance approved',
        'insurance_rejected' => 'Insurance rejected',
        'pending_payment' => 'Awaiting payment',
        'scheduled' => 'Scheduled',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ];
    private const TRANSITIONS = [
        'requested' => ['triage', 'insurance_pending', 'pending_payment', 'scheduled', 'cancelled'],
        'triage' => ['insurance_pending', 'pending_payment', 'scheduled', 'cancelled'],
        'insurance_pending' => ['insurance_approved', 'insurance_rejected', 'cancelled'],
        'insurance_approved' => ['pending_payment', 'scheduled', 'cancelled'],
        'insurance_rejected' => ['pending_payment', 'cancelled'],
        'pending_payment' => ['scheduled', 'cancelled'],
        'scheduled' => ['in_progress', 'completed', 'cancelled', 'no_show'],
        'in_progress' => ['completed', 'cancelled'],
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

        $data['status'] = $this->normalizeStatus($data['status']);
        if ($this->shouldAutoScheduleVirtualOnCreate($data)) {
            $data['status'] = 'scheduled';
            $data['status_reason'] = $data['status_reason'] ?? 'Auto-confirmed virtual visit';
        }
        $paymentId = $data['payment_id'] ?? null;
        $this->assertPaymentVerifiedForStatus($data['status'], $paymentId, $data['appointment_type'] ?? null);

        return DB::transaction(function () use ($data, $currentHospitalId, $paymentId) {
            $appointment = new Appointment();
            $adminApproved = $currentHospitalId ? $data['admin_approved'] : 0;
            $this->fillAppointment($appointment, $data, true, $adminApproved);
            $this->applyStatusMetadata($appointment, null, $data['status']);
            $this->applyWorkflowMetadata($appointment, null, $data['status'], $data);
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

        $fromStatus = $this->normalizeStatus((string) $appointment->status);
        $nextStatus = $this->normalizeStatus((string) $data['status']);
        $data['status'] = $nextStatus;
        $paymentId = $data['payment_id'] ?? $appointment->payment_id;
        $this->assertStatusTransitionAllowed($appointment, $fromStatus, $nextStatus, $paymentId, $access);

        return DB::transaction(function () use ($appointment, $data, $currentHospitalId, $fromStatus, $nextStatus, $paymentId) {
            $adminApproved = $currentHospitalId ? $data['admin_approved'] : $appointment->admin_approved;
            $this->fillAppointment($appointment, $data, false, $adminApproved);
            $this->applyStatusMetadata($appointment, $fromStatus, $nextStatus);
            $this->applyWorkflowMetadata($appointment, $fromStatus, $nextStatus, $data);
            $appointment->save();

            $this->handlePaymentSideEffects($paymentId, $fromStatus, $nextStatus);
            $this->recordStatusChange($appointment, $fromStatus, $nextStatus, $data['status_reason'] ?? null);

            return $appointment;
        });
    }

    /**
     * Auto-transition stale virtual scheduled appointments to no_show.
     * This keeps timeout state server-authoritative for all clients.
     */
    public function enforceTimeouts(iterable $appointments): void
    {
        foreach ($appointments as $appointment) {
            if ($appointment instanceof Appointment) {
                $this->enforceTimeoutFor($appointment);
            }
        }
    }

    /**
     * Auto-transition a single stale virtual scheduled appointment to no_show.
     */
    public function enforceTimeoutFor(Appointment $appointment): Appointment
    {
        $current = $this->normalizeStatus((string) $appointment->status);
        if ($current !== 'scheduled') {
            return $appointment;
        }

        if (!$this->isVirtualVisitType($appointment->appointment_type)) {
            return $appointment;
        }

        $appointmentTime = $this->parseDateTime($appointment->date_time);
        if (!$appointmentTime) {
            return $appointment;
        }

        $graceMinutes = (int) env('APPOINTMENT_VIRTUAL_NO_SHOW_GRACE_MINUTES', 20);
        $deadline = $appointmentTime->copy()->addMinutes(max($graceMinutes, 0));
        if (Carbon::now()->lessThanOrEqualTo($deadline)) {
            return $appointment;
        }

        DB::transaction(function () use ($appointment, $current) {
            $next = 'no_show';
            $this->applyStatusMetadata($appointment, $current, $next);
            $this->applyWorkflowMetadata($appointment, $current, $next, [
                'status_reason' => 'auto_virtual_session_timeout',
            ]);
            $appointment->status = $next;
            $appointment->save();
            $this->handlePaymentSideEffects($appointment->payment_id, $current, $next);

            $this->recordStatusChange(
                $appointment,
                $current,
                $next,
                'auto_virtual_session_timeout'
            );
        });

        return $appointment->refresh();
    }

    private function fillAppointment(Appointment $appointment, array $data, bool $isCreate, $adminApproved): void
    {
        $appointment->patient_id = $data['patient_id'];
        $appointment->carer_id = $data['carer_id'];
        $appointment->status = $data['status'];
        $appointment->address = $data['address'];
        $appointment->address_lat = $data['address_lat'] ?? null;
        $appointment->address_lon = $data['address_lon'] ?? null;
        $appointment->price = $data['price'];
        $appointment->payment_id = $data['payment_id'] ?? null;
        $appointment->consult_id = $data['consult_id'] ?? null;
        $appointment->consult_type = $data['consult_type'];
        $appointment->extra_notes = $data['extra_notes'];
        $appointment->consent_accepted = (bool) ($data['consent_accepted'] ?? false);
        $appointment->attachments_json = $data['attachments_json'] ?? $appointment->attachments_json;
        $appointment->appointment_type = $data['appointment_type'];
        $appointment->channel = $data['channel'] ?? null;
        $appointment->date_time = $data['date_time'];
        $appointment->owned_by_role = $data['owned_by_role'] ?? $appointment->owned_by_role;
        $appointment->owned_by_id = $data['owned_by_id'] ?? $appointment->owned_by_id;
        $appointment->next_action_at = $data['next_action_at'] ?? $appointment->next_action_at;
        if ($isCreate) {
            $appointment->admin_approved = $adminApproved;
        } else {
            if (array_key_exists('ward_id', $data)) {
                $appointment->ward_id = $data['ward_id'];
            }
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

        $allowed = self::TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($toStatus, $allowed, true)) {
            abort(422, "Invalid appointment status transition: {$fromStatus} -> {$toStatus}.");
        }

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed'], true)) {
            $this->assertFinancialClearanceForStatus(
                $fromStatus,
                $toStatus,
                $paymentId,
                $appointment->appointment_type
            );
        }

        if ($toStatus === 'cancelled') {
            $this->assertCanCancel($appointment, $fromStatus, $access);
        }

        if ($toStatus === 'no_show') {
            $this->assertCanMarkNoShow($appointment, $fromStatus, $access);
        }
    }

    private function assertPaymentVerifiedForStatus(string $status, ?int $paymentId, ?string $appointmentType = null): void
    {
        if (!in_array($status, ['scheduled', 'in_progress', 'completed'], true)) {
            return;
        }

        if ($this->isVirtualVisitType($appointmentType)) {
            // Virtual visits are auto-confirmed in this workflow.
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

    private function assertFinancialClearanceForStatus(
        string $fromStatus,
        string $toStatus,
        ?int $paymentId,
        ?string $appointmentType = null
    ): void
    {
        if (!in_array($toStatus, ['scheduled', 'in_progress', 'completed'], true)) {
            return;
        }

        if ($fromStatus === 'insurance_approved') {
            return;
        }

        $this->assertPaymentVerifiedForStatus($toStatus, $paymentId, $appointmentType);
    }

    private function assertCanCancel(Appointment $appointment, string $fromStatus, AccessService $access): void
    {
        if (!in_array($fromStatus, ['pending_payment', 'scheduled', 'in_progress'], true)) {
            abort(422, 'Only pending, scheduled, or in-progress appointments can be cancelled.');
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

        if (!in_array($toStatus, ['cancelled', 'no_show'], true) || !$paymentId) {
            return;
        }

        $payment = Payment::find($paymentId);
        if ($payment && $payment->status === 'paid') {
            $payment->status = 'refund_pending';
            $payment->status_reason = $toStatus === 'no_show'
                ? 'appointment_no_show_auto_refund_review'
                : 'appointment_cancelled_refund_review';
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

    private function normalizeStatus(string $status): string
    {
        $normalized = trim(strtolower($status));
        return match ($normalized) {
            'pending', 'payment_pending' => 'pending_payment',
            default => $normalized,
        };
    }

    private function applyWorkflowMetadata(Appointment $appointment, ?string $fromStatus, string $toStatus, array $data): void
    {
        if (array_key_exists('owned_by_role', $data)) {
            $appointment->owned_by_role = $data['owned_by_role'] ?: null;
        }
        if (array_key_exists('owned_by_id', $data)) {
            $appointment->owned_by_id = $data['owned_by_id'] ? (int) $data['owned_by_id'] : null;
        }
        if (array_key_exists('next_action_at', $data)) {
            $appointment->next_action_at = $data['next_action_at'] ? Carbon::parse((string) $data['next_action_at']) : null;
        }

        if ($fromStatus === $toStatus) {
            return;
        }

        // Default queue ownership by workflow stage if caller did not explicitly set owner.
        if (!$appointment->owned_by_role) {
            if (in_array($toStatus, ['requested', 'triage', 'insurance_pending', 'insurance_approved', 'insurance_rejected', 'pending_payment'], true)) {
                $hospitalId = optional($appointment->carer)->hospital_id;
                if ($hospitalId) {
                    $appointment->owned_by_role = 'hospital';
                    $appointment->owned_by_id = (int) $hospitalId;
                } else {
                    $appointment->owned_by_role = 'ops';
                    $appointment->owned_by_id = null;
                }
            } elseif (in_array($toStatus, ['scheduled', 'in_progress'], true)) {
                $appointment->owned_by_role = 'carer';
                $appointment->owned_by_id = (int) $appointment->carer_id;
            } else {
                $appointment->owned_by_role = null;
                $appointment->owned_by_id = null;
            }
        }

        if (!$appointment->next_action_at) {
            $appointment->next_action_at = match ($toStatus) {
                'requested' => Carbon::now()->addMinutes(30),
                'triage' => Carbon::now()->addMinutes(20),
                'insurance_pending' => Carbon::now()->addHours(4),
                'insurance_approved', 'insurance_rejected' => Carbon::now()->addHours(2),
                'pending_payment' => Carbon::now()->addHours(6),
                default => null,
            };
        }
    }

    private function isVirtualVisitType(?string $appointmentType): bool
    {
        $type = strtolower(trim((string) $appointmentType));
        return str_contains($type, 'virtual');
    }

    private function shouldAutoScheduleVirtualOnCreate(array $data): bool
    {
        if (!$this->isVirtualVisitType($data['appointment_type'] ?? null)) {
            return false;
        }

        $status = $this->normalizeStatus((string) ($data['status'] ?? 'pending'));
        return in_array($status, ['requested', 'triage', 'pending_payment'], true);
    }
}
