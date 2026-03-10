<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use App\Models\Patient;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
        $consultation = $this->relationLoaded('consultation') ? $this->consultation : Consultation::find($this->consultation_id);
        $carer = $consultation?->carer;
        $carerUser = $carer?->user;
        $hospital = $carer?->hospital;
        $carerName = trim((string) (($carerUser?->firstname ?? '') . ' ' . ($carerUser?->lastname ?? '')));
        if ($carerName === '') {
            $carerName = $carerUser?->name ?? '';
        }

        return  [
         
            'id'=>(string)$this->id,
                'patient_id'=>$this->patient_id,
                'patient'=> new PatientLiteResource($patient),
                'carer_id'=>$this->carer_id,
                'consultation_id'=>$this->consultation_id,
                'visit_type' => $consultation?->treatment_type,
                'visit_date' => $consultation?->date_time,
                'verified' => (bool) $consultation,
                'text'=>$this->text,
                'rating'=>(string)$this->rating,
                'recomm' => (bool) $this->recomm ? 'yes' : 'no',
                'recommend' => (bool) $this->recomm,
                'tags' => is_array($this->tags) ? $this->tags : [],
                'status' => $this->status,
                'response_text' => $this->response_text,
                'response_at' => $this->response_at,
                'response_by' => $this->response_by,
                'edited_at' => $this->edited_at,
                'deleted_reason' => $this->deleted_reason,
                'carer_name' => $carerName !== '' ? $carerName : null,
                'carer_role' => $carer?->position,
                'carer_avatar' => $carerUser?->user_img ?? $carer?->avatar,
                'hospital_name' => $hospital?->name,
            ];
    }
}
