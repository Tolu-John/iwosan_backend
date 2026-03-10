<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Certlice;
use App\Models\Complaints;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\Gen_Vital;
use App\Models\Hospital;
use App\Models\LabTest;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Test;
use App\Models\Teletest;
use App\Models\Transfers;
use App\Models\ward;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AccessService
{
    public function accessibleWardIds(): array
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId) {
            return ward::where('patient_id', $currentPatientId)->pluck('id')->all();
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId) {
            return ward::where('carer_id', $currentCarerId)->pluck('id')->all();
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId) {
            return ward::where('hospital_id', $currentHospitalId)->pluck('id')->all();
        }

        return [];
    }

    public function accessiblePatientIds(): array
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId) {
            return [$currentPatientId];
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId) {
            return collect()
                ->merge(Appointment::where('carer_id', $currentCarerId)->pluck('patient_id'))
                ->merge(Consultation::where('carer_id', $currentCarerId)->pluck('patient_id'))
                ->merge(Teletest::where('carer_id', $currentCarerId)->pluck('patient_id'))
                ->merge(ward::where('carer_id', $currentCarerId)->pluck('patient_id'))
                ->unique()
                ->values()
                ->all();
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');

            return collect()
                ->merge(Consultation::where('hospital_id', $currentHospitalId)->pluck('patient_id'))
                ->merge(Teletest::where('hospital_id', $currentHospitalId)->pluck('patient_id'))
                ->merge(ward::where('hospital_id', $currentHospitalId)->pluck('patient_id'))
                ->merge(Appointment::whereIn('carer_id', $carerIds)->pluck('patient_id'))
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }
    public function currentPatientId(): ?int
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }
        return Patient::where('user_id', $user->id)->value('id');
    }

    public function currentCarerId(): ?int
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }
        return Carer::where('user_id', $user->id)->value('id');
    }

    public function currentHospitalId(): ?int
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }
        return Hospital::where('user_id', $user->id)
            ->orWhere('firedb_id', $user->firedb_id)
            ->value('id');
    }

    public function canAccessPatient(int $patientId): bool
    {
        $patientId = (int) $patientId;

        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && $currentPatientId === $patientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId) {
            return Appointment::where('patient_id', $patientId)->where('carer_id', $currentCarerId)->exists()
                || Consultation::where('patient_id', $patientId)->where('carer_id', $currentCarerId)->exists()
                || Teletest::where('patient_id', $patientId)->where('carer_id', $currentCarerId)->exists()
                || ward::where('patient_id', $patientId)->where('carer_id', $currentCarerId)->exists();
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');

            return Consultation::where('patient_id', $patientId)->where('hospital_id', $currentHospitalId)->exists()
                || Teletest::where('patient_id', $patientId)->where('hospital_id', $currentHospitalId)->exists()
                || ward::where('patient_id', $patientId)->where('hospital_id', $currentHospitalId)->exists()
                || Appointment::where('patient_id', $patientId)->whereIn('carer_id', $carerIds)->exists();
        }

        return false;
    }

    public function canAccessCarer(Carer $carer): bool
    {
        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $carer->id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $carer->hospital_id === (int) $currentHospitalId) {
            return true;
        }

        // Any authenticated patient can view carer profiles
        if ($this->currentPatientId()) {
            return true;
        }

        return false;
    }

    public function canAccessHospital(Hospital $hospital): bool
    {
        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $hospital->id === (int) $currentHospitalId) {
            return true;
        }

        // Any authenticated patient can view hospital profiles
        if ($this->currentPatientId()) {
            return true;
        }

        return false;
    }

    public function canAccessAppointment(Appointment $appointment): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $appointment->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $appointment->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            return $carerIds->contains($appointment->carer_id);
        }

        return false;
    }

    public function canAccessConsultation(Consultation $consultation): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $consultation->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $consultation->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $consultation->hospital_id === (int) $currentHospitalId) {
            return true;
        }

        return false;
    }

    public function canAccessTeletest(Teletest $teletest): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $teletest->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $teletest->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $teletest->hospital_id === (int) $currentHospitalId) {
            return true;
        }

        return false;
    }

    public function canAccessPayment(Payment $payment): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $payment->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $payment->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            return $carerIds->contains($payment->carer_id);
        }

        return false;
    }

    public function canAccessLabResult(LabResult $labResult): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $labResult->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $labResult->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            return $carerIds->contains($labResult->carer_id);
        }

        return false;
    }

    public function canAccessWard(ward $ward): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $ward->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $ward->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $ward->hospital_id === (int) $currentHospitalId) {
            return true;
        }

        return false;
    }

    public function denyIfFalse(bool $allowed)
    {
        if (!$allowed) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function canAccessComplaint(Complaints $complaint): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $complaint->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $complaint->hospital_id === (int) $currentHospitalId) {
            return true;
        }

        return false;
    }

    public function canAccessReview(Review $review): bool
    {
        $currentPatientId = $this->currentPatientId();
        if ($currentPatientId && (int) $review->patient_id === (int) $currentPatientId) {
            return true;
        }

        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $review->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId) {
            $consultation = Consultation::find($review->consultation_id);
            return $consultation && (int) $consultation->hospital_id === (int) $currentHospitalId;
        }

        return false;
    }

    public function canAccessLabTest(LabTest $labTest): bool
    {
        $consultation = Consultation::find($labTest->consultation_id);
        if ($consultation && $this->canAccessConsultation($consultation)) {
            return true;
        }

        if ($labTest->ward_id) {
            $ward = ward::find($labTest->ward_id);
            if ($ward && $this->canAccessWard($ward)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessDrug(Drug $drug): bool
    {
        $consultation = Consultation::find($drug->consultation_id);
        if ($consultation && $this->canAccessConsultation($consultation)) {
            return true;
        }

        if ($drug->ward_id) {
            $ward = ward::find($drug->ward_id);
            if ($ward && $this->canAccessWard($ward)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessTest(Test $test): bool
    {
        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $test->hospital_id === (int) $currentHospitalId) {
            return true;
        }

        // Allow authenticated patients to view tests
        if ($this->currentPatientId()) {
            return true;
        }

        return false;
    }

    public function canAccessTransfer(Transfers $transfer): bool
    {
        $currentCarerId = $this->currentCarerId();
        if ($currentCarerId && (int) $transfer->carer_id === (int) $currentCarerId) {
            return true;
        }

        $currentHospitalId = $this->currentHospitalId();
        if ($currentHospitalId && (int) $transfer->hospital_id === (int) $currentHospitalId) {
            return true;
        }

        return false;
    }

    public function canAccessGenVital(Gen_Vital $vital): bool
    {
        return $this->canAccessPatient((int) $vital->patient_id);
    }

    public function canAccessCertlice(Certlice $certlice, bool $write = false): bool
    {
        $type = strtolower($certlice->type);

        if ($type === 'carer') {
            $currentCarerId = $this->currentCarerId();
            if ($currentCarerId && (int) $certlice->type_id === (int) $currentCarerId) {
                return true;
            }

            $currentHospitalId = $this->currentHospitalId();
            if ($currentHospitalId && !$write) {
                return true;
            }

            if ($this->currentPatientId() && !$write) {
                return true;
            }
        }

        if ($type === 'hospital') {
            $currentHospitalId = $this->currentHospitalId();
            if ($currentHospitalId && (int) $certlice->type_id === (int) $currentHospitalId) {
                return true;
            }

            if ($this->currentPatientId() && !$write) {
                return true;
            }
        }

        return false;
    }
}
