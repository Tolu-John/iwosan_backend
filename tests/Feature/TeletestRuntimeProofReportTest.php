<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Teletest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TeletestRuntimeProofReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_generates_runtime_proof_report_for_all_teletest_workflow_rows(): void
    {
        [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();
        $alternateCarer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $actionsByRole = (array) config('teletest_workflow.allowed_actions_by_role', []);
        $rows = [];
        $failures = [];

        foreach ($actionsByRole as $status => $roleActions) {
            foreach (['patient', 'clinician', 'hospital'] as $role) {
                $actions = array_values(array_map('strval', (array) ($roleActions[$role] ?? [])));
                if ($actions === []) {
                    continue;
                }

                foreach ($actions as $actionKey) {
                    $teletest = $this->makeTeletestFixture($status, $patient, $carer, $hospital);
                    $actor = match ($role) {
                        'patient' => $patientUser,
                        'clinician' => $clinicianUser,
                        'hospital' => $hospitalUser,
                    };

                    $this->primeGuardDependencies($teletest, $actionKey, $alternateCarer->id);
                    $payload = $this->payloadForAction($actionKey, $alternateCarer->id);

                    Passport::actingAs($actor);
                    $response = $this->postJson(
                        "/api/v1/teletest/{$teletest->id}/actions/{$actionKey}",
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

    private function makeTeletestFixture(
        string $status,
        Patient $patient,
        Carer $carer,
        Hospital $hospital
    ): Teletest {
        return Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => $status,
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);
    }

    private function primeGuardDependencies(Teletest $teletest, string $actionKey, int $alternateCarerId): void
    {
        $actionKey = strtolower(trim($actionKey));

        if ($actionKey === 'pay_now') {
            $payment = Payment::factory()->create([
                'patient_id' => $teletest->patient_id,
                'carer_id' => $teletest->carer_id,
                'status' => 'paid',
                'type' => 'tele_test',
            ]);
            $teletest->payment_id = $payment->id;
            $teletest->save();
        }

        if ($actionKey === 'notify_patient') {
            DB::table('teletest_result_deliveries')->insert([
                'teletest_id' => $teletest->id,
                'channel' => 'in_app',
                'delivery_status' => 'delivered',
                'delivered_at' => now(),
                'receipt_ref' => 'DELIVERY-RUNTIME-PROOF',
                'payload_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForAction(string $actionKey, int $alternateCarerId): array
    {
        $actionKey = strtolower(trim($actionKey));

        return match ($actionKey) {
            'start_visit', 'confirm_check_in' => ['check_in_confirmed' => true],
            'complete_visit' => ['sample_evidence' => ['sample_id' => 'RUNTIME-PROOF-SAMPLE']],
            'update_eta' => ['eta_minutes' => 20],
            'reassign_technician', 'assign_technician', 'reassign_urgent' => ['reassigned_to' => $alternateCarerId],
            default => [],
        };
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function writeCsvReport(array $rows): void
    {
        $path = base_path('../execution_plans/TELETEST_RUNTIME_PROOF_REPORT.csv');
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
            '# Teletest Runtime Proof Summary',
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
            base_path('../execution_plans/TELETEST_RUNTIME_PROOF_SUMMARY.md'),
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
