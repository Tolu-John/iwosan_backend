<?php

namespace App\Http\Resources;

use App\Models\Hospital;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $status = 'draft';
        $reason = strtolower((string) ($this->status_reason ?? ''));
        if ($reason === 'archived') {
            $status = 'archived';
        } elseif ((bool) $this->is_active) {
            $status = 'published';
        }

        return [

            'id'=>(string)$this->id,
                'hospital_id'=>$this->hospital_id,
                'hospital'=>new HospitalLiteResource(Hospital::find($this->hospital_id)),
                'name'=>$this->name,
                'code'=>$this->code,
                'test_code'=>$this->code,
                'category'=>$this->category,
                'sample_type'=>$this->sample_type,
                'turnaround_time'=>$this->turnaround_time,
                'turnaround_sla_hours'=>$this->turnaround_time,
                'preparation_notes'=>$this->preparation_notes,
                'prep_instructions'=>$this->preparation_notes,
                'fasting_required'=>(bool) ($this->fasting_required ?? false),
                'is_active'=>(bool) $this->is_active,
                'status'=>$status,
                'status_reason'=>$this->status_reason,
                'extra_notes'=>$this->extra_notes,
                'description'=>$this->extra_notes,
                'price'=>$this->price,
                'cash_price'=>$this->cash_price,
                'hmo_price'=>$this->hmo_price,
                'emergency_price'=>$this->emergency_price,
                'created_at'=>optional($this->created_at)->toDateTimeString(),
                'updated_at'=>optional($this->updated_at)->toDateTimeString(),
                'updated_by'=>$this->updated_by ?? 'System',
               
        ];
    }
}
