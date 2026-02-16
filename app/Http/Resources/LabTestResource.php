<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use Illuminate\Http\Resources\Json\JsonResource;

class LabTestResource extends JsonResource
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
                'done'=>$this->done,
                'status'=>$this->status,
                'status_reason'=>$this->status_reason,
                'scheduled_at'=>$this->scheduled_at,
                'collected_at'=>$this->collected_at,
                'resulted_at'=>$this->resulted_at,
                'test_name'=>$this->test_name,
                'lab_recomm'=>$this->lab_recomm,
                'extra_notes'=>$this->extra_notes,
    ];
    }
}
