<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Teletest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeletestWorkflowService
{
    public function __construct(
        private readonly StatusChangeService $statusChanges,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function allowedActions(Teletest $teletest, AccessService $access): array
    {
        $status = $this->normalizeStatus((string) $teletest->status);
        $role = $this->resolveRole($access);
        if ($status === null || $role === null) {
            return [];
        }

        $actions = (array) config("teletest_workflow.allowed_actions_by_role.{$status}.{$role}", []);
        return array_values(array_map('strval', $actions));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function runAction(Teletest $teletest, string $actionKey, array $payload, AccessService $access): array
    {
        $fromStatus = $this->normalizeStatus((string) $teletest->status);
        if ($fromStatus === null) {
            abort(422, 'Current teletest status is not recognized by workflow contract.');
        }

        $role = $this->resolveRole($access);
        if ($role === null) {
            abort(403, 'Forbidden');
        }

        $actionKey = strtolower(trim($actionKey));
        $allowed = $this->allowedActions($teletest, $access);
        if (!in_array($actionKey, $allowed, true)) {
            abort(403, "Action '{$actionKey}' is not allowed for role '{$role}' in status '{$fromStatus}'.");
        }

        $toStatus = $this->resolveTargetStatus($fromStatus, $role, $actionKey, $payload);
        if ($toStatus === null || $toStatus === $fromStatus) {
            return $this->buildStatePayload($teletest, $access);
        }

        $allowedTransitions = (array) config("teletest_workflow.allowed_transitions.{$fromStatus}", []);
        if (!in_array($toStatus, $allowedTransitions, true)) {
            abort(422, "Illegal transition '{$fromStatus}' -> '{$toStatus}'.");
        }

        $this->assertGuardRules($teletest, $fromStatus, $toStatus, $payload);

        DB::transaction(function () use ($teletest, $fromStatus, $toStatus, $actionKey, $role, $access, $payload): void {
            $this->applyTransition($teletest, $toStatus, $payload);
            $teletest->save();

            $this->recordStatusHistory($teletest, $fromStatus, $toStatus, $actionKey, $role, $access, $payload);
            $this->recordStatusChange($teletest, $fromStatus, $toStatus, $payload);
        });

        $teletest->refresh();

        return $this->buildStatePayload($teletest, $access);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function transitionSystem(
        Teletest $teletest,
        string $toStatus,
        string $reasonCode,
        ?string $reasonNote = null,
        array $metadata = []
    ): Teletest {
        $fromStatus = $this->normalizeStatus((string) $teletest->status);
        $toStatus = $this->normalizeStatus($toStatus);

        if ($fromStatus === null || $toStatus === null) {
            abort(422, 'Unknown teletest workflow status for system transition.');
        }
        if ($fromStatus === $toStatus) {
            return $teletest;
        }

        $allowedTransitions = (array) config("teletest_workflow.allowed_transitions.{$fromStatus}", []);
        if (!in_array($toStatus, $allowedTransitions, true)) {
            abort(422, "Invalid teletest status transition: {$fromStatus} -> {$toStatus}.");
        }

        if ($fromStatus === 'awaiting_payment' && $toStatus === 'scheduled') {
            if (!$teletest->payment_id) {
                abort(422, 'A paid payment is required before scheduling.');
            }
            $payment = Payment::find($teletest->payment_id);
            if (!$payment || $payment->status !== 'paid') {
                abort(422, 'Payment must be paid before scheduling.');
            }
        }

        DB::transaction(function () use ($teletest, $fromStatus, $toStatus, $reasonCode, $reasonNote, $metadata): void {
            $this->applyTransition($teletest, $toStatus, [
                'reason_code' => $reasonCode,
                'reason_note' => $reasonNote,
                'metadata' => $metadata,
            ]);
            $teletest->save();

            $this->recordStatusHistory(
                $teletest,
                $fromStatus,
                $toStatus,
                'system_transition',
                'system',
                null,
                [
                    'reason_code' => $reasonCode,
                    'reason_note' => $reasonNote,
                    'metadata' => $metadata,
                ]
            );
            $this->recordStatusChange($teletest, $fromStatus, $toStatus, ['reason_note' => $reasonNote]);
        });

        return $teletest->refresh();
    }

    private function normalizeStatus(string $status): ?string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return null;
        }

        $statuses = (array) config('teletest_workflow.statuses', []);
        if (array_key_exists($status, $statuses)) {
            return $status;
        }

        $aliases = (array) config('teletest_workflow.status_aliases', []);
        if (array_key_exists($status, $aliases)) {
            return (string) $aliases[$status];
        }

        // Legacy teletest status compatibility.
        $legacyMap = [
            'pending_payment' => 'awaiting_payment',
            'completed' => 'visit_completed',
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
            // Backend role is "carer"; workflow contract uses "clinician".
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
    private function resolveTargetStatus(string $fromStatus, string $role, string $actionKey, array $payload): ?string
    {
        $explicitTarget = $payload['target_status'] ?? null;
        if (is_string($explicitTarget) && trim($explicitTarget) !== '') {
            $normalized = $this->normalizeStatus($explicitTarget);
            if ($normalized === null) {
                abort(422, "Unknown target_status '{$explicitTarget}'.");
            }
            return $normalized;
        }

        if ($actionKey === 'cancel_request' && $role === 'clinician') {
            return 'cancelled_by_technician';
        }

        $rawTarget = (string) (config("teletest_workflow.action_targets.{$actionKey}") ?? '');
        if ($rawTarget === '' || $rawTarget === '__stay') {
            return null;
        }

        if (str_starts_with($rawTarget, '__dynamic_')) {
            if ($rawTarget === '__dynamic_reschedule') {
                return match ($role) {
                    'patient' => 'reschedule_requested_by_patient',
                    'clinician' => 'reschedule_requested_by_technician',
                    'hospital' => 'reschedule_requested_by_hospital',
                    default => null,
                };
            }
            abort(422, "Unsupported dynamic action target '{$rawTarget}'.");
        }

        return $this->normalizeStatus($rawTarget);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertGuardRules(Teletest $teletest, string $fromStatus, string $toStatus, array $payload): void
    {
        if ($fromStatus === 'awaiting_payment' && $toStatus === 'scheduled') {
            if (!$teletest->payment_id) {
                abort(422, 'A paid payment is required before scheduling.');
            }
            $payment = Payment::find($teletest->payment_id);
            if (!$payment || $payment->status !== 'paid') {
                abort(422, 'Payment must be paid before scheduling.');
            }
        }

        if ($fromStatus === 'arrived' && $toStatus === 'in_progress') {
            if (($payload['check_in_confirmed'] ?? false) !== true) {
                abort(422, 'check_in_confirmed=true is required to start in-progress visit.');
            }
        }

        if ($fromStatus === 'in_progress' && $toStatus === 'sample_collected') {
            $sampleEvidence = $payload['sample_evidence'] ?? null;
            if (!is_array($sampleEvidence) || $sampleEvidence === []) {
                abort(422, 'sample_evidence is required before marking sample_collected.');
            }
        }

        if ($fromStatus === 'lab_processing' && $toStatus === 'result_ready') {
            $validated = DB::table('teletest_results')
                ->where('teletest_id', $teletest->id)
                ->whereNotNull('validated_at')
                ->exists();
            if (!$validated) {
                abort(422, 'A validated teletest result is required before result_ready.');
            }
        }

        if ($fromStatus === 'result_ready' && $toStatus === 'result_delivered') {
            $delivered = DB::table('teletest_result_deliveries')
                ->where('teletest_id', $teletest->id)
                ->where('delivery_status', 'delivered')
                ->exists();
            if (!$delivered) {
                abort(422, 'At least one delivered result delivery event is required before result_delivered.');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyTransition(Teletest $teletest, string $toStatus, array $payload): void
    {
        $now = Carbon::now();

        $teletest->status = $toStatus;
        $teletest->status_description = (string) (config("teletest_workflow.statuses.{$toStatus}.label") ?? $toStatus);
        $teletest->status_reason = isset($payload['reason_code']) ? (string) $payload['reason_code'] : $teletest->status_reason;
        $teletest->status_reason_note = isset($payload['reason_note']) ? (string) $payload['reason_note'] : $teletest->status_reason_note;

        if (isset($payload['eta_minutes']) && is_numeric($payload['eta_minutes'])) {
            $teletest->current_eta_minutes = (int) $payload['eta_minutes'];
            $teletest->eta_last_updated_at = $now;
        }

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
            $incoming = $payload['reassigned_to'] ?? null;
            if (is_numeric($incoming)) {
                $teletest->reassigned_to = (int) $incoming;
            }
        }

        if ($toStatus === 'sample_collected') {
            DB::table('teletest_sample_events')->insert([
                'teletest_id' => $teletest->id,
                'event_type' => 'collected',
                'actor_id' => $payload['actor_id'] ?? null,
                'payload_json' => isset($payload['sample_evidence']) ? json_encode($payload['sample_evidence']) : null,
                'event_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($toStatus === 'sample_rejected') {
            DB::table('teletest_sample_events')->insert([
                'teletest_id' => $teletest->id,
                'event_type' => 'rejected',
                'actor_id' => $payload['actor_id'] ?? null,
                'payload_json' => isset($payload['metadata']) ? json_encode($payload['metadata']) : null,
                'event_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($toStatus === 'recollection_required') {
            DB::table('teletest_recollections')->insert([
                'teletest_id' => $teletest->id,
                'reason_code' => $payload['reason_code'] ?? null,
                'requested_at' => $now,
                'status' => 'requested',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordStatusHistory(
        Teletest $teletest,
        string $fromStatus,
        string $toStatus,
        string $actionKey,
        string $role,
        ?AccessService $access,
        array $payload
    ): void {
        $actorId = null;
        if ($role !== 'system' && $access !== null) {
            $actorId = match ($role) {
                'patient' => $access->currentPatientId(),
                'clinician' => $access->currentCarerId(),
                'hospital' => $access->currentHospitalId(),
                default => null,
            };
        }

        DB::table('teletest_status_history')->insert([
            'teletest_id' => $teletest->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action_key' => $actionKey,
            'actor_role' => $role,
            'actor_id' => $actorId,
            'reason_code' => $payload['reason_code'] ?? null,
            'reason_note' => $payload['reason_note'] ?? null,
            'metadata_json' => isset($payload['metadata']) ? json_encode($payload['metadata']) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function recordStatusChange(Teletest $teletest, string $fromStatus, string $toStatus, array $payload): void
    {
        $reason = isset($payload['reason_note']) ? (string) $payload['reason_note'] : null;
        $this->statusChanges->record('teletest', $teletest->id, $fromStatus, $toStatus, $reason);
        $this->notifications->notifyStatusChange(
            'teletest',
            $teletest->id,
            $fromStatus,
            $toStatus,
            ['reason' => $reason]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatePayload(Teletest $teletest, AccessService $access): array
    {
        $allowed = $this->allowedActions($teletest, $access);
        return [
            'id' => (int) $teletest->id,
            'status' => (string) $teletest->status,
            'status_description' => (string) ($teletest->status_description ?? ''),
            'allowed_actions' => $allowed,
            'status_timestamps' => [
                'scheduled_at' => $teletest->scheduled_at,
                'departed_at' => $teletest->departed_at,
                'arrived_at' => $teletest->arrived_at,
                'started_at' => $teletest->started_at,
                'completed_at' => $teletest->completed_at,
                'cancelled_at' => $teletest->cancelled_at,
                'no_show_at' => $teletest->no_show_at,
            ],
            'eta' => [
                'minutes' => $teletest->current_eta_minutes,
                'last_updated_at' => $teletest->eta_last_updated_at,
            ],
            'ux_hints' => [
                'primary_cta' => $allowed[0] ?? null,
                'supporting_copy' => null,
            ],
        ];
    }
}
