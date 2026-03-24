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
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HomeVisitRuntimeProofReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_generates_runtime_proof_report_for_all_home_workflow_rows(): void
    {
        [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();
        $alternateCarer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $actionsByRole = (array) config('home_visit_workflow.role_actions', []);
        $rows = [];
        $failures = [];

        foreach ($actionsByRole as $status => $roleActions) {
            foreach (['patient', 'clinician', 'hospital'] as $role) {
                $actions = array_values(array_map('strval', (array) ($roleActions[$role] ?? [])));
                if ($actions === []) {
                    continue;
                }

                foreach ($actions as $actionKey) {
                    $appointment = $this->makeHomeAppointmentFixture($status, $patient, $carer);
                    $this->seedBaseAdmissionQuote($appointment);
                    $payload = $this->payloadForAction($actionKey, $appointment, $alternateCarer->id);
                    $payload = array_merge(
                        $payload,
                        $this->primeGuardDependencies($appointment, $status, $actionKey)
                    );

                    $actor = match ($role) {
                        'patient' => $patientUser,
                        'clinician' => $clinicianUser,
                        'hospital' => $hospitalUser,
                    };

                    Passport::actingAs($actor);
                    $response = $this->postJson(
                        "/api/v1/appointment/{$appointment->id}/actions/{$actionKey}",
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

    private function makeHomeAppointmentFixture(
        string $status,
        Patient $patient,
        Carer $carer
    ): Appointment {
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'paid',
            'type' => 'home_visit',
        ]);

        return Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => $status,
            'payment_id' => $payment->id,
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedBaseAdmissionQuote(Appointment $appointment): void
    {
        DB::table('home_admission_quotes')->insert([
            'appointment_id' => $appointment->id,
            'version' => 1,
            'currency' => 'NGN',
            'enrollment_fee_minor' => 100000,
            'recurring_fee_minor' => 50000,
            'billing_cycle' => 'monthly',
            'addons_total_minor' => 0,
            'discount_total_minor' => 0,
            'tax_total_minor' => 0,
            'grand_total_minor' => 150000,
            'quote_status' => 'submitted',
            'valid_until' => now()->addDays(3),
            'approved_by' => null,
            'approved_at' => null,
            'metadata_json' => json_encode(['source' => 'runtime_proof_seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function primeGuardDependencies(
        Appointment $appointment,
        string $status,
        string $actionKey
    ): array {
        $actionKey = strtolower(trim($actionKey));
        $status = strtolower(trim($status));
        $payload = [];

        if (in_array($actionKey, [
            'request_quote_revision',
            'reject',
            'reject_admission',
            'request_changes',
            'cancel_admission',
            'pause_care_non_critical',
            'close_episode_nonpayment',
            'mark_escalation_unresolved',
        ], true)) {
            $payload['status_reason_note'] = 'Runtime proof note.';
            $payload['status_reason'] = 'Runtime proof note.';
        }

        if (in_array($actionKey, ['pay_now', 'pay_admission'], true)) {
            $payload['payment_id'] = $appointment->payment_id;
            $payload['status_reason'] = 'payment_completed';
        }

        if (str_starts_with($status, 'escalation_') || in_array($actionKey, [
            'transfer_escalation',
            'resolve_escalation',
            'mark_escalation_unresolved',
        ], true)) {
            DB::table('escalations')->updateOrInsert(
                ['appointment_id' => $appointment->id],
                [
                    'episode_id' => null,
                    'severity' => 'high',
                    'pathway' => 'intervention',
                    'status' => $status === 'escalation_in_progress' ? 'escalation_open' : $status,
                    'opened_by_role' => 'hospital',
                    'opened_by_id' => 1,
                    'opened_at' => now(),
                    'resolved_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForAction(
        string $actionKey,
        Appointment $appointment,
        int $alternateCarerId
    ): array {
        $actionKey = strtolower(trim($actionKey));

        return match ($actionKey) {
            'reassign' => ['carer_id' => $alternateCarerId],
            'approve_quote' => [
                'enrollment_fee_minor' => 900000,
                'recurring_fee_minor' => 500000,
                'billing_cycle' => 'monthly',
                'addons_total_minor' => 40000,
                'tax_total_minor' => 20000,
                'discount_total_minor' => 20000,
                'status_reason' => 'runtime_quote_approval',
            ],
            'submit_admission_plan' => [
                'billing_cycle' => 'monthly',
                'enrollment_fee_minor' => 900000,
                'recurring_fee_minor' => 500000,
                'addons_total_minor' => 40000,
                'tax_total_minor' => 20000,
                'discount_total_minor' => 20000,
                'grand_total_minor' => 1440000,
                'quote_valid_until' => now()->addDays(5)->toIso8601String(),
            ],
            'request_quote_revision' => [
                'status_reason_note' => 'Runtime proof quote revision requested.',
                'status_reason' => 'Runtime proof quote revision requested.',
            ],
            'open_escalation', 'escalate' => [
                'severity' => 'high',
                'pathway' => 'intervention',
                'status_reason' => 'runtime_escalation_opened',
            ],
            'transfer_escalation' => [
                'severity' => 'high',
                'pathway' => 'transfer',
                'status_reason' => 'runtime_escalation_transfer',
            ],
            'resolve_escalation' => [
                'status_reason' => 'runtime_escalation_resolved',
            ],
            'pay_now', 'pay_admission' => [
                'payment_id' => $appointment->payment_id,
                'status_reason' => 'payment_completed',
            ],
            default => [],
        };
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function writeCsvReport(array $rows): void
    {
        $path = base_path('../execution_plans/HOME_VISIT_RUNTIME_PROOF_REPORT.csv');
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
            '# Home Visit Runtime Proof Summary',
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
            base_path('../execution_plans/HOME_VISIT_RUNTIME_PROOF_SUMMARY.md'),
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

