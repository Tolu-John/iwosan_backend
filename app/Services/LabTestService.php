<?php

namespace App\Services;

use App\Models\LabTest;
use App\Services\StatusChangeService;
use Carbon\Carbon;

class LabTestService
{
    private const STATUS_ORDERED = 'ordered';
    private const STATUS_SCHEDULED = 'scheduled';
    private const STATUS_COLLECTED = 'collected';
    private const STATUS_RESULTED = 'resulted';

    private StatusChangeService $statusChanges;

    public function __construct(StatusChangeService $statusChanges)
    {
        $this->statusChanges = $statusChanges;
    }

    public function create(array $data, AccessService $access): LabTest
    {
        $labTest = new LabTest();
        $this->fillLabTest($labTest, $data, null);

        $deny = $access->denyIfFalse($access->canAccessLabTest($labTest));
        if ($deny) {
            abort(403, 'Forbidden');
        }

        $labTest->save();

        $this->statusChanges->record('lab_test', $labTest->id, null, $labTest->status, $data['status_reason'] ?? null);

        return $labTest;
    }

    public function update(LabTest $labTest, array $data, AccessService $access): LabTest
    {
        $fromStatus = $labTest->status ?? self::STATUS_ORDERED;
        $this->assertTransitionAllowed($fromStatus, $data['status']);
        $this->fillLabTest($labTest, $data, $fromStatus);

        $deny = $access->denyIfFalse($access->canAccessLabTest($labTest));
        if ($deny) {
            abort(403, 'Forbidden');
        }

        $labTest->save();

        if ($fromStatus !== $labTest->status) {
            $this->statusChanges->record('lab_test', $labTest->id, $fromStatus, $labTest->status, $data['status_reason'] ?? null);
        }

        return $labTest;
    }

    private function fillLabTest(LabTest $labTest, array $data, ?string $fromStatus): void
    {
        $labTest->consultation_id = $data['consultation_id'];
        $labTest->ward_id = $data['ward_id'];
        $labTest->test_name = $data['test_name'];
        $labTest->lab_recomm = $data['lab_recomm'];
        $labTest->extra_notes = $data['extra_notes'] ?? null;
        $labTest->status = $data['status'];
        $labTest->status_reason = $data['status_reason'] ?? null;

        $this->applyStatusTimestamps($labTest, $fromStatus, $data['status']);
        $labTest->done = $data['status'] === self::STATUS_RESULTED;
    }

    private function applyStatusTimestamps(LabTest $labTest, ?string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        $now = Carbon::now();

        if ($toStatus === self::STATUS_SCHEDULED && !$labTest->scheduled_at) {
            $labTest->scheduled_at = $now;
        }

        if ($toStatus === self::STATUS_COLLECTED && !$labTest->collected_at) {
            $labTest->collected_at = $now;
        }

        if ($toStatus === self::STATUS_RESULTED && !$labTest->resulted_at) {
            $labTest->resulted_at = $now;
        }
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = [
            self::STATUS_ORDERED => [self::STATUS_SCHEDULED, self::STATUS_COLLECTED, self::STATUS_RESULTED],
            self::STATUS_SCHEDULED => [self::STATUS_COLLECTED, self::STATUS_RESULTED],
            self::STATUS_COLLECTED => [self::STATUS_RESULTED],
            self::STATUS_RESULTED => [],
        ];

        $next = $allowed[$from] ?? [];
        if (!in_array($to, $next, true)) {
            abort(422, 'Invalid lab test status transition.');
        }
    }
}
