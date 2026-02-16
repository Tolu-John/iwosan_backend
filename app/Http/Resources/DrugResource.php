<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use Illuminate\Http\Resources\Json\JsonResource;

class DrugResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'=>(string)$this->id,
                'consultation_id'=>$this->consultation_id,
                'ward_id'=>$this->ward_id,
                'name'=>$this->name,
                'duration'=>$this->duration,
                'quantity'=>$this->quantity,
                'status'=>$this->status,
                'status_reason'=>$this->status_reason,
                'started'=>$this->started,
                'finished'=>$this->finished,
                'carer_name'=>$this->carer_name,
                'dosage'=>$this->dosage,
                'stop_date'=>$this->stop_date,
                'start_date'=>$this->start_date,
                'drug_type'=>$this->drug_type,
                'extra_notes'=>$this->extra_notes, 
            ];

    }
}
