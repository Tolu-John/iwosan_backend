<?php

namespace App\Services;

use App\Models\Carer;
use App\Models\LabResultAuditLog;
use App\Models\LabResult;
use App\Models\Teletest;
use App\Jobs\ProcessLabResultUploadJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LabResultService
{
    public function create(array $data, ?UploadedFile $file, AccessService $access, array $files = []): LabResult
    {
        $this->assertAccess($data, $access);
        $this->assertTeletestOwnership($data['teletest_id'] ?? null, $data['patient_id'], $data['carer_id'] ?? null);

        $labResult = new LabResult();
        $this->fillLabResult($labResult, $data);
        $this->applyFiles($labResult, $data['name'], $file, $files);
        $labResult->uploaded_at = $labResult->uploaded_at ?? now();
        $labResult->uploaded_by = Auth::id();
        $labResult->uploaded_role = $this->currentActorRole($access);
        $labResult->save();
        $this->logAudit($labResult, 'created', $labResult->getAttributes(), $access);
        ProcessLabResultUploadJob::dispatch($labResult->id);

        return $labResult;
    }

    public function update(LabResult $labResult, array $data, ?UploadedFile $file, AccessService $access, array $files = []): LabResult
    {
        $this->assertAccess($data, $access);
        $this->assertTeletestOwnership($data['teletest_id'] ?? null, $data['patient_id'], $data['carer_id'] ?? null);

        $before = $labResult->getAttributes();
        if ($file || $files) {
            $this->deleteStoredFile($labResult->result_picture);
            $this->deleteStoredFile($labResult->result_picture_front);
            $this->deleteStoredFile($labResult->result_picture_back);
            $this->applyFiles($labResult, $data['name'], $file, $files);
        }

        $this->fillLabResult($labResult, $data);
        $labResult->save();
        $changes = $this->diffChanges($before, $labResult->getAttributes());
        $this->logAudit($labResult, 'updated', $changes, $access);
        ProcessLabResultUploadJob::dispatch($labResult->id);

        return $labResult;
    }

    public function delete(LabResult $labResult): void
    {
        $labResult->delete();
    }

    public function forceDelete(LabResult $labResult): void
    {
        $this->deleteStoredFile($labResult->result_picture);
        $this->deleteStoredFile($labResult->result_picture_front);
        $this->deleteStoredFile($labResult->result_picture_back);
        $labResult->forceDelete();
    }

    private function fillLabResult(LabResult $labResult, array $data): void
    {
        $labResult->patient_id = $data['patient_id'];
        $labResult->carer_id = $data['carer_id'] ?? null;
        $labResult->teletest_id = $data['teletest_id'] ?? null;
        $labResult->name = trim($data['name'], "\"\'");
        $labResult->lab_name = trim($data['lab_name'], "\"\'");
        $labResult->extra_notes = trim($data['extra_notes'], "\"\'");
        $labResult->source = $data['source'] ?? $labResult->source;
    }

    private function applyFiles(LabResult $labResult, string $name, ?UploadedFile $file, array $files): void
    {
        if ($file) {
            $labResult->result_picture = $this->storeFile($file, $name);
            $labResult->result_picture_front = null;
            $labResult->result_picture_back = null;
            return;
        }

        $files = array_values(array_filter($files));
        if (!$files) {
            abort(422, 'At least one file is required.');
        }

        $front = $files[0] ?? null;
        $back = $files[1] ?? null;

        if ($front) {
            $labResult->result_picture_front = $this->storeFile($front, $name.'-front');
        }

        if ($back) {
            $labResult->result_picture_back = $this->storeFile($back, $name.'-back');
        }

        $labResult->result_picture = $labResult->result_picture_front ?? $labResult->result_picture_back;
    }

    private function assertAccess(array $data, AccessService $access): void
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId) {
            if ((int) $data['patient_id'] !== (int) $currentPatientId) {
                abort(403, 'Forbidden');
            }
            return;
        }

        $currentCarerId = $access->currentCarerId();
        if ($currentCarerId) {
            if (empty($data['carer_id']) || (int) $data['carer_id'] !== (int) $currentCarerId) {
                abort(403, 'Forbidden');
            }
            if (empty($data['teletest_id'])) {
                abort(422, 'Teletest is required.');
            }
            return;
        }

        $currentHospitalId = $access->currentHospitalId();
        if ($currentHospitalId) {
            if (empty($data['carer_id'])) {
                abort(422, 'Carer is required.');
            }
            if (empty($data['teletest_id'])) {
                abort(422, 'Teletest is required.');
            }
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            if (!$carerIds->contains($data['carer_id'])) {
                abort(403, 'Forbidden');
            }
            return;
        }
    }

    private function assertTeletestOwnership(?int $teletestId, int $patientId, ?int $carerId): void
    {
        if (!$teletestId) {
            return;
        }
        $teletest = Teletest::find($teletestId);
        if (!$teletest) {
            abort(422, 'Teletest not found.');
        }

        if ((int) $teletest->patient_id !== (int) $patientId) {
            abort(422, 'Teletest patient mismatch.');
        }

        if ($carerId === null || (int) $teletest->carer_id !== (int) $carerId) {
            abort(422, 'Teletest carer mismatch.');
        }
    }

    private function storeFile(UploadedFile $file, string $name): string
    {
        $base = Str::slug($name) ?: 'lab-result';
        $filename = sprintf('%s-%s.%s', $base, Str::random(8), $file->getClientOriginalExtension());

        $file->storeAs('labresult', $filename, 'iwosan_files');

        return url('/')."/api/storage/labresult/".$filename;
    }

    private function deleteStoredFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return;
        }

        $filename = basename($path);
        $location = 'labresult/'.$filename;

        if (Storage::disk('iwosan_files')->exists($location)) {
            Storage::disk('iwosan_files')->delete($location);
        }
    }

    private function logAudit(LabResult $labResult, string $action, ?array $changes, AccessService $access): void
    {
        LabResultAuditLog::create([
            'lab_result_id' => $labResult->id,
            'action' => $action,
            'changes' => $changes,
            'created_by' => Auth::id(),
            'created_role' => $this->currentActorRole($access),
        ]);
    }

    private function currentActorRole(AccessService $access): string
    {
        if ($access->currentPatientId()) {
            return 'patient';
        }
        if ($access->currentCarerId()) {
            return 'carer';
        }
        if ($access->currentHospitalId()) {
            return 'hospital';
        }

        return 'system';
    }

    private function diffChanges(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before)) {
                continue;
            }
            if ($before[$key] !== $value) {
                $changes[$key] = [
                    'from' => $before[$key],
                    'to' => $value,
                ];
            }
        }
        return $changes;
    }
}
