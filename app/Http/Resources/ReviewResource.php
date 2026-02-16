<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
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
                'recomm'=>(string)$this->recomm,
                'tags' => $this->tags ? json_decode($this->tags, true) : [],
                'status' => $this->status,
                'response_text' => $this->response_text,
                'response_at' => $this->response_at,
                'response_by' => $this->response_by,
                'edited_at' => $this->edited_at,
                'deleted_reason' => $this->deleted_reason,
            ];
    }
}
