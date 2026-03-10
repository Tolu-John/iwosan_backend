<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Drug;
use App\Models\HConsultation;
use App\Models\Hospital;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\VConsultation;
use App\Services\ConsultationClinicalRecordBuilder;
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
        $patient = Patient::find($this->patient_id);
        $carer = Carer::find($this->carer_id);
        $hospital = Hospital::find($this->hospital_id);
        $payment = Payment::find($this->payment_id);
        $vConsultation = VConsultation::where('consultation_id', $this->id)->first();
        $hConsultation = HConsultation::where('consultation_id', $this->id)->first();

        $clinicalRecord = (new ConsultationClinicalRecordBuilder())
            ->buildForConsultationId((int) $this->id);

        return [
            'id' => (string) $this->id,
            'patient_id' => $this->patient_id,
            'patient' => $patient ? new PatientLiteResource($patient) : null,
            'carer_id' => $this->carer_id,
            'carer' => $carer ? new CarerLiteResource($carer) : null,
            'payment_id' => $this->payment_id,
            'payment' => [
                'id' => $payment?->id,
                'price' => $payment?->price,
                'status' => $payment?->status,
            ],
            'hospital_id' => $this->hospital_id,
            'hospital' => $hospital ? new HospitalLiteResource($hospital) : null,
            'review_id' => $this->review_id,
            'review_submitted' => $this->review_id !== null,
            'status' => $this->status,
            'status_description' => $this->status_description,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'no_show_at' => $this->no_show_at,
            'treatment_type' => $this->treatment_type,
            'diagnosis' => $this->diagnosis,
            'consult_notes' => $this->consult_notes,
            'date_time' => $this->date_time,
            'clinical_record' => $clinicalRecord,
            'vConsultation' => $vConsultation ? new VConsultationResource($vConsultation) : null,
            'hConsultation' => $hConsultation ? new HConsultationResource($hConsultation) : null,
            'drugs' => DrugResource::collection(Drug::where('consultation_id', $this->id)->get()),
            'labtests' => LabTestResource::collection(LabTest::where('consultation_id', $this->id)->get()),
        ];
    }
}
