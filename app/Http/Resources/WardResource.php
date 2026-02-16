<?php

namespace App\Http\Resources;

use App\Http\Controllers\ward_dashboard;
use App\Models\Carer;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\ward_note;
use Illuminate\Http\Resources\Json\JsonResource;

class WardResource extends JsonResource
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
        $appt= Appointment::find($this->appt_id);
        $notes= ward_note::select(['text'])->where('ward_id',$this->id)->get();
      
        $noting=array();

        foreach($notes as $noted=>$x){

            array_push($noting,$x['text']);
        }
      
      
        $wd= new ward_dashboard;

        return [
         
            'id'=>(string)$this->id,
                'patient_id'=>$this->patient_id,
               'patient'=> new PatientLiteResource($patient),
                'carer_id'=>$this->carer_id,
                'carer'=> new CarerLiteResource($carer),
                'hospital_id'=>$this->hospital_id,
                'appt_id'=>$this->appt_id,
                'appointment'=> new AppointmentResource($appt),
                'diagnosis'=>$this->diagnosis,
               'admission_date'=>$this->admission_date,
                'ward_vitals'=>$this->ward_vitals,
                'discharged'=>$this->discharged,
                'discharge_date'=>$this->discharge_date,
                'discharge_summary'=>$this->discharge_summary,
                'notes'=> $noting,
                'priority'=>$this->priority,
                'timelines'=> $wd->getWardTimeline($this->id),
                'ward_prescriptions'=> $wd->getWardPrescriptions($this->id),
                'ward_vitalsList' => $wd->getPatientVitals($this->id),
                 'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at
            
            ];
    }
}
