<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Drug;
use App\Models\HConsultation;
use App\Models\Hospital;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\VConsultation;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
     
        if($this->treatment_type=='Virtual visit'){
         
            return  [
                'id'=>(string)$this->id,
                    'patient_id'=>$this->patient_id,
                    'patient'=>new PatientLiteResource(Patient::find($this->patient_id)),
                    'carer_id'=>$this->carer_id,
                    'carer'=>new CarerLiteResource(Carer::find($this->carer_id)),
                    'payment_id'=>$this->payment_id,
                      'payment'=>['price'=> (Payment::find($this->payment_id)?->price)],
                     'hospital_id'=>$this->hospital_id,
                    'review_id'=>$this->review_id,
                    'status'=>$this->status,
                    'status_description'=>$this->status_description,
                    'scheduled_at'=>$this->scheduled_at,
                    'started_at'=>$this->started_at,
                    'completed_at'=>$this->completed_at,
                    'cancelled_at'=>$this->cancelled_at,
                    'no_show_at'=>$this->no_show_at,
                    'treatment_type'=>$this->treatment_type,
                    'diagnosis'=>$this->diagnosis,
                    'consult_notes'=>$this->consult_notes,
                    'date_time'=>$this->date_time,
                    'vConsultation'=> new VConsultationResource(VConsultation::where('consultation_id',$this->id)->first()),
                    'drugs'=>DrugResource::collection (Drug::where('consultation_id',$this->id)->get()),
                    'labtests'=>LabTestResource::collection (LabTest::where('consultation_id',$this->id)->get()), 
                
                ];

        }
        
        else if($this->treatment_type=='Home visit' || $this->treatment_type=='Home visit Admitted' ){

            return  [
                'id'=>(string)$this->id,
                'patient_id'=>$this->patient_id,
                    'patient'=>new PatientLiteResource(Patient::find($this->patient_id)),
                    'carer_id'=>$this->carer_id,
                    'carer'=>new CarerLiteResource(Carer::find($this->carer_id)),
                    'payment_id'=>$this->payment_id,
                    'payment'=>['price'=> (Payment::find($this->payment_id)?->price)],
                     'hospital_id'=>$this->hospital_id,
                    'review_id'=>$this->review_id,
                    'status'=>$this->status,
                    'status_description'=>$this->status_description,
                    'scheduled_at'=>$this->scheduled_at,
                    'started_at'=>$this->started_at,
                    'completed_at'=>$this->completed_at,
                    'cancelled_at'=>$this->cancelled_at,
                    'no_show_at'=>$this->no_show_at,
                    'treatment_type'=>$this->treatment_type,
                    'diagnosis'=>$this->diagnosis,
                    'consult_notes'=>$this->consult_notes,
                    'date_time'=>$this->date_time,
                    'hConsultation'=>new HConsultationResource(HConsultation::where('consultation_id',$this->id)->first()),
                'drugs'=>DrugResource::collection (Drug::where('consultation_id',$this->id)->get()),
                'labtests'=>LabTestResource::collection (LabTest::where('consultation_id',$this->id)->get())
                ];

        }



        
    }
}
