<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VirtualVisitRuntimeProofReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_generates_runtime_proof_report_for_all_virtual_workflow_rows(): void
    {
        [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();
        $alternateCarer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $actionsByRole = (array) config('virtual_visit_workflow.allowed_actions_by_role', []);
        $rows = [];
        $failures = [];

        foreach ($actionsByRole as $status => $roleActions) {
            foreach (['patient', 'clinician', 'hospital'] as $role) {
                $actions = array_values(array_map('strval', (array) ($roleActions[$role] ?? [])));
                if ($actions === []) {
                    continue;
                }

                foreach ($actions as $actionKey) {
                    $appointment = $this->makeVirtualAppointmentFixture($status, $patient, $carer);
                    $actor = match ($role) {
                        'patient' => $patientUser,
                        'clinician' => $clinicianUser,
                        'hospital' => $hospitalUser,
                    };

                    $payload = $this->payloadForAction($actionKey, $alternateCarer->id);
                    $payload = array_merge(
                        $payload,
                        $this->primeGuardDependencies($appointment, $actionKey, $alternateCarer->id)
                    );

                    Passport::actingAs($actor);
                    $response = $this->postJson(
                        "/api/v1/appointment/{$appointment->id}/virtual-actions/{$actionKey}",
                        $payload
                    );

                    $resolvedStatus = $response->json('status');
                    $message = $response->json('message');
                    $httpStatus = $response->getStatusCode();
                    $pass = $httpStatus === 200;

                    $rows[] = [
                        'status_key' => $status,
                        'role' => $role,
                        'action_key' => $actionKey,
                        'http_status' => (string) $httpStatus,
                        'pass_fail' => $pass ? 'pass' : 'fail',
                        'resolved_status' => is_string($resolvedStatus) ? $resolvedStatus : '',
                        'message' => is_string($message) ? $message : '',
                    ];

                    if (!$pass) {
                        $failures[] = sprintf(
                            '%s/%s/%s => %d (%s)',
                            $status,
                            $role,
                            $actionKey,
                            $httpStatus,
                            is_string($message) ? $message : 'no_message'
                        );
                    }
                }
            }
        }

        $this->writeCsvReport($rows);
        $this->writeSummaryReport($rows, $failures);

        $this->assertNotEmpty($rows);
    }

    private function makeVirtualAppointmentFixture(
        string $status,
        Patient $patient,
        Carer $carer
    ): Appointment {
        return Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => $status,
            'payment_id' => null,
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function primeGuardDependencies(
        Appointment $appointment,
        string $actionKey,
        int $alternateCarerId
    ): array {
        $actionKey = strtolower(trim($actionKey));
        $payload = [];

        if (in_array($actionKey, [
            'request_changes',
            'reject',
            'decline',
            'submit_reason',
            'file_failure_report',
            'submit_failure_summary',
            'submit_response',
            'resolve',
            'terminate_case',
            'submit_final_note',
            'escalate',
        ], true)) {
            $payload['status_reason_note'] = 'Runtime proof reason note.';
        }

        if ($actionKey === 'pay_now') {
            $payment = Payment::factory()->create([
                'patient_id' => $appointment->patient_id,
                'carer_id' => $appointment->carer_id,
                'status' => 'paid',
                'type' => 'virtual_visit',
            ]);
            $appointment->payment_id = $payment->id;
            $appointment->save();
            $payload['payment_id'] = $payment->id;
            $payload['status_reason'] = 'payment_completed';
        }

        if ($actionKey === 'provide_consent') {
            $payload['consent_accepted'] = true;
            $payload['consent_version'] = 'runtime-proof-v1';
        }

        if ($actionKey === 'start_consultation') {
            $appointment->consent_granted_at = now()->subMinute();
            $appointment->session_ready_at = now()->subMinute();
            $appointment->save();
        }

        if ($actionKey === 'submit_report') {
            $payload['closeout_submitted'] = true;
            $payload['status_reason_note'] = 'Runtime proof closeout submitted.';
        }

        if ($actionKey === 'process_refund') {
            $payment = Payment::factory()->create([
                'patient_id' => $appointment->patient_id,
                'carer_id' => $appointment->carer_id,
                'status' => 'refund_pending',
                'type' => 'virtual_visit',
            ]);
            $appointment->payment_id = $payment->id;
            $appointment->save();
        }

        if (in_array($actionKey, ['reassign'], true)) {
            $payload['carer_id'] = $alternateCarerId;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForAction(string $actionKey, int $alternateCarerId): array
    {
        $actionKey = strtolower(trim($actionKey));

        return match ($actionKey) {
            'accept_new_slot', 'approve_slot', 'propose_alternative', 'reschedule' => [
                'proposed_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
            ],
            'reassign' => ['carer_id' => $alternateCarerId],
            default => [],
        };
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function writeCsvReport(array $rows): void
    {
        $path = base_path('../execution_plans/VIRTUAL_VISIT_RUNTIME_PROOF_REPORT.csv');
        $stream = fopen($path, 'w');
        if ($stream === false) {
            return;
        }

        fputcsv($stream, [
            'status_key',
            'role',
            'action_key',
            'http_status',
            'pass_fail',
            'resolved_status',
            'message',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['status_key'],
                $row['role'],
                $row['action_key'],
                $row['http_status'],
                $row['pass_fail'],
                $row['resolved_status'],
                $row['message'],
            ]);
        }

        fclose($stream);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<int, string> $failures
     */
    private function writeSummaryReport(array $rows, array $failures): void
    {
        $total = count($rows);
        $passed = count(array_filter($rows, static fn ($row) => $row['pass_fail'] === 'pass'));
        $failed = $total - $passed;

        $lines = [
            '# Virtual Visit Runtime Proof Summary',
            "- Actions executed: {$total}",
            "- Passed: {$passed}",
            "- Failed: {$failed}",
            '',
            '## Failed actions',
        ];

        if ($failures === []) {
            $lines[] = '- none';
        } else {
            foreach ($failures as $failure) {
                $lines[] = "- {$failure}";
            }
        }

        file_put_contents(
            base_path('../execution_plans/VIRTUAL_VISIT_RUNTIME_PROOF_SUMMARY.md'),
            implode("\n", $lines)
        );
    }

    /**
     * @return array{0:User,1:User,2:User,3:Patient,4:Carer,5:Hospital}
     */
    private function seedActors(): array
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);

        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);

        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $clinicianUser = User::query()->findOrFail($carer->user_id);

        return [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital];
    }
}
