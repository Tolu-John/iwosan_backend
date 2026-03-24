<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Services\ConsultationClinicalRecordBuilder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $patient = Patient::find($this->patient_id);
        $carer = Carer::find($this->carer_id);
        $resolvedHospitalId = (int) ($this->hospital_id ?: optional($carer)->hospital_id ?: 0);
        $hospital = $resolvedHospitalId > 0 ? Hospital::find($resolvedHospitalId) : null;
        $consultation = Consultation::find($this->consult_id);
        $clinicalRecord = (new ConsultationClinicalRecordBuilder())
            ->buildForConsultationId($consultation?->id);
        $rawStatusKey = strtolower(trim((string) $this->status));
        $actorRole = $this->resolveActorRole($request, $patient, $carer, $hospital);
        $roleKey = $actorRole === 'carer' ? 'clinician' : $actorRole;
        $workflowConfig = $this->resolveWorkflowConfig($rawStatusKey);
        $workflowKey = $workflowConfig['key'];
        $statusKey = $this->normalizeStatusForWorkflow($rawStatusKey, $workflowKey);
        $encounterType = match ($workflowConfig['key']) {
            'virtual_visit_workflow' => 'virtual_visit',
            'teletest_workflow' => 'teletest',
            default => 'home_visit',
        };
        $allowedActions = [];
        if ($roleKey && $workflowConfig['enabled']) {
            $allowedActions = Arr::wrap(config("{$workflowKey}.{$workflowConfig['actions_path']}.{$statusKey}.{$roleKey}", []));
        }
        $teletestId = $this->resolveLinkedTeletestId();
        $terminalStatuses = array_map('strval', (array) config("{$workflowKey}.terminal_statuses", []));
        $workflowTerminal = in_array($statusKey, $terminalStatuses, true)
            || (bool) config("{$workflowKey}.statuses.{$statusKey}.terminal", false);
        $statusDescription = $this->status_description;
        if ($statusKey !== $rawStatusKey) {
            $statusDescription = (string) (config("{$workflowKey}.statuses.{$statusKey}.label")
                ?? $statusDescription
                ?? $statusKey);
        }
        $quoteSummary = $this->latestQuoteSummary();
        $billingSummary = $this->latestBillingSummary();
        $escalationSummary = $this->latestEscalationSummary();
        $disputeSummary = $this->latestDisputeSummary();
        $refundSummary = $this->latestRefundSummary();
        $joinWindowMinutes = $workflowKey === 'virtual_visit_workflow'
            ? $this->virtualJoinWindowMinutes()
            : null;
        $lateJoinAllowanceMinutes = $workflowKey === 'virtual_visit_workflow'
            ? $this->virtualLateJoinAllowanceMinutes()
            : null;
    
        return [
         
            'id'=>(string)$this->id,
                'patient_id'=>$this->patient_id,
               'patient'=> $patient ? new PatientResource($patient) : null,
                'carer_id'=>$this->carer_id,
                'carer'=> $carer ? new CarerLiteResource($carer) : null,
                'hospital_id' => $resolvedHospitalId > 0 ? $resolvedHospitalId : null,
                'hospital' => $hospital ? new HospitalLiteResource($hospital) : null,
                'payment_id'=>$this->payment_id,
                'ward_id'=>$this->ward_id,
                'status'=>$statusKey,
                'status_description'=>$statusDescription,
                'workflow_terminal'=>$workflowTerminal,
                'allowed_actions'=>$allowedActions,
                'owned_by_role'=>$this->owned_by_role,
                'owned_by_id'=>$this->owned_by_id,
                'next_action_at'=>$this->next_action_at,
                'sla_state'=>$this->resolveSlaState(),
                'scheduled_at'=>$this->scheduled_at,
                'approved_at'=>$this->approved_at,
                'departed_at'=>$this->departed_at,
                'arrived_at'=>$this->arrived_at,
                'started_at'=>$this->started_at,
                'completed_at'=>$this->completed_at,
                'cancelled_at'=>$this->cancelled_at,
                'no_show_at'=>$this->no_show_at,
                'assignment_source'=>$this->assignment_source,
                'current_eta_minutes'=>$this->current_eta_minutes,
                'quote_summary'=>$quoteSummary,
                'billing_summary'=>$billingSummary,
                'escalation_summary'=>$escalationSummary,
                'dispute_summary'=>$disputeSummary,
                'refund_summary'=>$refundSummary,
                'address'=>$this->address,
                'address_lat'=>$this->address_lat,
                'address_lon'=>$this->address_lon,
                'price'=>$this->price,
                'consult_type'=>$this->consult_type,
                'consult_id'=>$this->consult_id,
                'consultation_id'=>$consultation?->id,
                'consultation_status'=>$consultation?->status,
                'teletest_id'=>$teletestId,
                'diagnosis'=>$consultation?->diagnosis,
                'consult_notes'=>$consultation?->consult_notes,
                'clinical_record'=>$clinicalRecord,
                 'extra_notes'=>$this->extra_notes,
                 'consent_accepted'=>(bool)$this->consent_accepted,
                'attachments'=>$this->attachments_json ?? [],
                'channel'=>$this->channel,
                'dispatch_model'=>$this->dispatch_model,
                'address_source'=>$this->address_source,
                'contact_profile'=>$this->contact_profile,
                'visit_reason'=>$this->visit_reason,
                'preferred_window'=>$this->preferred_window,
                'home_window_code'=>$this->home_window_code,
                'home_window_label'=>$this->home_window_label,
                'expected_duration'=>$this->expected_duration,
                'red_flags'=>$this->red_flags_json ?? [],
                'preferred_hospital_id'=>$this->preferred_hospital_id,
                'preferred_hospital_name'=>$this->preferred_hospital_name,
                'preferred_clinician_id'=>$this->preferred_clinician_id,
                'preferred_clinician_name'=>$this->preferred_clinician_name,
                'preference_note'=>$this->preference_note,
                'additional_notes'=>$this->additional_notes,
                'visit_contact_name'=>$this->visit_contact_name,
                'visit_contact_phone'=>$this->visit_contact_phone,
                'appointment_type'=>$this->appointment_type,
                'encounter_type'=>$encounterType,
                'date_time'=>$this->date_time,
                'join_window_minutes'=>$joinWindowMinutes,
                'late_join_allowance_minutes'=>$lateJoinAllowanceMinutes,
                'admin_approved'=>$this->admin_approved,
                'status_reason'=>$this->status_reason,
                'status_reason_code'=>$this->status_reason_code,
                'status_reason_note'=>$this->status_reason_note,

            
            ];
    }

    private function virtualJoinWindowMinutes(): int
    {
        $configured = (int) (
            config('virtual_visit_workflow.sla_windows.waiting_room_open.join_window_minutes')
            ?? config('virtual_visit_workflow.sla_windows.waiting_room_open.open_lead_minutes')
            ?? 15
        );

        return $configured > 0 ? $configured : 15;
    }

    private function virtualLateJoinAllowanceMinutes(): int
    {
        $configured = (int) (
            config('virtual_visit_workflow.sla_windows.waiting_room_open.late_join_allowance_minutes')
            ?? 120
        );

        return $configured >= 0 ? $configured : 120;
    }

    private function normalizeStatusForWorkflow(string $statusKey, string $workflowKey): string
    {
        if ($workflowKey === 'home_visit_workflow' && $statusKey === 'requested') {
            return 'awaiting_hospital_approval';
        }

        return $statusKey;
    }

    private function latestQuoteSummary(): ?array
    {
        $quote = DB::table('home_admission_quotes')
            ->where('appointment_id', (int) $this->id)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first([
                'id',
                'version',
                'quote_status',
                'currency',
                'enrollment_fee_minor',
                'recurring_fee_minor',
                'billing_cycle',
                'grand_total_minor',
                'valid_until',
            ]);
        if (!$quote) {
            return null;
        }

        return [
            'quote_id' => (int) $quote->id,
            'version' => (int) $quote->version,
            'status' => $quote->quote_status,
            'currency' => $quote->currency,
            'enrollment_fee_minor' => (int) $quote->enrollment_fee_minor,
            'recurring_fee_minor' => (int) $quote->recurring_fee_minor,
            'billing_cycle' => $quote->billing_cycle,
            'grand_total_minor' => (int) $quote->grand_total_minor,
            'valid_until' => $quote->valid_until,
        ];
    }

    private function latestBillingSummary(): ?array
    {
        $episode = DB::table('home_care_episodes')
            ->where('appointment_id', (int) $this->id)
            ->orderByDesc('id')
            ->first(['id']);
        if (!$episode) {
            return null;
        }

        $cycle = DB::table('episode_billing_cycles')
            ->where('episode_id', (int) $episode->id)
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'billing_status',
                'amount_due_minor',
                'amount_paid_minor',
                'due_at',
                'grace_until',
            ]);
        if (!$cycle) {
            return null;
        }

        return [
            'billing_cycle_id' => (int) $cycle->id,
            'billing_status' => $cycle->billing_status,
            'amount_due_minor' => (int) $cycle->amount_due_minor,
            'amount_paid_minor' => (int) $cycle->amount_paid_minor,
            'due_at' => $cycle->due_at,
            'grace_until' => $cycle->grace_until,
        ];
    }

    private function latestEscalationSummary(): ?array
    {
        $escalation = DB::table('escalations')
            ->where('appointment_id', (int) $this->id)
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'status',
                'severity',
                'pathway',
                'opened_at',
                'resolved_at',
            ]);
        if (!$escalation) {
            return null;
        }

        return [
            'escalation_id' => (int) $escalation->id,
            'status' => $escalation->status,
            'severity' => $escalation->severity,
            'pathway' => $escalation->pathway,
            'opened_at' => $escalation->opened_at,
            'resolved_at' => $escalation->resolved_at,
        ];
    }

    private function latestDisputeSummary(): ?array
    {
        $dispute = DB::table('virtual_visit_disputes')
            ->where('appointment_id', (int) $this->id)
            ->orderByDesc('id')
            ->first([
                'id',
                'status',
                'reason_code',
                'reason_note',
                'opened_at',
                'resolved_at',
            ]);
        if (!$dispute) {
            return null;
        }

        return [
            'dispute_id' => (int) $dispute->id,
            'status' => $dispute->status,
            'reason_code' => $dispute->reason_code,
            'reason_note' => $dispute->reason_note,
            'opened_at' => $dispute->opened_at,
            'resolved_at' => $dispute->resolved_at,
        ];
    }

    private function latestRefundSummary(): ?array
    {
        if (!$this->payment_id) {
            return null;
        }

        $payment = DB::table('payments')
            ->where('id', (int) $this->payment_id)
            ->first([
                'id',
                'status',
                'status_reason',
                'reference',
                'gateway',
                'refunded_at',
                'updated_at',
            ]);
        if (!$payment) {
            return null;
        }

        return [
            'payment_id' => (int) $payment->id,
            'status' => $payment->status,
            'status_reason' => $payment->status_reason,
            'reference' => $payment->reference ?? null,
            'gateway' => $payment->gateway ?? null,
            'refunded_at' => $payment->refunded_at,
            'updated_at' => $payment->updated_at,
        ];
    }

    private function resolveSlaState(): ?string
    {
        if (!$this->next_action_at) {
            return null;
        }

        $next = $this->next_action_at instanceof \Carbon\Carbon
            ? $this->next_action_at
            : \Carbon\Carbon::parse((string) $this->next_action_at);

        return $next->lessThanOrEqualTo(now()) ? 'overdue' : 'pending';
    }

    private function resolveActorRole($request, ?Patient $patient, ?Carer $carer, ?Hospital $hospital): ?string
    {
        $userId = (int) optional($request->user())->id;
        if ($userId <= 0) {
            return null;
        }

        if ((int) optional(optional($patient)->user)->id === $userId) {
            return 'patient';
        }
        if ((int) optional(optional($carer)->user)->id === $userId) {
            return 'carer';
        }
        if ((int) ($hospital->user_id ?? 0) === $userId) {
            return 'hospital';
        }

        return null;
    }

    /**
     * @return array{key: string, actions_path: string, enabled: bool}
     */
    private function resolveWorkflowConfig(string $statusKey): array
    {
        $type = strtolower(trim((string) ($this->appointment_type ?? '')));
        $consultType = strtolower(trim((string) ($this->consult_type ?? '')));
        $isHome = str_contains($type, 'home');
        $isVirtual = str_contains($type, 'virtual');
        $isTeletest = str_contains($type, 'teletest')
            || str_contains($type, 'tele_test')
            || str_contains($type, 'lab')
            || str_contains($type, 'test')
            || str_contains($consultType, 'teletest')
            || str_contains($consultType, 'lab')
            || str_contains($consultType, 'test');

        if ($isVirtual) {
            return [
                'key' => 'virtual_visit_workflow',
                'actions_path' => 'allowed_actions_by_role',
                'enabled' => (bool) config('virtual_visit_workflow.enabled', true),
            ];
        }

        if ($isTeletest) {
            return [
                'key' => 'teletest_workflow',
                'actions_path' => 'allowed_actions_by_role',
                'enabled' => (bool) config('teletest_workflow.enabled', true),
            ];
        }

        if ($isHome) {
            return [
                'key' => 'home_visit_workflow',
                'actions_path' => 'role_actions',
                'enabled' => (bool) config('home_visit_workflow.enabled', true),
            ];
        }

        if (array_key_exists($statusKey, (array) config('teletest_workflow.statuses', []))) {
            return [
                'key' => 'teletest_workflow',
                'actions_path' => 'allowed_actions_by_role',
                'enabled' => (bool) config('teletest_workflow.enabled', true),
            ];
        }

        if (array_key_exists($statusKey, (array) config('home_visit_workflow.statuses', []))) {
            return [
                'key' => 'home_visit_workflow',
                'actions_path' => 'role_actions',
                'enabled' => (bool) config('home_visit_workflow.enabled', true),
            ];
        }

        if (array_key_exists($statusKey, (array) config('virtual_visit_workflow.statuses', []))) {
            return [
                'key' => 'virtual_visit_workflow',
                'actions_path' => 'allowed_actions_by_role',
                'enabled' => (bool) config('virtual_visit_workflow.enabled', true),
            ];
        }

        return [
            'key' => 'home_visit_workflow',
            'actions_path' => 'role_actions',
            'enabled' => (bool) config('home_visit_workflow.enabled', true),
        ];
    }

    private function resolveLinkedTeletestId(): ?int
    {
        $type = strtolower(trim((string) ($this->appointment_type ?? '')));
        $consultType = strtolower(trim((string) ($this->consult_type ?? '')));
        $isTeletest = str_contains($type, 'teletest')
            || str_contains($type, 'tele_test')
            || str_contains($type, 'lab')
            || str_contains($type, 'test')
            || str_contains($consultType, 'teletest')
            || str_contains($consultType, 'lab')
            || str_contains($consultType, 'test');
        if (!$isTeletest) {
            return null;
        }

        $bySameId = DB::table('teletests')
            ->where('id', (int) $this->id)
            ->value('id');
        if ($bySameId !== null) {
            return (int) $bySameId;
        }

        $query = DB::table('teletests')
            ->where('patient_id', (int) $this->patient_id)
            ->where('carer_id', (int) $this->carer_id);
        if ($this->date_time) {
            $query->where('date_time', (string) $this->date_time);
        }

        $id = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
