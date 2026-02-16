<?php

namespace App\Services;

use App\Models\Gen_Vital;
use App\Models\Patient;
use App\Models\VitalAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GenVitalService
{
    private const TYPE_RULES = [
        'temperature' => ['unit' => 'C', 'min' => 30, 'max' => 45, 'critical_low' => 35, 'critical_high' => 40],
        'heart_rate' => ['unit' => 'bpm', 'min' => 30, 'max' => 220, 'critical_low' => 40, 'critical_high' => 130],
        'respiratory_rate' => ['unit' => 'rpm', 'min' => 6, 'max' => 50, 'critical_low' => 8, 'critical_high' => 30],
        'oxygen_saturation' => ['unit' => '%', 'min' => 50, 'max' => 100, 'critical_low' => 90, 'critical_high' => 100],
        'blood_glucose' => ['unit' => 'mg/dL', 'min' => 30, 'max' => 600, 'critical_low' => 60, 'critical_high' => 300],
        'weight' => ['unit' => 'kg', 'min' => 1, 'max' => 500],
        'height' => ['unit' => 'm', 'min' => 0.5, 'max' => 2.5],
        'bmi' => ['unit' => 'kg/m2', 'min' => 10, 'max' => 60],
        'pain_score' => ['unit' => '/10', 'min' => 0, 'max' => 10],
    ];

    public function create(array $data, AccessService $access): Gen_Vital
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        $this->assertValidVital($data);
        $this->assertNoDuplicate($data['patient_id'], $data['type'], $data['taken_at']);

        $vital = new Gen_Vital();
        $this->fillVital($vital, $data);
        $vital->recorded_at = Carbon::now();
        $vital->save();

        $this->recordAudit($vital, 'created', $access);
        $this->updatePatientSummary($data['patient_id'], $vital);

        return $vital;
    }

    public function update(Gen_Vital $vital, array $data, AccessService $access): Gen_Vital
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        if ((int) $vital->patient_id !== (int) $data['patient_id']) {
            abort(403, 'Forbidden');
        }

        $this->assertValidVital($data);

        $this->fillVital($vital, $data);
        $vital->save();

        $this->recordAudit($vital, 'updated', $access);
        $this->updatePatientSummary($data['patient_id'], $vital);

        return $vital;
    }

    public function delete(Gen_Vital $vital, AccessService $access): void
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $vital->patient_id !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        $vital->delete();
        $this->recordAudit($vital, 'deleted', $access);
    }

    public function patientSnapshot(int $patientId): array
    {
        $vitals = Gen_Vital::where('patient_id', $patientId)
            ->orderBy('taken_at', 'desc')
            ->get();

        $latest = [];
        $series = [];
        $alerts = [];

        foreach ($vitals as $vital) {
            $type = $vital->type ?: $vital->name;
            if (!$type) {
                continue;
            }

            $series[$type][] = $vital;

            if (!isset($latest[$type])) {
                $latest[$type] = $vital;
            }

            if (in_array($vital->status_flag, ['out_of_range', 'critical'], true)) {
                $alerts[] = $vital;
            }
        }

        return [
            'latest' => $latest,
            'series' => $series,
            'alerts' => $alerts,
        ];
    }

    private function fillVital(Gen_Vital $vital, array $data): void
    {
        $type = $data['type'];
        $unit = $data['unit'];

        $vital->patient_id = $data['patient_id'];
        $vital->name = $type;
        $vital->type = $type;
        $vital->unit = $unit;
        $vital->value = $data['value'] ?? null;
        $vital->value_num = isset($data['value']) ? (float) $data['value'] : null;
        $vital->systolic = $data['systolic'] ?? null;
        $vital->diastolic = $data['diastolic'] ?? null;
        $vital->pulse = $data['pulse'] ?? null;
        $vital->taken_at = Carbon::parse($data['taken_at']);
        $vital->context = $data['context'];
        $vital->source = $data['source'];
        $vital->device_name = $data['device_name'] ?? null;
        $vital->device_model = $data['device_model'] ?? null;
        $vital->device_serial = $data['device_serial'] ?? null;
        $vital->location = $data['location'] ?? null;
        $vital->notes = $data['notes'] ?? null;
        $vital->status_flag = $this->computeStatusFlag($data);
    }

    private function assertValidVital(array $data): void
    {
        if ($data['type'] === 'blood_pressure') {
            $this->assertBloodPressure($data);
            return;
        }

        $rules = self::TYPE_RULES[$data['type']] ?? null;
        if (!$rules) {
            abort(422, 'Invalid vital type.');
        }

        if ($data['unit'] !== $rules['unit']) {
            abort(422, 'Invalid unit for vital type.');
        }

        if (!isset($data['value'])) {
            abort(422, 'Value is required for vital type.');
        }

        $value = (float) $data['value'];
        if ($value < $rules['min'] || $value > $rules['max']) {
            abort(422, 'Value out of allowed range.');
        }

        if ($data['source'] === 'device_sync') {
            if (empty($data['device_name']) || empty($data['device_model'])) {
                abort(422, 'Device information is required for device_sync source.');
            }
        }
    }

    private function assertBloodPressure(array $data): void
    {
        if ($data['unit'] !== 'mmHg') {
            abort(422, 'Invalid unit for blood pressure.');
        }

        $sys = (float) $data['systolic'];
        $dia = (float) $data['diastolic'];

        if ($sys < 60 || $sys > 250 || $dia < 30 || $dia > 150) {
            abort(422, 'Blood pressure out of allowed range.');
        }
    }

    private function assertNoDuplicate(int $patientId, string $type, string $takenAt): void
    {
        $taken = Carbon::parse($takenAt);
        $start = $taken->copy()->subMinutes(5);
        $end = $taken->copy()->addMinutes(5);

        $exists = Gen_Vital::where('patient_id', $patientId)
            ->where('type', $type)
            ->whereBetween('taken_at', [$start, $end])
            ->exists();

        if ($exists) {
            abort(422, 'Duplicate vital within 5 minutes.');
        }
    }

    private function computeStatusFlag(array $data): string
    {
        if ($data['type'] === 'blood_pressure') {
            $sys = (float) $data['systolic'];
            $dia = (float) $data['diastolic'];

            if ($sys >= 180 || $dia >= 120 || $sys < 80 || $dia < 50) {
                return 'critical';
            }

            if ($sys > 140 || $dia > 90 || $sys < 90 || $dia < 60) {
                return 'out_of_range';
            }

            return 'normal';
        }

        $rules = self::TYPE_RULES[$data['type']] ?? null;
        if (!$rules || !isset($data['value'])) {
            return 'normal';
        }

        $value = (float) $data['value'];

        if (isset($rules['critical_low'], $rules['critical_high'])) {
            if ($value < $rules['critical_low'] || $value > $rules['critical_high']) {
                return 'critical';
            }
        }

        if ($value < $rules['min'] || $value > $rules['max']) {
            return 'out_of_range';
        }

        return 'normal';
    }

    private function updatePatientSummary(int $patientId, Gen_Vital $vital): void
    {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return;
        }

        switch ($vital->type) {
            case 'temperature':
                $patient->temperature = $vital->value;
                break;
            case 'weight':
                $patient->weight = $vital->value;
                break;
            case 'blood_pressure':
                $patient->bp_sys = $vital->systolic;
                $patient->bp_dia = $vital->diastolic;
                break;
            case 'blood_glucose':
                $patient->sugar_level = $vital->value;
                break;
            case 'height':
                $patient->height = $vital->value;
                break;
        }

        $patient->save();
    }

    private function recordAudit(Gen_Vital $vital, string $action, AccessService $access): void
    {
        $actorId = auth()->id();
        $actorType = null;

        if ($access->currentPatientId()) {
            $actorType = 'patient';
        } elseif ($access->currentCarerId()) {
            $actorType = 'carer';
        } elseif ($access->currentHospitalId()) {
            $actorType = 'hospital';
        }

        VitalAuditLog::create([
            'vital_id' => $vital->id,
            'patient_id' => $vital->patient_id,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'action' => $action,
            'snapshot' => json_encode($vital->toArray()),
        ]);
    }
}
