<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Teletest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AppointmentResourceWorkflowConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_teletest_like_appointment_uses_teletest_workflow_actions_and_encounter_type(): void
    {
        [$patientUser, $patient, $carer, $hospital] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'Lab Test at Home',
            'consult_type' => 'lab test',
            'status' => 'awaiting_payment',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => 'awaiting_payment',
            'date_time' => $appointment->date_time,
        ]);

        Passport::actingAs($patientUser);

        $response = $this->getJson("/api/v1/appointment/{$appointment->id}");
        $response->assertStatus(200);

        $payload = $response->json();
        $this->assertSame('teletest', $payload['encounter_type'] ?? null);
        $this->assertSame($teletest->id, (int) ($payload['teletest_id'] ?? 0));
        $this->assertContains('pay_now', (array) ($payload['allowed_actions'] ?? []));
    }

    public function test_virtual_appointment_exposes_join_window_policy_fields(): void
    {
        [$patientUser, $patient, $carer] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'consult_type' => 'virtual',
            'status' => 'scheduled',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($patientUser);

        $response = $this->getJson("/api/v1/appointment/{$appointment->id}");
        $response->assertStatus(200);

        $payload = $response->json();
        $this->assertSame('virtual_visit', $payload['encounter_type'] ?? null);
        $this->assertSame(
            (int) (config('virtual_visit_workflow.sla_windows.waiting_room_open.join_window_minutes') ?? 15),
            (int) ($payload['join_window_minutes'] ?? 0)
        );
        $this->assertSame(
            (int) (config('virtual_visit_workflow.sla_windows.waiting_room_open.late_join_allowance_minutes') ?? 120),
            (int) ($payload['late_join_allowance_minutes'] ?? 0)
        );
    }

    /**
     * @return array{User, Patient, Carer, Hospital}
     */
    private function seedActors(): array
    {
        $hospitalUser = User::factory()->create();
        $hospital = Hospital::factory()->create([
            'user_id' => $hospitalUser->id,
        ]);

        $carerUser = User::factory()->create();
        $carer = Carer::factory()->create([
            'user_id' => $carerUser->id,
            'hospital_id' => $hospital->id,
        ]);

        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create([
            'user_id' => $patientUser->id,
        ]);

        return [$patientUser, $patient, $carer, $hospital];
    }
}
