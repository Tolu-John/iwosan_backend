<?php

namespace App\Http\Resources;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $patient = $this->relationLoaded('patient') ? $this->patient : Patient::find($this->patient_id);
        $hospital = $this->relationLoaded('hospital') ? $this->hospital : Hospital::find($this->hospital_id);
        $assignee = $this->relationLoaded('assignee') ? $this->assignee : null;

        return [
            'id'=>(string)$this->id,
            'patient_id'=>$this->patient_id,
            'patient'=>new PatientLiteResource($patient),
            'hospital_id'=>$this->hospital_id,
            'hospital'=> new HospitalLiteResource($hospital),
            'title'=>$this->title,
            'complaint'=>$this->complaint,
            'category'=>$this->category,
            'severity'=>$this->severity,
            'status'=>$this->status,
            'assigned_to'=>$this->assigned_to,
            'assignee'=>$assignee ? [
                'id' => (string) $assignee->id,
                'firstname' => $assignee->firstname,
                'lastname' => $assignee->lastname,
                'email' => $assignee->email,
            ] : null,
            'first_response_at'=>$this->first_response_at,
            'response_notes'=>$this->response_notes,
            'resolved_at'=>$this->resolved_at,
            'resolution_notes'=>$this->resolution_notes,
            'closed_at'=>$this->closed_at,
            'rejected_at'=>$this->rejected_at,
            'rejection_reason'=>$this->rejection_reason,
            'created_at'=>$this->created_at,
        ];
    }
}
