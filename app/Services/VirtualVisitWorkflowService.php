<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VirtualVisitWorkflowService
{
    public function __construct(
        private readonly StatusChangeService $statusChanges,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function allowedActions(Appointment $appointment, AccessService $access): array
    {
        if (!$this->isVirtualVisitType($appointment->appointment_type)) {
            return [];
        }

        $status = $this->normalizeStatus((string) $appointment->status);
        $role = $this->resolveRole($access);
        if ($status === null || $role === null) {
            return [];
        }

        $actions = (array) config("virtual_visit_workflow.allowed_actions_by_role.{$status}.{$role}", []);
        return array_values(array_map('strval', $actions));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function runAction(Appointment $appointment, string $actionKey, array $payload, AccessService $access): Appointment
    {
        if (!$this->isVirtualVisitType($appointment->appointment_type)) {
            abort(422, 'Action endpoint is only enabled for virtual visit appointments.');
        }

        $fromStatus = $this->normalizeStatus((string) $appointment->status);
        if ($fromStatus === null) {
            abort(422, 'Current appointment status is not recognized by virtual workflow contract.');
        }

        $role = $this->resolveRole($access);
        if ($role === null) {
            abort(403, 'Forbidden');
        }

        $actionKey = strtolower(trim($actionKey));
        $allowed = $this->allowedActions($appointment, $access);
        if (!in_array($actionKey, $allowed, true)) {
            abort(403, "Action '{$actionKey}' is not allowed for role '{$role}' in status '{$fromStatus}'.");
        }
        $this->assertActionPayload($actionKey, $payload);

        $toStatus = $this->resolveTargetStatus($appointment, $fromStatus, $role, $actionKey, $payload);
        $allowSameStatusSideEffects = $toStatus === $fromStatus
            && $this->isSameStatusSideEffectAction($actionKey);

        if ($toStatus === null) {
            return $appointment;
        }
        if ($toStatus === $fromStatus && !$allowSameStatusSideEffects) {
            return $appointment;
        }

        if ($toStatus !== $fromStatus) {
            $allowedTransitions = (array) config("virtual_visit_workflow.allowed_transitions.{$fromStatus}", []);
            if (!in_array($toStatus, $allowedTransitions, true)) {
                abort(422, "Illegal transition '{$fromStatus}' -> '{$toStatus}'.");
            }
        }

        $this->assertGuardRules($appointment, $fromStatus, $toStatus, $payload);

        DB::transaction(function () use ($appointment, $fromStatus, $toStatus, $actionKey, $role, $access, $payload): void {
            $this->applyTransition($appointment, $toStatus, $payload);
            $appointment->save();

            $this->recordVirtualStatusHistory($appointment, $fromStatus, $toStatus, $actionKey, $role, $access, $payload);
            $this->recordStatusHistory($appointment, $fromStatus, $toStatus, $actionKey, $role, $access, $payload);
            $this->recordStatusChange($appointment, $fromStatus, $toStatus, $payload);
            $this->recordSessionEvent($appointment, $toStatus, $payload);
            $this->upsertConsentIfNeeded($appointment, $toStatus, $access, $payload);
            $this->upsertDisputeIfNeeded($appointment, $toStatus, $role, $access, $payload);
            $this->upsertEscalationIfNeeded($appointment, $toStatus, $role, $access, $payload);
        });

        return $appointment->refresh();
    }

    private function isSameStatusSideEffectAction(string $actionKey): bool
    {
        return in_array($actionKey, ['reassign'], true);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function transitionSystem(
        Appointment $appointment,
        string $toStatus,
        string $reasonCode,
        ?string $reasonNote = null,
        array $metadata = []
    ): Appointment {
        if (!$this->isVirtualVisitType($appointment->appointment_type)) {
            abort(422, 'System virtual workflow transition is only valid for virtual visit appointments.');
        }

        $fromStatus = $this->normalizeStatus((string) $appointment->status);
        $toStatus = $this->normalizeStatus($toStatus);

        if ($fromStatus === null || $toStatus === null) {
            abort(422, 'Unknown virtual workflow status for system transition.');
        }
        if ($fromStatus === $toStatus) {
            return $appointment;
        }

        $allowedTransitions = (array) config("virtual_visit_workflow.allowed_transitions.{$fromStatus}", []);
        if (!in_array($toStatus, $allowedTransitions, true)) {
            abort(422, "Illegal transition '{$fromStatus}' -> '{$toStatus}'.");
        }

        $payload = [
            'status_reason_code' => $reasonCode,
            'status_reason_note' => $reasonNote,
            'status_reason' => $reasonNote,
            'metadata' => $metadata,
        ];

        $this->assertGuardRules($appointment, $fromStatus, $toStatus, $payload);

        DB::transaction(function () use ($appointment, $fromStatus, $toStatus, $payload): void {
            $this->applyTransition($appointment, $toStatus, $payload);
            $appointment->save();

            $this->recordVirtualStatusHistory(
                $appointment,
                $fromStatus,
                $toStatus,
                'system_transition',
                'system',
                null,
                $payload
            );
            $this->recordStatusHistory(
                $appointment,
                $fromStatus,
                $toStatus,
                'system_transition',
                'system',
                null,
                $payload
            );
            $this->recordStatusChange($appointment, $fromStatus, $toStatus, $payload);
            $this->recordSessionEvent($appointment, $toStatus, $payload);
            $this->upsertDisputeIfNeeded($appointment, $toStatus, 'system', null, $payload);
            $this->upsertEscalationIfNeeded($appointment, $toStatus, 'system', null, $payload);
        });

        return $appointment->refresh();
    }

    private function isVirtualVisitType(?string $appointmentType): bool
    {
        $type = strtolower(trim((string) $appointmentType));
        return str_contains($type, 'virtual');
    }

    private function normalizeStatus(string $status): ?string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return null;
        }

        $statuses = (array) config('virtual_visit_workflow.statuses', []);
        if (array_key_exists($status, $statuses)) {
            return $status;
        }

        $aliases = (array) config('virtual_visit_workflow.status_aliases', []);
        if (array_key_exists($status, $aliases)) {
            return (string) $aliases[$status];
        }

        $legacyMap = [
            'triage' => 'pending_review',
            'insurance_pending' => 'pending_review',
            'insurance_approved' => 'awaiting_clinician_approval',
            'insurance_rejected' => 'pending_review',
            'cancelled' => 'cancelled_by_hospital',
            'no_show' => 'no_show_patient',
        ];

        return $legacyMap[$status] ?? null;
    }

    private function resolveRole(AccessService $access): ?string
    {
        if ($access->currentPatientId()) {
            return 'patient';
        }
        if ($access->currentCarerId()) {
            return 'clinician';
        }
        if ($access->currentHospitalId()) {
            return 'hospital';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveTargetStatus(Appointment $appointment, string $fromStatus, string $role, string $actionKey, array $payload): ?string
    {
        $explicitTarget = $payload['target_status'] ?? null;
        if (is_string($explicitTarget) && trim($explicitTarget) !== '') {
            $normalized = $this->normalizeStatus($explicitTarget);
            if ($normalized === null) {
                abort(422, "Unknown target_status '{$explicitTarget}'.");
            }

            return $normalized;
        }

        if ($actionKey === 'approve') {
            return match ($fromStatus) {
                'requested' => 'awaiting_clinician_approval',
                'pending_review' => 'awaiting_clinician_approval',
                'reschedule_requested' => 'rescheduled_confirmed',
                default => null,
            };
        }

        if ($actionKey === 'accept') {
            return match ($fromStatus) {
                'awaiting_clinician_approval' => $this->hasPaidBookingPayment($appointment, $payload)
                    ? 'scheduled'
                    : 'pending_payment',
                'reschedule_requested' => 'rescheduled_confirmed',
                default => null,
            };
        }

        if ($actionKey === 'decline') {
            return match ($fromStatus) {
                'awaiting_clinician_approval', 'reschedule_requested' => 'pending_review',
                default => null,
            };
        }

        if ($actionKey === 'submit_report' && $fromStatus === 'clinical_closeout_pending') {
            return 'review_pending';
        }

        if ($actionKey === 'review_carer' && $fromStatus === 'review_pending') {
            return 'record_released';
        }

        $rawTarget = (string) (config("virtual_visit_workflow.action_targets.{$actionKey}") ?? '');
        if ($rawTarget === '' || $rawTarget === '__stay') {
            return $fromStatus;
        }

        if ($rawTarget === '__dynamic_cancel_request') {
            return match ($role) {
                'patient' => 'cancelled_by_patient',
                'clinician' => 'cancelled_by_clinician',
                'hospital' => 'cancelled_by_hospital',
                default => $fromStatus,
            };
        }

        if ($rawTarget === '__dynamic_reschedule') {
            return 'reschedule_requested';
        }

        if ($rawTarget === '__dynamic_reassign') {
            return match ($fromStatus) {
                'awaiting_clinician_approval' => 'awaiting_clinician_approval',
                'cancelled_by_clinician', 'no_show_clinician' => 'refund_pending',
                default => $fromStatus,
            };
        }

        if ($rawTarget === '__dynamic_session_setup') {
            if ($fromStatus !== 'clinician_admitted_patient') {
                return $fromStatus;
            }

            return $this->isConsentAlreadyAccepted($appointment, $payload)
                ? 'consent_granted'
                : 'consent_pending';
        }

        return $this->normalizeStatus($rawTarget);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertActionPayload(string $actionKey, array $payload): void
    {
        $noteRequiredActions = [
            'request_changes',
            'reject',
            'decline',
            'submit_reason',
            'file_failure_report',
            'submit_failure_summary',
            'submit_response',
            'resolve',
            'terminate_case',
            'submit_final_note',
            'escalate',
        ];

        if (in_array($actionKey, $noteRequiredActions, true)) {
            $note = trim((string) ($payload['status_reason_note'] ?? ''));
            if ($note === '') {
                abort(422, "status_reason_note is required for {$actionKey}.");
            }
        }

        if ($actionKey === 'provide_consent') {
            $consentAccepted = (bool) ($payload['consent_accepted'] ?? false);
            if (!$consentAccepted) {
                abort(422, 'consent_accepted=true is required for provide_consent.');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertGuardRules(Appointment $appointment, string $fromStatus, string $toStatus, array $payload): void
    {
        if ($fromStatus === 'pending_payment' && $toStatus === 'scheduled') {
            $paymentId = $payload['payment_id'] ?? $appointment->payment_id;
            if (!$paymentId) {
                abort(422, 'A paid payment is required before scheduling.');
            }
            $payment = Payment::find((int) $paymentId);
            if (!$payment || $payment->status !== 'paid') {
                abort(422, 'Payment must be paid before scheduling.');
            }
        }

        if ($fromStatus === 'awaiting_clinician_approval' && $toStatus === 'scheduled') {
            $paymentId = $payload['payment_id'] ?? $appointment->payment_id;
            if (!$paymentId) {
                abort(422, 'A paid payment is required before scheduling.');
            }
            $payment = Payment::find((int) $paymentId);
            if (!$payment || $payment->status !== 'paid') {
                abort(422, 'Payment must be paid before scheduling.');
            }
        }

        if ($fromStatus === 'consent_pending' && $toStatus === 'consent_granted') {
            $consentAccepted = (bool) ($payload['consent_accepted'] ?? $appointment->consent_accepted);
            if (!$consentAccepted) {
                abort(422, 'consent_accepted=true is required before granting consent.');
            }
        }

        if ($fromStatus === 'clinical_closeout_pending' && $toStatus === 'completed') {
            $hasCloseoutSignal = !empty($payload['closeout_submitted']) || !empty($payload['status_reason_note']);
            if (!$hasCloseoutSignal) {
                abort(422, 'closeout_submitted=true or status_reason_note is required to mark completed.');
            }
        }

        if ($toStatus === 'session_live') {
            if (!$appointment->consent_granted_at && $fromStatus !== 'consent_granted') {
                abort(422, 'Consent must be granted before session can go live.');
            }
            if (!$appointment->session_ready_at && $fromStatus !== 'session_ready' && $fromStatus !== 'clinician_joined') {
                abort(422, 'Session readiness must be confirmed before session can go live.');
            }
        }

        if ($toStatus === 'refunded') {
            if (!$appointment->payment_id) {
                abort(422, 'Payment reference is required before refund can be finalized.');
            }
            $payment = Payment::find($appointment->payment_id);
            if (!$payment || !in_array((string) $payment->status, ['refund_pending', 'refunded'], true)) {
                abort(422, 'Payment must be in refund_pending before marking refunded.');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyTransition(Appointment $appointment, string $toStatus, array $payload): void
    {
        $now = Carbon::now();
        $appointment->status = $toStatus;
        $appointment->status_description = (string) (config("virtual_visit_workflow.statuses.{$toStatus}.label") ?? $toStatus);

        if (array_key_exists('payment_id', $payload) && $payload['payment_id']) {
            $appointment->payment_id = (int) $payload['payment_id'];
        }
        if (array_key_exists('carer_id', $payload) && $payload['carer_id']) {
            $appointment->carer_id = (int) $payload['carer_id'];
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
        if (array_key_exists('consent_version', $payload) && is_string($payload['consent_version'])) {
            $appointment->consent_version = trim($payload['consent_version']) ?: $appointment->consent_version;
        }

        if ($toStatus === 'scheduled' && !$appointment->scheduled_at) {
            $appointment->scheduled_at = $now;
        }
        if ($toStatus === 'waiting_room_open' && !$appointment->waiting_room_opened_at) {
            $appointment->waiting_room_opened_at = $now;
        }
        if ($toStatus === 'queued_for_clinician' && !$appointment->queued_at) {
            $appointment->queued_at = $now;
        }
        if ($toStatus === 'clinician_admitted_patient' && !$appointment->clinician_admitted_at) {
            $appointment->clinician_admitted_at = $now;
        }
        if ($toStatus === 'consent_pending' && !$appointment->consent_required_at) {
            $appointment->consent_required_at = $now;
        }
        if ($toStatus === 'consent_granted' && !$appointment->consent_granted_at) {
            $appointment->consent_granted_at = $now;
            $appointment->consent_accepted = true;
        }
        if ($toStatus === 'session_link_sent' && !$appointment->session_link_sent_at) {
            $appointment->session_link_sent_at = $now;
        }
        if ($toStatus === 'session_ready' && !$appointment->session_ready_at) {
            $appointment->session_ready_at = $now;
        }
        if ($toStatus === 'clinician_joined' && !$appointment->clinician_joined_at) {
            $appointment->clinician_joined_at = $now;
        }
        if ($toStatus === 'session_live' && !$appointment->session_live_at) {
            $appointment->session_live_at = $now;
        }
        if (in_array($toStatus, ['session_live', 'in_progress'], true) && !$appointment->started_at) {
            $appointment->started_at = $now;
        }
        if ($toStatus === 'session_interrupted') {
            $appointment->session_interrupted_at = $now;
        }
        if ($toStatus === 'session_failed') {
            $appointment->session_failed_at = $now;
        }
        if ($toStatus === 'session_ended' && !$appointment->session_ended_at) {
            $appointment->session_ended_at = $now;
        }
        if ($toStatus === 'clinical_closeout_pending' && !$appointment->closeout_started_at) {
            $appointment->closeout_started_at = $now;
        }
        if ($toStatus === 'completed') {
            if (!$appointment->closeout_submitted_at) {
                $appointment->closeout_submitted_at = $now;
            }
            if (!$appointment->completed_at) {
                $appointment->completed_at = $now;
            }
        }
        if ($toStatus === 'review_pending' && !$appointment->review_prompted_at) {
            $appointment->review_prompted_at = $now;
        }
        if ($toStatus === 'record_released' && !$appointment->record_released_at) {
            $appointment->record_released_at = $now;
        }
        if (in_array($toStatus, ['cancelled_by_patient', 'cancelled_by_clinician', 'cancelled_by_hospital'], true) && !$appointment->cancelled_at) {
            $appointment->cancelled_at = $now;
        }
        if (in_array($toStatus, ['no_show_patient', 'no_show_clinician'], true) && !$appointment->no_show_at) {
            $appointment->no_show_at = $now;
        }

        // Virtual queue ownership defaults.
        if (in_array($toStatus, [
            'requested',
            'pending_review',
            'awaiting_clinician_approval',
            'pending_payment',
            'payment_failed',
            'payment_expired',
            'reschedule_requested',
            'refund_pending',
            'refunded',
            'escalation_open',
            'escalation_in_progress',
            'escalation_resolved',
            'escalation_unresolved',
            'dispute_open',
            'dispute_resolved',
            'failed',
        ], true)) {
            $hospitalId = optional($appointment->carer)->hospital_id;
            $appointment->owned_by_role = $hospitalId ? 'hospital' : 'ops';
            $appointment->owned_by_id = $hospitalId ? (int) $hospitalId : null;
        } elseif (in_array($toStatus, [
            'scheduled',
            'waiting_room_open',
            'queued_for_clinician',
            'clinician_admitted_patient',
            'consent_pending',
            'consent_granted',
            'session_link_sent',
            'session_ready',
            'clinician_joined',
            'session_live',
            'in_progress',
            'session_interrupted',
            'session_failed',
            'session_ended',
            'clinical_closeout_pending',
        ], true)) {
            $appointment->owned_by_role = 'carer';
            $appointment->owned_by_id = (int) $appointment->carer_id;
        } else {
            $appointment->owned_by_role = null;
            $appointment->owned_by_id = null;
        }

        if (in_array($toStatus, ['refund_pending', 'cancelled_by_patient', 'cancelled_by_clinician', 'cancelled_by_hospital', 'no_show_clinician'], true)) {
            $this->markPaymentRefundPending($appointment);
        }
        if ($toStatus === 'refunded') {
            $this->markPaymentRefunded($appointment);
        }

        $this->syncNextActionAt($appointment, $toStatus, $now);
    }

    private function syncNextActionAt(Appointment $appointment, string $toStatus, Carbon $now): void
    {
        $minutesByStatus = [
            'pending_payment' => (int) (config('virtual_visit_workflow.sla_windows.pending_payment.payment_window_minutes') ?? 30),
            'queued_for_clinician' => (int) (config('virtual_visit_workflow.sla_windows.queued_for_clinician.queue_wait_minutes') ?? 10),
            'session_interrupted' => (int) (config('virtual_visit_workflow.sla_windows.session_interrupted.reconnect_minutes') ?? 2),
            'clinical_closeout_pending' => (int) (config('virtual_visit_workflow.sla_windows.clinical_closeout_pending.closeout_minutes') ?? 15),
            'review_pending' => (int) (config('virtual_visit_workflow.sla_windows.review_pending.first_reminder_minutes') ?? 60),
        ];

        if (array_key_exists($toStatus, $minutesByStatus)) {
            $minutes = max(1, (int) $minutesByStatus[$toStatus]);
            $appointment->next_action_at = $now->copy()->addMinutes($minutes);
            return;
        }

        $appointment->next_action_at = null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordVirtualStatusHistory(
        Appointment $appointment,
        string $fromStatus,
        string $toStatus,
        string $actionKey,
        string $role,
        ?AccessService $access,
        array $payload
    ): void {
        $actorId = $this->resolveActorId($role, $access);
        DB::table('virtual_visit_status_history')->insert([
            'appointment_id' => $appointment->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action_key' => $actionKey,
            'actor_role' => $role,
            'actor_id' => $actorId,
            'reason_code' => $appointment->status_reason_code,
            'reason_note' => $appointment->status_reason_note,
            'metadata_json' => $payload !== [] ? json_encode($payload) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordStatusHistory(
        Appointment $appointment,
        string $fromStatus,
        string $toStatus,
        string $actionKey,
        string $role,
        ?AccessService $access,
        array $payload
    ): void {
        $actorId = $this->resolveActorId($role, $access);
        DB::table('appointment_status_history')->insert([
            'appointment_id' => $appointment->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action_key' => $actionKey,
            'actor_role' => $role === 'clinician' ? 'carer' : $role,
            'actor_id' => $actorId,
            'reason_code' => $appointment->status_reason_code,
            'reason_note' => $appointment->status_reason_note,
            'metadata_json' => $payload !== [] ? json_encode($payload) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordStatusChange(Appointment $appointment, string $fromStatus, string $toStatus, array $payload): void
    {
        $reason = $appointment->status_reason_note ?: ($appointment->status_reason ?? null);
        $this->statusChanges->record('appointment', $appointment->id, $fromStatus, $toStatus, $reason);
        $this->notifications->notifyStatusChange(
            'appointment',
            $appointment->id,
            $fromStatus,
            $toStatus,
            ['reason' => $reason, 'payload' => $payload]
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordSessionEvent(Appointment $appointment, string $toStatus, array $payload): void
    {
        $eventMap = [
            'waiting_room_open' => 'waiting_room_opened',
            'queued_for_clinician' => 'patient_queued',
            'clinician_admitted_patient' => 'clinician_admitted_patient',
            'session_link_sent' => 'session_link_sent',
            'session_ready' => 'session_ready',
            'clinician_joined' => 'clinician_joined',
            'session_live' => 'session_live',
            'session_interrupted' => 'session_interrupted',
            'in_progress' => 'session_reconnected',
            'session_failed' => 'session_failed',
            'session_ended' => 'session_ended',
        ];

        if (!array_key_exists($toStatus, $eventMap)) {
            return;
        }

        DB::table('virtual_session_events')->insert([
            'appointment_id' => $appointment->id,
            'event_type' => $eventMap[$toStatus],
            'event_at' => Carbon::now(),
            'payload_json' => $payload !== [] ? json_encode($payload) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertConsentIfNeeded(Appointment $appointment, string $toStatus, AccessService $access, array $payload): void
    {
        if ($toStatus !== 'consent_granted') {
            return;
        }

        $patientUserId = (int) optional(optional($appointment->patient)->user)->id;
        $userId = $patientUserId > 0 ? $patientUserId : null;
        $version = trim((string) ($payload['consent_version'] ?? $appointment->consent_version ?? 'v1'));
        $textHash = trim((string) ($payload['consent_text_hash'] ?? ''));

        DB::table('virtual_visit_consents')->insert([
            'appointment_id' => $appointment->id,
            'consent_version' => $version !== '' ? $version : 'v1',
            'consent_text_hash' => $textHash !== '' ? $textHash : null,
            'granted_by_user_id' => $userId,
            'granted_at' => Carbon::now(),
            'channel' => 'in_app',
            'ip' => request()?->ip(),
            'device_fingerprint' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isConsentAlreadyAccepted(Appointment $appointment, array $payload): bool
    {
        if (array_key_exists('consent_accepted', $payload)) {
            return (bool) $payload['consent_accepted'];
        }

        return (bool) $appointment->consent_accepted;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertDisputeIfNeeded(
        Appointment $appointment,
        string $toStatus,
        string $role,
        ?AccessService $access,
        array $payload
    ): void {
        if (!in_array($toStatus, ['dispute_open', 'dispute_resolved'], true)) {
            return;
        }

        $now = Carbon::now();
        $existing = DB::table('virtual_visit_disputes')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('id')
            ->first();

        $actorId = $this->resolveActorId($role, $access);
        if (!$existing || $toStatus === 'dispute_open') {
            DB::table('virtual_visit_disputes')->insert([
                'appointment_id' => $appointment->id,
                'status' => $toStatus,
                'reason_code' => $payload['status_reason_code'] ?? null,
                'reason_note' => $payload['status_reason_note'] ?? null,
                'opened_by_role' => $role,
                'opened_by_id' => $actorId,
                'opened_at' => $now,
                'resolution_summary' => $toStatus === 'dispute_resolved' ? ($payload['status_reason_note'] ?? null) : null,
                'resolved_by_id' => $toStatus === 'dispute_resolved' ? $actorId : null,
                'resolved_at' => $toStatus === 'dispute_resolved' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('virtual_visit_disputes')
            ->where('id', $existing->id)
            ->update([
                'status' => $toStatus,
                'resolution_summary' => $payload['status_reason_note'] ?? $existing->resolution_summary,
                'resolved_by_id' => $toStatus === 'dispute_resolved' ? $actorId : $existing->resolved_by_id,
                'resolved_at' => $toStatus === 'dispute_resolved' ? $now : $existing->resolved_at,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertEscalationIfNeeded(
        Appointment $appointment,
        string $toStatus,
        string $role,
        ?AccessService $access,
        array $payload
    ): void {
        if (!in_array($toStatus, [
            'escalation_open',
            'escalation_in_progress',
            'escalation_resolved',
            'escalation_unresolved',
        ], true)) {
            return;
        }

        $now = Carbon::now();
        $actorId = $this->resolveActorId($role, $access);

        $existing = DB::table('escalations')
            ->where('appointment_id', $appointment->id)
            ->where('encounter_type', 'virtual_visit')
            ->orderByDesc('id')
            ->first();

        $severity = trim((string) ($payload['severity'] ?? ($existing->severity ?? 'medium')));
        $pathway = trim((string) ($payload['pathway'] ?? ($existing->pathway ?? 'virtual_visit')));
        $severity = $severity !== '' ? $severity : 'medium';
        $pathway = $pathway !== '' ? $pathway : 'virtual_visit';

        $escalationId = null;
        if (!$existing || $toStatus === 'escalation_open') {
            $escalationId = DB::table('escalations')->insertGetId([
                'appointment_id' => $appointment->id,
                'episode_id' => null,
                'severity' => $severity,
                'pathway' => $pathway,
                'status' => $toStatus,
                'opened_by_role' => $role,
                'opened_by_id' => $actorId,
                'opened_at' => $now,
                'resolved_at' => in_array($toStatus, ['escalation_resolved', 'escalation_unresolved'], true) ? $now : null,
                'encounter_type' => 'virtual_visit',
                'encounter_id' => $appointment->id,
                'teletest_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('escalations')
                ->where('id', $existing->id)
                ->update([
                    'status' => $toStatus,
                    'severity' => $severity,
                    'pathway' => $pathway,
                    'resolved_at' => in_array($toStatus, ['escalation_resolved', 'escalation_unresolved'], true)
                        ? $now
                        : null,
                    'updated_at' => $now,
                ]);
            $escalationId = (int) $existing->id;
        }

        if ($escalationId) {
            DB::table('escalation_events')->insert([
                'escalation_id' => $escalationId,
                'event_type' => $toStatus,
                'actor_role' => $role,
                'actor_id' => $actorId,
                'payload_json' => $payload !== [] ? json_encode($payload) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function markPaymentRefundPending(Appointment $appointment): void
    {
        if (!$appointment->payment_id) {
            return;
        }

        $payment = Payment::find($appointment->payment_id);
        if (!$payment || !in_array((string) $payment->status, ['paid', 'refund_pending'], true)) {
            return;
        }

        $payment->status = 'refund_pending';
        $payment->status_reason = 'virtual_visit_refund_review';
        $payment->save();
    }

    private function markPaymentRefunded(Appointment $appointment): void
    {
        if (!$appointment->payment_id) {
            return;
        }

        $payment = Payment::find($appointment->payment_id);
        if (!$payment || !in_array((string) $payment->status, ['paid', 'refund_pending', 'refunded'], true)) {
            return;
        }

        $payment->status = 'refunded';
        $payment->status_reason = 'virtual_visit_refund_settled';
        $payment->save();
    }

    private function resolveActorId(string $role, ?AccessService $access): ?int
    {
        if (!$access) {
            return null;
        }

        return match ($role) {
            'patient' => $access->currentPatientId(),
            'clinician' => $access->currentCarerId(),
            'hospital' => $access->currentHospitalId(),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasPaidBookingPayment(Appointment $appointment, array $payload): bool
    {
        $paymentId = $payload['payment_id'] ?? $appointment->payment_id;
        if (!$paymentId) {
            return false;
        }
        $payment = Payment::find((int) $paymentId);
        return $payment?->status === 'paid';
    }
}
