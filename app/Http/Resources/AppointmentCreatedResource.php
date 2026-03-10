<?php

namespace App\Http\Resources;

use App\Models\Carer;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentCreatedResource extends JsonResource
{
    public function toArray($request): array
    {
        $carer = Carer::with(['user', 'hospital'])->find($this->carer_id);
        $carerName = trim((string) (($carer?->user?->firstname ?? '').' '.($carer?->user?->lastname ?? '')));
        $hospitalName = trim((string) ($carer?->hospital?->name ?? ''));

        return [
            'id' => (string) $this->id,
            'status' => $this->status,
            'status_description' => $this->status_description,
            'owned_by_role' => $this->owned_by_role,
            'owned_by_id' => $this->owned_by_id,
            'next_action_at' => $this->next_action_at,
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
            'consent_accepted' => (bool) $this->consent_accepted,
            'attachments' => $this->attachments_json ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
