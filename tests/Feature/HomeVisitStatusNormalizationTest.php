<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HomeVisitStatusNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_create_normalizes_requested_to_awaiting_hospital_approval(): void
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        Passport::actingAs($patientUser);

        $response = $this->postJson('/api/v1/appointment', [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'requested',
            'address' => '12 Main Street',
            'price' => 15000,
            'consult_type' => 'Home Visit',
            'appointment_type' => 'Home Visit',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'extra_notes' => 'Status normalization test',
            'admin_approved' => 0,
            'consent_accepted' => true,
            'channel' => 'home_visit',
            'dispatch_model' => 'system_ops_assignment',
            'address_source' => 'manual_entry',
            'contact_profile' => 'onboarding_defaults',
            'visit_reason' => 'Fever and body pain',
            'preferred_window' => 'Morning window',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'awaiting_hospital_approval')
            ->assertJsonPath('status_description', 'Request Submitted');

        $appointmentId = (int) $response->json('id');
        $this->assertGreaterThan(0, $appointmentId);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'awaiting_hospital_approval',
        ]);
    }

    public function test_home_show_normalizes_legacy_requested_status_without_mutating_database(): void
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'Home Visit',
            'status' => 'requested',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($patientUser);

        $response = $this->getJson("/api/v1/appointment/{$appointment->id}");
        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'awaiting_hospital_approval')
            ->assertJsonPath('status_description', 'Request Submitted');

        $allowedActions = (array) $response->json('allowed_actions');
        $this->assertContains('request_changes', $allowedActions);
        $this->assertContains('cancel_admission', $allowedActions);

        $appointment->refresh();
        $this->assertSame('requested', $appointment->status);
    }
}
