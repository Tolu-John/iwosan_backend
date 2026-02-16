<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_create_own_appointment(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        Passport::actingAs($user);

        $payload = $this->storePayload($patient->id, $carer->id);

        $this->postJson('/api/v1/appointment', $payload)->assertStatus(200);
    }

    public function test_patient_cannot_create_for_other_patient(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $otherUser = User::factory()->create();
        $otherPatient = Patient::factory()->create(['user_id' => $otherUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        Passport::actingAs($user);

        $payload = $this->storePayload($otherPatient->id, $carer->id);

        $this->postJson('/api/v1/appointment', $payload)->assertStatus(403);
    }

    public function test_hospital_can_create_for_own_carer_only(): void
    {
        $hospital = Hospital::factory()->create();
        $user = User::factory()->create(['firedb_id' => $hospital->firedb_id]);
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $ownCarer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $otherCarer = Carer::factory()->create();

        Passport::actingAs($user);

        $okPayload = $this->storePayload($patient->id, $ownCarer->id);
        $this->postJson('/api/v1/appointment', $okPayload)->assertStatus(200);

        $badPayload = $this->storePayload($patient->id, $otherCarer->id);
        $this->postJson('/api/v1/appointment', $badPayload)->assertStatus(403);
    }

    public function test_patient_cannot_change_ids_on_update(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'paid',
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
        ]);

        Passport::actingAs($user);

        $payload = $this->updatePayload($patient->id, $carer->id);
        $payload['patient_id'] = $patient->id + 999;
        $payload['payment_id'] = $payment->id;

        $this->putJson("/api/v1/appointment/{$appointment->id}", $payload)->assertStatus(403);
    }

    public function test_patient_cannot_schedule_without_paid_payment(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'pending_payment',
        ]);

        Passport::actingAs($user);

        $payload = $this->updatePayload($patient->id, $carer->id);
        $payload['status'] = 'scheduled';
        $payload['payment_id'] = null;

        $this->putJson("/api/v1/appointment/{$appointment->id}", $payload)->assertStatus(422);
    }

    public function test_appointment_show_includes_status_metadata_fields(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'pending_payment',
        ]);

        Passport::actingAs($user);

        $this->getJson("/api/v1/appointment/{$appointment->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'status',
                'status_description',
                'scheduled_at',
                'started_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);
    }

    private function storePayload(int $patientId, int $carerId): array
    {
        return [
            'patient_id' => $patientId,
            'carer_id' => $carerId,
            'status' => 'pending_payment',
            'address' => 'Test address',
            'price' => 100,
            'consult_type' => 'home',
            'appointment_type' => 'home',
            'date_time' => '2026-02-10 10:00:00',
            'extra_notes' => 'Notes',
            'admin_approved' => 0,
            'payment_id' => null,
            'consult_id' => null,
            'channel' => 'mobile',
        ];
    }

    private function updatePayload(int $patientId, int $carerId): array
    {
        return [
            'patient_id' => $patientId,
            'carer_id' => $carerId,
            'status' => 'scheduled',
            'address' => 'Updated address',
            'price' => 120,
            'consult_type' => 'home',
            'appointment_type' => 'home',
            'extra_notes' => 'Updated notes',
            'date_time' => '2026-02-11 11:00:00',
            'ward_id' => 1,
            'admin_approved' => 1,
            'payment_id' => null,
            'consult_id' => null,
            'channel' => 'mobile',
        ];
    }
}
