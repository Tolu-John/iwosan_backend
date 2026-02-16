<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Patient;
use Illuminate\Http\Resources\Json\JsonResource;

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

        $patient=Patient::find($this->patient_id);
        $carer= Carer::find($this->carer_id);
    
        return [
         
            'id'=>(string)$this->id,
                'patient_id'=>$this->patient_id,
               'patient'=> new PatientResource($patient),
                'carer_id'=>$this->carer_id,
                'carer'=> new CarerLiteResource($carer),
                'payment_id'=>$this->payment_id,
                'ward_id'=>$this->ward_id,
                'status'=>$this->status,
                'status_description'=>$this->status_description,
                'scheduled_at'=>$this->scheduled_at,
                'started_at'=>$this->started_at,
                'completed_at'=>$this->completed_at,
                'cancelled_at'=>$this->cancelled_at,
                'no_show_at'=>$this->no_show_at,
                'address'=>$this->address,
                'price'=>$this->price,
                'consult_type'=>$this->consult_type,
                'consult_id'=>$this->consult_id,
                 'extra_notes'=>$this->extra_notes,
                 'channel'=>$this->channel,
                'appointment_type'=>$this->appointment_type,
                'date_time'=>$this->date_time,
                'admin_approved'=>$this->admin_approved,

            
            ];
    }
}
