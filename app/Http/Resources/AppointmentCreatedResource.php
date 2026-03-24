<?php

namespace App\Http\Resources;

use App\Models\Carer;
use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentCreatedResource extends JsonResource
{
    public function toArray($request): array
    {
        $carer = Carer::with(['user', 'hospital'])->find($this->carer_id);
        $carerName = trim((string) (($carer?->user?->firstname ?? '').' '.($carer?->user?->lastname ?? '')));
        $hospitalName = trim((string) ($carer?->hospital?->name ?? ''));
        $type = strtolower(trim((string) ($this->appointment_type ?? '')));
        $isHomeVisit = str_contains($type, 'home');
        $rawStatusKey = strtolower(trim((string) $this->status));
        $statusKey = $isHomeVisit && $rawStatusKey === 'requested'
            ? 'awaiting_hospital_approval'
            : $rawStatusKey;
        $actorRole = null;
        $authUserId = (int) optional($request->user())->id;
        if ($authUserId > 0) {
            if ((int) optional($carer?->user)->id === $authUserId) {
                $actorRole = 'clinician';
            } elseif ((int) optional($carer?->hospital)->user_id === $authUserId) {
                $actorRole = 'hospital';
            } else {
                $actorRole = 'patient';
            }
        }
        $allowedActions = $actorRole
            ? Arr::wrap(config("home_visit_workflow.role_actions.{$statusKey}.{$actorRole}", []))
            : [];
        $statusDescription = $this->status_description;
        if ($statusKey !== $rawStatusKey) {
            $statusDescription = (string) (config("home_visit_workflow.statuses.{$statusKey}.label")
                ?? $statusDescription
                ?? $statusKey);
        }

        return [
            'id' => (string) $this->id,
            'status' => $statusKey,
            'status_description' => $statusDescription,
            'workflow_terminal' => (bool) config("home_visit_workflow.statuses.{$statusKey}.terminal", false),
            'allowed_actions' => $allowedActions,
            'owned_by_role' => $this->owned_by_role,
            'owned_by_id' => $this->owned_by_id,
            'next_action_at' => $this->next_action_at,
            'sla_state' => $this->resolveSlaState(),
            'approved_at' => $this->approved_at,
            'departed_at' => $this->departed_at,
            'arrived_at' => $this->arrived_at,
            'appointment_type' => $this->appointment_type,
            'consult_type' => $this->consult_type,
            'date_time' => $this->date_time,
            'price' => $this->price,
            'carer_id' => $this->carer_id,
            'carer_name' => $carerName !== '' ? $carerName : null,
            'carer_role' => $carer?->position,
            'carer_avatar' => $carer?->avatar,
            'hospital_id' => $carer?->hospital?->id,
            'hospital_name' => $hospitalName !== '' ? $hospitalName : null,
            'hospital_phone' => $carer?->hospital?->phone,
            'hospital_email' => $carer?->hospital?->email,
            'hospital_address' => $carer?->hospital?->address,
            'channel' => $this->channel,
            'dispatch_model' => $this->dispatch_model,
            'address_source' => $this->address_source,
            'contact_profile' => $this->contact_profile,
            'visit_reason' => $this->visit_reason,
            'preferred_window' => $this->preferred_window,
            'home_window_code' => $this->home_window_code,
            'home_window_label' => $this->home_window_label,
            'expected_duration' => $this->expected_duration,
            'red_flags' => $this->red_flags_json ?? [],
            'preferred_hospital_id' => $this->preferred_hospital_id,
            'preferred_hospital_name' => $this->preferred_hospital_name,
            'preferred_clinician_id' => $this->preferred_clinician_id,
            'preferred_clinician_name' => $this->preferred_clinician_name,
            'preference_note' => $this->preference_note,
            'additional_notes' => $this->additional_notes,
            'visit_contact_name' => $this->visit_contact_name,
            'visit_contact_phone' => $this->visit_contact_phone,
            'assignment_source' => $this->assignment_source,
            'current_eta_minutes' => $this->current_eta_minutes,
            'status_reason' => $this->status_reason,
            'status_reason_code' => $this->status_reason_code,
            'status_reason_note' => $this->status_reason_note,
            'consent_accepted' => (bool) $this->consent_accepted,
            'attachments' => $this->attachments_json ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
}
