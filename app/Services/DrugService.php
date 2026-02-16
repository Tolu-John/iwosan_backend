<?php

namespace App\Services;

use App\Models\Drug;
use App\Services\StatusChangeService;

class DrugService
{
    private const STATUS_ACTIVE = 'active';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_DISCONTINUED = 'discontinued';

    private StatusChangeService $statusChanges;

    public function __construct(StatusChangeService $statusChanges)
    {
        $this->statusChanges = $statusChanges;
    }

    public function create(array $data, AccessService $access): Drug
    {
        $drug = new Drug();
        $this->fillDrug($drug, $data);

        $deny = $access->denyIfFalse($access->canAccessDrug($drug));
        if ($deny) {
            abort(403, 'Forbidden');
        }

        $drug->save();

        $this->statusChanges->record('drug', $drug->id, null, $drug->status, $data['status_reason'] ?? null);

        return $drug;
    }

    public function update(Drug $drug, array $data, AccessService $access): Drug
    {
        $fromStatus = $drug->status;
        $this->fillDrug($drug, $data);

        $deny = $access->denyIfFalse($access->canAccessDrug($drug));
        if ($deny) {
            abort(403, 'Forbidden');
        }

        $drug->save();

        if ($fromStatus !== $drug->status) {
            $this->statusChanges->record('drug', $drug->id, $fromStatus, $drug->status, $data['status_reason'] ?? null);
        }

        return $drug;
    }

    private function fillDrug(Drug $drug, array $data): void
    {
        $drug->consultation_id = $data['consultation_id'];
        $drug->ward_id = $data['ward_id'];
        $drug->drug_type = $data['drug_type'];
        $drug->dosage = $data['dosage'];
        $drug->name = $data['name'];
        $drug->duration = $data['duration'];
        $drug->quantity = $data['quantity'];
        $drug->start_date = $data['start_date'];
        $drug->stop_date = $data['stop_date'] ?? null;
        $drug->status = $data['status'];
        $drug->status_reason = $data['status_reason'] ?? null;
        $drug->extra_notes = $data['extra_notes'];
        $drug->carer_name = $data['carer_name'];

        $drug->started = $data['status'] === self::STATUS_ACTIVE;
        $drug->finished = in_array($data['status'], [self::STATUS_COMPLETED, self::STATUS_DISCONTINUED], true);
    }
}
