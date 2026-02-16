<?php

namespace App\Services;

use App\Http\Resources\CarerLiteResource;
use App\Http\Resources\DrugResource;
use App\Http\Resources\LabTestResource;
use App\Models\Consultation;

class PrescriptionService
{
    public function forPatient(int $patientId): array
    {
        $consultations = Consultation::with(['drugs', 'labtests', 'carer'])
            ->select(['id', 'patient_id', 'carer_id', 'date_time', 'updated_at'])
            ->where('patient_id', $patientId)
            ->orderBy('updated_at', 'desc')
            ->get();

        $result = [];

        foreach ($consultations as $consultation) {
            $activeDrugs = $consultation->drugs->where('status', 'active')->values();
            $pastDrugs = $consultation->drugs->whereIn('status', ['completed', 'discontinued'])->values();
            $activeLabTests = $consultation->labtests->whereIn('status', ['ordered', 'scheduled', 'collected'])->values();
            $pastLabTests = $consultation->labtests->where('status', 'resulted')->values();

            $result[] = [
                'consultation_id' => $consultation->id,
                'carer_id' => $consultation->carer_id,
                'patient_id' => $consultation->patient_id,
                'carer' => new CarerLiteResource($consultation->carer),
                'consul_date_time' => $consultation->date_time,
                'drugs' => DrugResource::collection($consultation->drugs),
                'lab_tests' => LabTestResource::collection($consultation->labtests),
                'drugs_active' => DrugResource::collection($activeDrugs),
                'drugs_past' => DrugResource::collection($pastDrugs),
                'lab_tests_active' => LabTestResource::collection($activeLabTests),
                'lab_tests_past' => LabTestResource::collection($pastLabTests),
            ];
        }

        return $result;
    }
}
