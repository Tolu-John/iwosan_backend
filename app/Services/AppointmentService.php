<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Payment;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    private const HOME_ACTION_STATUS_MAP = [
        'approve' => 'awaiting_clinician_approval',
        'reject' => 'admission_rejected',
        'request_changes' => '__dynamic_request_changes',
        'approve_assignment' => 'awaiting_payment',
        'reject_assignment' => 'admission_cancelled',
        'reassign' => '__stay',
        'pay_now' => 'scheduled',
        'prepare_for_visit' => '__stay',
        'view_eta' => '__stay',
        'get_support' => '__stay',
        'monitor_assist' => '__stay',
        'start_travel' => 'en_route',
        'mark_arrived' => 'arrived',
        'confirm_check_in' => 'in_progress',
        'start_visit' => 'in_progress',
        'complete_visit' => 'visit_completed',
        'submit_admission_plan' => 'home_admission_quote_pending_hospital',
        'approve_quote' => 'home_admitted_pending_payment',
        'request_quote_revision' => 'admission_revision_requested',
        'reject_admission' => 'admission_rejected',
        'pay_admission' => 'home_admitted_active',
        'open_escalation' => 'escalation_open',
        'escalate' => 'escalation_open',
        'resolve_escalation' => 'escalation_resolved',
        'transfer_escalation' => 'escalation_in_transfer',
        'mark_escalation_unresolved' => 'escalation_unresolved',
        'initiate_discharge' => 'discharge_initiated',
        'approve_discharge' => 'episode_completed',
        'cancel_admission' => 'admission_cancelled',
        'expire_admission' => 'admission_expired',
    ];

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

        $data['status'] = $this->normalizeStatus($data['status'], $data['appointment_type'] ?? null);
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

        $fromStatus = $this->normalizeStatus((string) $appointment->status, $appointment->appointment_type);
        $nextStatus = $this->normalizeStatus((string) $data['status'], $appointment->appointment_type);
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

    public function transitionByAction(
        Appointment $appointment,
        string $actionKey,
        array $payload,
        AccessService $access
    ): Appointment {
        if (!config('home_visit_workflow.enabled', true)) {
            abort(409, 'Home visit workflow v2 is disabled.');
        }

        $actionKey = strtolower(trim($actionKey));
        $fromStatus = $this->normalizeStatus((string) $appointment->status, $appointment->appointment_type);

        if (!$this->usesHomeWorkflow($appointment, $fromStatus)) {
            abort(422, 'Action endpoint is only enabled for home workflow appointments.');
        }

        $role = $this->resolveActorRole($access);
        if (!$role) {
            abort(403, 'Forbidden');
        }
        $actorId = match ($role) {
            'patient' => $access->currentPatientId(),
            'carer' => $access->currentCarerId(),
            'hospital' => $access->currentHospitalId(),
            default => null,
        };

        $roleKey = $role === 'carer' ? 'clinician' : $role;
        $allowedForRole = (array) config("home_visit_workflow.role_actions.{$fromStatus}.{$roleKey}", []);
        if (empty($allowedForRole) || !in_array($actionKey, $allowedForRole, true)) {
            abort(422, "Action {$actionKey} is not allowed for {$role} in status {$fromStatus}.");
        }

        $toStatus = $this->resolveActionTargetStatus($actionKey, $payload, $fromStatus);
        $this->assertStatusTransitionAllowed(
            $appointment,
            $fromStatus,
            $toStatus,
            $payload['payment_id'] ?? $appointment->payment_id,
            $access
        );

        return DB::transaction(function () use ($appointment, $fromStatus, $toStatus, $payload, $actionKey, $role, $actorId) {
            $appointment->status = $toStatus;
            if (array_key_exists('payment_id', $payload) && $payload['payment_id']) {
                $appointment->payment_id = (int) $payload['payment_id'];
            }
            if (array_key_exists('status_reason_code', $payload)) {
                $appointment->status_reason_code = $payload['status_reason_code'] ?: null;
            }
            if (array_key_exists('status_reason_note', $payload)) {
                $appointment->status_reason_note = $payload['status_reason_note'] ?: null;
            }
            if (array_key_exists('status_reason', $payload)) {
                $appointment->status_reason = $payload['status_reason'] ?: $appointment->status_reason;
            }
            if (array_key_exists('current_eta_minutes', $payload)) {
                $appointment->current_eta_minutes = $payload['current_eta_minutes'] !== null
                    ? (int) $payload['current_eta_minutes']
                    : null;
            }
            if (array_key_exists('assignment_source', $payload)) {
                $appointment->assignment_source = $payload['assignment_source'] ?: $appointment->assignment_source;
            }

            $this->applyStatusMetadata($appointment, $fromStatus, $toStatus);
            $this->applyWorkflowMetadata($appointment, $fromStatus, $toStatus, [
                'status_reason' => $payload['status_reason'] ?? null,
            ]);
            $appointment->save();

            $this->handlePaymentSideEffects($appointment->payment_id, $fromStatus, $toStatus);
            $this->recordStatusChange(
                $appointment,
                $fromStatus,
                $toStatus,
                $payload['status_reason'] ?? null,
                $actionKey,
                $role,
                $actorId,
                $payload
            );
            $this->applyHomeWorkflowSideEffects($appointment, $fromStatus, $toStatus, $payload, $role, $actorId);

            return $appointment;
        });
    }

    public function transitionSystem(
        Appointment $appointment,
        string $toStatus,
        string $reasonCode,
        ?string $reasonNote = null,
        array $metadata = []
    ): Appointment {
        if (!config('home_visit_workflow.enabled', true)) {
            abort(409, 'Home visit workflow v2 is disabled.');
        }

        $fromStatus = $this->normalizeStatus((string) $appointment->status, $appointment->appointment_type);
        $toStatus = $this->normalizeStatus($toStatus, $appointment->appointment_type);

        if (!$this->usesHomeWorkflow($appointment, $fromStatus)) {
            abort(422, 'System transition is only enabled for home workflow appointments.');
        }

        $allowed = $this->resolveAllowedTransitions($appointment, $fromStatus);
        if (!in_array($toStatus, $allowed, true)) {
            abort(422, "Invalid appointment status transition: {$fromStatus} -> {$toStatus}.");
        }

        return DB::transaction(function () use ($appointment, $fromStatus, $toStatus, $reasonCode, $reasonNote, $metadata) {
            $appointment->status = $toStatus;
            $appointment->status_reason_code = $reasonCode;
            $appointment->status_reason_note = $reasonNote;
            $appointment->status_reason = $reasonNote ?: $reasonCode;
            $this->applyStatusMetadata($appointment, $fromStatus, $toStatus);
            $this->applyWorkflowMetadata($appointment, $fromStatus, $toStatus, ['status_reason' => $appointment->status_reason]);
            $appointment->save();

            $this->recordStatusChange(
                $appointment,
                $fromStatus,
                $toStatus,
                $appointment->status_reason,
                'system_transition',
                'system',
                null,
                $metadata
            );
            $this->applyHomeWorkflowSideEffects($appointment, $fromStatus, $toStatus, $metadata, 'system', null);

            return $appointment;
        });
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
        $isHomeVisit = $this->isHomeVisitType($data['appointment_type'] ?? null);
        if ($isHomeVisit) {
            $homeVisitContact = $this->resolveHomeVisitContact($data, (int) $appointment->patient_id);
            $appointment->dispatch_model = $data['dispatch_model'] ?? $appointment->dispatch_model;
            $appointment->address_source = $data['address_source'] ?? $appointment->address_source;
            $appointment->contact_profile = $data['contact_profile'] ?? $appointment->contact_profile;
            $appointment->visit_reason = $data['visit_reason'] ?? $appointment->visit_reason;
            $appointment->preferred_window = $data['preferred_window'] ?? $appointment->preferred_window;
            $appointment->expected_duration = $data['expected_duration'] ?? $appointment->expected_duration;
            $appointment->red_flags_json = $data['red_flags_json'] ?? $appointment->red_flags_json;
            $appointment->preferred_hospital_id = $data['preferred_hospital_id'] ?? $appointment->preferred_hospital_id;
            $appointment->preferred_hospital_name = $data['preferred_hospital_name'] ?? $appointment->preferred_hospital_name;
            $appointment->preferred_clinician_id = $data['preferred_clinician_id'] ?? $appointment->preferred_clinician_id;
            $appointment->preferred_clinician_name = $data['preferred_clinician_name'] ?? $appointment->preferred_clinician_name;
            $appointment->preference_note = $data['preference_note'] ?? $appointment->preference_note;
            $appointment->additional_notes = $data['additional_notes'] ?? $appointment->additional_notes;
            $appointment->visit_contact_name = $homeVisitContact['name'] ?? $appointment->visit_contact_name;
            $appointment->visit_contact_phone = $homeVisitContact['phone'] ?? $appointment->visit_contact_phone;
        } elseif ($isCreate) {
            $appointment->dispatch_model = null;
            $appointment->address_source = null;
            $appointment->contact_profile = null;
            $appointment->visit_reason = null;
            $appointment->preferred_window = null;
            $appointment->expected_duration = null;
            $appointment->red_flags_json = null;
            $appointment->preferred_hospital_id = null;
            $appointment->preferred_hospital_name = null;
            $appointment->preferred_clinician_id = null;
            $appointment->preferred_clinician_name = null;
            $appointment->preference_note = null;
            $appointment->additional_notes = null;
            $appointment->visit_contact_name = null;
            $appointment->visit_contact_phone = null;
        }
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
        $homeStatusMeta = (array) config("home_visit_workflow.statuses.{$toStatus}", []);
        $appointment->status_description = $homeStatusMeta['label']
            ?? self::STATUS_DESCRIPTIONS[$toStatus]
            ?? $toStatus;

        if ($fromStatus === $toStatus) {
            return;
        }

        $now = Carbon::now();

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed', 'visit_completed', 'episode_completed'], true) && !$appointment->scheduled_at) {
            $appointment->scheduled_at = $now;
        }

        if (in_array($toStatus, ['in_progress', 'completed', 'visit_completed', 'episode_completed'], true) && !$appointment->started_at) {
            $appointment->started_at = $now;
        }

        if (in_array($toStatus, ['completed', 'visit_completed', 'episode_completed'], true) && !$appointment->completed_at) {
            $appointment->completed_at = $now;
        }

        if ($toStatus === 'awaiting_clinician_approval' && !$appointment->approved_at) {
            $appointment->approved_at = $now;
        }

        if ($toStatus === 'en_route' && !$appointment->departed_at) {
            $appointment->departed_at = $now;
        }

        if ($toStatus === 'arrived' && !$appointment->arrived_at) {
            $appointment->arrived_at = $now;
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

        $allowed = $this->resolveAllowedTransitions($appointment, $fromStatus);
        if (!in_array($toStatus, $allowed, true)) {
            abort(422, "Invalid appointment status transition: {$fromStatus} -> {$toStatus}.");
        }

        if (in_array($toStatus, ['scheduled', 'in_progress', 'completed', 'visit_completed', 'episode_completed', 'home_admitted_active'], true)) {
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

        if ($toStatus === 'home_admitted_pending_payment') {
            $this->assertAdmissionQuoteExists($appointment);
        }

        if (in_array($toStatus, ['escalation_in_transfer', 'escalation_resolved', 'escalation_unresolved'], true)) {
            $this->assertEscalationContextExists($appointment);
        }
    }

    private function assertPaymentVerifiedForStatus(string $status, ?int $paymentId, ?string $appointmentType = null): void
    {
        if (!in_array($status, ['scheduled', 'in_progress', 'completed', 'visit_completed', 'episode_completed', 'home_admitted_active'], true)) {
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
        if (!in_array($toStatus, ['scheduled', 'in_progress', 'completed', 'visit_completed', 'episode_completed', 'home_admitted_active'], true)) {
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

    private function assertAdmissionQuoteExists(Appointment $appointment): void
    {
        $quote = DB::table('home_admission_quotes')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('version')
            ->first();

        if (!$quote) {
            abort(422, 'Admission quote is required before awaiting payment.');
        }
    }

    private function assertEscalationContextExists(Appointment $appointment): void
    {
        $exists = DB::table('escalations')
            ->where('appointment_id', $appointment->id)
            ->exists();

        if (!$exists) {
            abort(422, 'Escalation context must exist for this transition.');
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

    private function recordStatusChange(
        Appointment $appointment,
        ?string $fromStatus,
        string $toStatus,
        ?string $reason,
        ?string $actionKey = null,
        ?string $actorRole = null,
        ?int $actorId = null,
        array $metadata = []
    ): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        $this->statusChanges->record('appointment', $appointment->id, $fromStatus, $toStatus, $reason);
        DB::table('appointment_status_history')->insert([
            'appointment_id' => $appointment->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action_key' => $actionKey,
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'reason_code' => $appointment->status_reason_code,
            'reason_note' => $reason,
            'metadata_json' => !empty($metadata) ? json_encode($metadata) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
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

    private function normalizeStatus(string $status, ?string $appointmentType = null): string
    {
        $normalized = trim(strtolower($status));
        $aliases = (array) config('home_visit_workflow.status_aliases', []);

        if (isset($aliases[$normalized])) {
            $normalized = (string) $aliases[$normalized];
        }

        if (in_array($normalized, ['pending', 'payment_pending'], true)) {
            $normalized = 'pending_payment';
        }

        if ($this->isHomeVisitType($appointmentType) && $normalized === 'requested') {
            return 'awaiting_hospital_approval';
        }

        if ($this->isHomeVisitType($appointmentType) && $normalized === 'pending_payment') {
            return 'awaiting_payment';
        }

        return $normalized;
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
            if (in_array($toStatus, ['requested', 'triage', 'insurance_pending', 'insurance_approved', 'insurance_rejected', 'pending_payment', 'awaiting_hospital_approval', 'awaiting_clinician_approval', 'awaiting_payment', 'home_admission_quote_pending_hospital', 'admission_revision_requested', 'home_admitted_pending_payment', 'payment_due', 'payment_overdue', 'care_paused_non_critical', 'discharge_initiated', 'escalation_open', 'escalation_in_progress', 'escalation_in_transfer', 'escalation_unresolved'], true)) {
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
                'requested', 'awaiting_hospital_approval' => Carbon::now()->addMinutes(30),
                'triage' => Carbon::now()->addMinutes(20),
                'insurance_pending' => Carbon::now()->addHours(4),
                'insurance_approved', 'insurance_rejected' => Carbon::now()->addHours(2),
                'pending_payment', 'awaiting_payment' => Carbon::now()->addHours(6),
                default => null,
            };
        }
    }

    private function isVirtualVisitType(?string $appointmentType): bool
    {
        $type = strtolower(trim((string) $appointmentType));
        return str_contains($type, 'virtual');
    }

    private function isHomeVisitType(?string $appointmentType): bool
    {
        $type = strtolower(trim((string) $appointmentType));
        return str_contains($type, 'home');
    }

    private function resolveActionTargetStatus(string $actionKey, array $payload, string $fromStatus): string
    {
        $map = $this->homeActionTargets();

        if ($actionKey === 'complete_visit') {
            $disposition = strtolower(trim((string) ($payload['disposition'] ?? '')));
            if ($disposition === 'admit_to_home_care') {
                return 'admission_recommended';
            }
        }

        if ($actionKey === 'open_escalation') {
            $pathway = strtolower(trim((string) ($payload['pathway'] ?? '')));
            if ($pathway === 'transfer') {
                return 'escalation_in_transfer';
            }
        }

        if (!array_key_exists($actionKey, $map)) {
            abort(422, "Unknown action: {$actionKey}.");
        }

        $target = $map[$actionKey];
        if ($target === '__stay') {
            return $fromStatus;
        }
        if ($target === '__dynamic_request_changes') {
            return match ($fromStatus) {
                'awaiting_hospital_approval' => 'awaiting_hospital_approval',
                'home_admission_quote_pending_hospital' => 'admission_revision_requested',
                default => 'admission_revision_requested',
            };
        }

        return $target;
    }

    private function homeActionTargets(): array
    {
        $configured = config('home_visit_workflow.action_targets');
        if (!is_array($configured) || empty($configured)) {
            return self::HOME_ACTION_STATUS_MAP;
        }

        return $configured;
    }

    private function applyHomeWorkflowSideEffects(
        Appointment $appointment,
        string $fromStatus,
        string $toStatus,
        array $payload,
        string $actorRole,
        ?int $actorId
    ): void {
        if (!$this->usesHomeWorkflow($appointment, $toStatus)) {
            return;
        }

        if (in_array($toStatus, ['home_admission_quote_pending_hospital', 'admission_revision_requested'], true)) {
            $this->upsertAdmissionQuote($appointment, $payload, $toStatus);
        }

        if (in_array($toStatus, ['home_admitted_pending_payment', 'admission_expired', 'admission_cancelled'], true)) {
            $this->markLatestQuoteState($appointment, $toStatus, $actorId);
        }

        if (in_array($toStatus, [
            'home_admitted_active',
            'payment_due',
            'payment_overdue',
            'care_paused_non_critical',
            'episode_closed_nonpayment',
            'discharge_initiated',
            'episode_completed',
        ], true)) {
            $this->upsertHomeEpisode($appointment, $toStatus);
        }

        if (str_starts_with($toStatus, 'escalation_')) {
            $this->upsertEscalation($appointment, $toStatus, $payload, $actorRole, $actorId);
        }
    }

    private function upsertAdmissionQuote(Appointment $appointment, array $payload, string $toStatus): void
    {
        $latest = DB::table('home_admission_quotes')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('version')
            ->first();

        $version = $latest ? ((int) $latest->version + 1) : 1;
        $now = Carbon::now();

        $enrollmentFeeMinor = array_key_exists('enrollment_fee_minor', $payload)
            ? (int) $payload['enrollment_fee_minor']
            : (int) ($latest->enrollment_fee_minor ?? 0);
        $recurringFeeMinor = array_key_exists('recurring_fee_minor', $payload)
            ? (int) $payload['recurring_fee_minor']
            : (int) ($latest->recurring_fee_minor ?? 0);
        $addonsTotalMinor = array_key_exists('addons_total_minor', $payload)
            ? (int) $payload['addons_total_minor']
            : (int) ($latest->addons_total_minor ?? 0);
        $discountTotalMinor = array_key_exists('discount_total_minor', $payload)
            ? (int) $payload['discount_total_minor']
            : (int) ($latest->discount_total_minor ?? 0);
        $taxTotalMinor = array_key_exists('tax_total_minor', $payload)
            ? (int) $payload['tax_total_minor']
            : (int) ($latest->tax_total_minor ?? 0);

        $computedGrandTotalMinor = max(0, $enrollmentFeeMinor + $recurringFeeMinor + $addonsTotalMinor + $taxTotalMinor - $discountTotalMinor);
        $grandTotalMinor = array_key_exists('grand_total_minor', $payload)
            ? (int) $payload['grand_total_minor']
            : (isset($latest->grand_total_minor) ? (int) $latest->grand_total_minor : $computedGrandTotalMinor);

        DB::table('home_admission_quotes')->insert([
            'appointment_id' => $appointment->id,
            'version' => $version,
            'currency' => $payload['currency'] ?? ($latest->currency ?? 'NGN'),
            'enrollment_fee_minor' => $enrollmentFeeMinor,
            'recurring_fee_minor' => $recurringFeeMinor,
            'billing_cycle' => $payload['billing_cycle'] ?? ($latest->billing_cycle ?? null),
            'addons_total_minor' => $addonsTotalMinor,
            'discount_total_minor' => $discountTotalMinor,
            'tax_total_minor' => $taxTotalMinor,
            'grand_total_minor' => $grandTotalMinor,
            'quote_status' => $toStatus === 'admission_revision_requested' ? 'submitted' : 'draft',
            'valid_until' => !empty($payload['quote_valid_until']) ? Carbon::parse((string) $payload['quote_valid_until']) : null,
            'approved_by' => null,
            'approved_at' => null,
            'metadata_json' => json_encode(['source' => 'workflow_action']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function markLatestQuoteState(Appointment $appointment, string $toStatus, ?int $actorId): void
    {
        $latest = DB::table('home_admission_quotes')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('version')
            ->first();

        if (!$latest) {
            return;
        }

        $quoteStatus = match ($toStatus) {
            'home_admitted_pending_payment' => 'approved',
            'admission_expired' => 'expired',
            'admission_cancelled' => 'cancelled',
            default => $latest->quote_status,
        };

        DB::table('home_admission_quotes')
            ->where('id', $latest->id)
            ->update([
                'quote_status' => $quoteStatus,
                'approved_by' => $quoteStatus === 'approved' ? $actorId : $latest->approved_by,
                'approved_at' => $quoteStatus === 'approved' ? Carbon::now() : $latest->approved_at,
                'updated_at' => Carbon::now(),
            ]);
    }

    private function upsertHomeEpisode(Appointment $appointment, string $toStatus): void
    {
        $latestQuote = DB::table('home_admission_quotes')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('version')
            ->first();

        $hospitalId = optional($appointment->carer)->hospital_id;
        if (!$hospitalId) {
            return;
        }

        $existing = DB::table('home_care_episodes')
            ->where('appointment_id', $appointment->id)
            ->first();

        $now = Carbon::now();
        if (!$existing) {
            $episodeId = DB::table('home_care_episodes')->insertGetId([
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'hospital_id' => $hospitalId,
                'episode_status' => $toStatus,
                'current_quote_id' => $latestQuote?->id,
                'care_plan_json' => null,
                'started_at' => $toStatus === 'home_admitted_active' ? $now : null,
                'paused_at' => $toStatus === 'care_paused_non_critical' ? $now : null,
                'closed_at' => in_array($toStatus, ['episode_closed_nonpayment', 'episode_completed'], true) ? $now : null,
                'discharged_at' => $toStatus === 'episode_completed' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->ensureInitialBillingCycle($episodeId, $toStatus);
            return;
        }

        DB::table('home_care_episodes')->where('id', $existing->id)->update([
            'episode_status' => $toStatus,
            'current_quote_id' => $latestQuote?->id ?? $existing->current_quote_id,
            'started_at' => $existing->started_at ?: ($toStatus === 'home_admitted_active' ? $now : null),
            'paused_at' => $toStatus === 'care_paused_non_critical' ? $now : $existing->paused_at,
            'closed_at' => in_array($toStatus, ['episode_closed_nonpayment', 'episode_completed'], true) ? $now : $existing->closed_at,
            'discharged_at' => $toStatus === 'episode_completed' ? $now : $existing->discharged_at,
            'updated_at' => $now,
        ]);
        $this->ensureInitialBillingCycle((int) $existing->id, $toStatus);
    }

    private function upsertEscalation(
        Appointment $appointment,
        string $toStatus,
        array $payload,
        string $actorRole,
        ?int $actorId
    ): void {
        $episode = DB::table('home_care_episodes')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('id')
            ->first();

        $existing = DB::table('escalations')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('id')
            ->first();

        $now = Carbon::now();
        if (!$existing || $toStatus === 'escalation_open') {
            $escalationId = DB::table('escalations')->insertGetId([
                'appointment_id' => $appointment->id,
                'episode_id' => $episode?->id,
                'severity' => $payload['severity'] ?? 'urgent',
                'pathway' => $payload['pathway'] ?? 'intervention',
                'status' => $toStatus,
                'opened_by_role' => $actorRole,
                'opened_by_id' => $actorId,
                'opened_at' => $now,
                'resolved_at' => in_array($toStatus, ['escalation_resolved', 'escalation_unresolved'], true) ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('escalations')->where('id', $existing->id)->update([
                'status' => $toStatus,
                'pathway' => $payload['pathway'] ?? $existing->pathway,
                'severity' => $payload['severity'] ?? $existing->severity,
                'resolved_at' => in_array($toStatus, ['escalation_resolved', 'escalation_unresolved'], true) ? $now : $existing->resolved_at,
                'updated_at' => $now,
            ]);
            $escalationId = (int) $existing->id;
        }

        DB::table('escalation_events')->insert([
            'escalation_id' => $escalationId,
            'event_type' => 'status_transition',
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'payload_json' => json_encode([
                'to_status' => $toStatus,
                'severity' => $payload['severity'] ?? null,
                'pathway' => $payload['pathway'] ?? null,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureInitialBillingCycle(int $episodeId, string $episodeStatus): void
    {
        if ($episodeStatus !== 'home_admitted_active') {
            return;
        }

        $hasCycle = DB::table('episode_billing_cycles')
            ->where('episode_id', $episodeId)
            ->exists();
        if ($hasCycle) {
            return;
        }

        $start = Carbon::today();
        DB::table('episode_billing_cycles')->insert([
            'episode_id' => $episodeId,
            'cycle_start' => $start->toDateString(),
            'cycle_end' => $start->copy()->addDays(29)->toDateString(),
            'due_at' => $start->copy()->addDays(30),
            'amount_due_minor' => 0,
            'amount_paid_minor' => 0,
            'billing_status' => 'due',
            'last_reminder_at' => null,
            'grace_until' => $start->copy()->addDays(37),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @return string[]
     */
    private function resolveAllowedTransitions(Appointment $appointment, string $fromStatus): array
    {
        if ($this->usesHomeWorkflow($appointment, $fromStatus)) {
            return (array) config("home_visit_workflow.transitions.{$fromStatus}", []);
        }

        return self::TRANSITIONS[$fromStatus] ?? [];
    }

    private function usesHomeWorkflow(Appointment $appointment, string $status): bool
    {
        if ($this->isVirtualVisitType($appointment->appointment_type)) {
            return false;
        }

        if ($this->isHomeVisitType($appointment->appointment_type)) {
            return true;
        }

        return $this->isHomeSpecificWorkflowStatus($status);
    }

    private function isHomeWorkflowStatus(string $status): bool
    {
        return array_key_exists($status, (array) config('home_visit_workflow.statuses', []));
    }

    private function isVirtualWorkflowStatus(string $status): bool
    {
        return array_key_exists($status, (array) config('virtual_visit_workflow.statuses', []));
    }

    private function isHomeSpecificWorkflowStatus(string $status): bool
    {
        return $this->isHomeWorkflowStatus($status) && !$this->isVirtualWorkflowStatus($status);
    }

    private function resolveHomeVisitContact(array $data, int $patientId): array
    {
        $name = trim((string) ($data['visit_contact_name'] ?? ''));
        $phone = trim((string) ($data['visit_contact_phone'] ?? ''));
        $profile = trim(strtolower((string) ($data['contact_profile'] ?? '')));

        if ($profile === 'custom' && ($name !== '' || $phone !== '')) {
            return ['name' => $name !== '' ? $name : null, 'phone' => $phone !== '' ? $phone : null];
        }

        if ($name !== '' || $phone !== '') {
            return ['name' => $name !== '' ? $name : null, 'phone' => $phone !== '' ? $phone : null];
        }

        $patient = Patient::with('user')->find($patientId);
        $first = trim((string) optional(optional($patient)->user)->firstname);
        $last = trim((string) optional(optional($patient)->user)->lastname);
        $fullName = trim($first.' '.$last);
        $fallbackPhone = trim((string) optional(optional($patient)->user)->phone);

        return [
            'name' => $fullName !== '' ? $fullName : null,
            'phone' => $fallbackPhone !== '' ? $fallbackPhone : null,
        ];
    }
}
