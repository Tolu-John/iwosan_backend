<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Services\ConsultationClinicalRecordBuilder;
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

        $patient = Patient::find($this->patient_id);
        $carer = Carer::find($this->carer_id);
        $hospital = Hospital::find($this->hospital_id);
        $consultation = Consultation::find($this->consult_id);
        $clinicalRecord = (new ConsultationClinicalRecordBuilder())
            ->buildForConsultationId($consultation?->id);
    
        return [
         
            'id'=>(string)$this->id,
                'patient_id'=>$this->patient_id,
               'patient'=> $patient ? new PatientResource($patient) : null,
                'carer_id'=>$this->carer_id,
                'carer'=> $carer ? new CarerLiteResource($carer) : null,
                'hospital_id' => $this->hospital_id,
                'hospital' => $hospital ? new HospitalLiteResource($hospital) : null,
                'payment_id'=>$this->payment_id,
                'ward_id'=>$this->ward_id,
                'status'=>$this->status,
                'status_description'=>$this->status_description,
                'owned_by_role'=>$this->owned_by_role,
                'owned_by_id'=>$this->owned_by_id,
                'next_action_at'=>$this->next_action_at,
                'scheduled_at'=>$this->scheduled_at,
                'started_at'=>$this->started_at,
                'completed_at'=>$this->completed_at,
                'cancelled_at'=>$this->cancelled_at,
                'no_show_at'=>$this->no_show_at,
                'address'=>$this->address,
                'address_lat'=>$this->address_lat,
                'address_lon'=>$this->address_lon,
                'price'=>$this->price,
                'consult_type'=>$this->consult_type,
                'consult_id'=>$this->consult_id,
                'consultation_id'=>$consultation?->id,
                'consultation_status'=>$consultation?->status,
                'diagnosis'=>$consultation?->diagnosis,
                'consult_notes'=>$consultation?->consult_notes,
                'clinical_record'=>$clinicalRecord,
                 'extra_notes'=>$this->extra_notes,
                 'consent_accepted'=>(bool)$this->consent_accepted,
                 'attachments'=>$this->attachments_json ?? [],
                 'channel'=>$this->channel,
                'appointment_type'=>$this->appointment_type,
                'date_time'=>$this->date_time,
                'admin_approved'=>$this->admin_approved,

            
            ];
    }
}
