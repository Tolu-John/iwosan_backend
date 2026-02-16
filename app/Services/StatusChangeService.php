<?php

namespace App\Services;

use App\Models\StatusChangeLog;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Teletest;
use App\Models\MetricEvent;
use Illuminate\Support\Facades\Auth;

class StatusChangeService
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function record(string $modelType, int $modelId, ?string $from, string $to, ?string $reason = null): void
    {
        $actorId = Auth::id();
        $actorType = $this->resolveActorType();

        StatusChangeLog::create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'reason' => $reason,
        ]);

        $owners = $this->resolveOwners($modelType, $modelId);
        if (!$owners) {
            $owners = [[null, null]];
        }

        foreach ($owners as [$ownerType, $ownerId]) {
            MetricEvent::create([
                'event_type' => 'status_change',
                'model_type' => $modelType,
                'model_id' => $modelId,
                'actor_id' => $actorId,
                'actor_role' => $actorType,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'metadata' => [
                    'from' => $from,
                    'to' => $to,
                    'reason' => $reason,
                ],
            ]);
        }
    }

    private function resolveActorType(): ?string
    {
        if ($this->access->currentPatientId()) {
            return 'patient';
        }

        if ($this->access->currentCarerId()) {
            return 'carer';
        }

        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }

        return null;
    }

    private function resolveOwners(string $modelType, int $modelId): array
    {
        if ($modelType === 'appointment') {
            $appointment = Appointment::find($modelId);
            if (!$appointment) {
                return [];
            }
            $carerId = $appointment->carer_id;
            $hospitalId = $appointment->carer?->hospital_id;
            $owners = [];
            if ($appointment->patient_id) {
                $owners[] = ['patient', (int) $appointment->patient_id];
            }
            if ($carerId) {
                $owners[] = ['carer', (int) $carerId];
            }
            if ($hospitalId) {
                $owners[] = ['hospital', (int) $hospitalId];
            }
            return $owners;
        }

        if ($modelType === 'consultation') {
            $consultation = Consultation::find($modelId);
            if (!$consultation) {
                return [];
            }
            $owners = [];
            if ($consultation->patient_id) {
                $owners[] = ['patient', (int) $consultation->patient_id];
            }
            if ($consultation->carer_id) {
                $owners[] = ['carer', (int) $consultation->carer_id];
            }
            if ($consultation->hospital_id) {
                $owners[] = ['hospital', (int) $consultation->hospital_id];
            }
            return $owners;
        }

        if ($modelType === 'teletest') {
            $teletest = Teletest::find($modelId);
            if (!$teletest) {
                return [];
            }
            $owners = [];
            if ($teletest->patient_id) {
                $owners[] = ['patient', (int) $teletest->patient_id];
            }
            if ($teletest->carer_id) {
                $owners[] = ['carer', (int) $teletest->carer_id];
            }
            if ($teletest->hospital_id) {
                $owners[] = ['hospital', (int) $teletest->hospital_id];
            }
            return $owners;
        }

        return [];
    }
}
