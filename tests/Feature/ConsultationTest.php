<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_create_virtual_consultation(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'pending_payment',
            'treatment_type' => 'Virtual visit',
            'diagnosis' => 'Diagnosis',
            'consult_notes' => 'Notes',
            'date_time' => '2026-02-10 10:00:00',
            'vConsultation' => [
                'consult_type' => 'video',
                'duration' => '30',
            ],
        ];

        $this->postJson('/api/v1/consultation', $payload)->assertStatus(200);
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

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'treatment_type' => 'Virtual visit',
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id + 1,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'review_id' => 1,
            'status' => 'scheduled',
            'treatment_type' => 'Virtual visit',
            'diagnosis' => 'Diagnosis',
            'consult_notes' => 'Notes',
            'date_time' => '2026-02-10 10:00:00',
            'vConsultation' => [
                'consult_type' => 'video',
                'duration' => '30',
            ],
        ];

        $this->putJson("/api/v1/consultation/{$consultation->id}", $payload)->assertStatus(403);
    }

    public function test_cannot_change_treatment_type_on_update(): void
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

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'treatment_type' => 'Virtual visit',
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'review_id' => 1,
            'status' => 'scheduled',
            'treatment_type' => 'Home visit',
            'diagnosis' => 'Diagnosis',
            'consult_notes' => 'Notes',
            'date_time' => '2026-02-10 10:00:00',
            'hConsultation' => [
                'address' => 'Address',
                'ward_id' => 1,
                'admitted' => 0,
            ],
        ];

        $this->putJson("/api/v1/consultation/{$consultation->id}", $payload)->assertStatus(422);
    }

    public function test_patient_cannot_schedule_without_paid_payment(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'pending_payment',
            'treatment_type' => 'Virtual visit',
        ]);
        $review = \App\Models\Review::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'review_id' => $review->id,
            'status' => 'scheduled',
            'treatment_type' => 'Virtual visit',
            'diagnosis' => 'Diagnosis',
            'consult_notes' => 'Notes',
            'date_time' => '2026-02-10 10:00:00',
            'vConsultation' => [
                'consult_type' => 'video',
                'duration' => '30',
            ],
        ];

        $this->putJson("/api/v1/consultation/{$consultation->id}", $payload)->assertStatus(422);
    }

    public function test_patient_can_create_virtual_consultation_draft(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'draft',
            'treatment_type' => 'Virtual visit',
            'diagnosis' => 'Draft diagnosis',
            'consult_notes' => 'Draft notes',
            'date_time' => '2026-02-10 10:00:00',
            'vConsultation' => [
                'consult_type' => 'video',
                'duration' => 25,
            ],
        ];

        $this->postJson('/api/v1/consultation', $payload)
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'draft']);
    }

    public function test_patient_can_create_home_visit_consultation_without_ward_id_when_not_admitted(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'draft',
            'treatment_type' => 'Home visit',
            'diagnosis' => 'Home consult draft',
            'consult_notes' => 'Home consult notes',
            'date_time' => '2026-02-10 10:00:00',
            'hConsultation' => [
                'address' => '12 Home Street',
                'admitted' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/consultation', $payload)
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'draft']);

        $consultationId = (int) $response->json('id');
        $this->assertGreaterThan(0, $consultationId);
        $this->assertDatabaseMissing('h_consultations', [
            'consultation_id' => $consultationId,
        ]);
    }

    public function test_home_visit_admitted_requires_ward_id(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'draft',
            'treatment_type' => 'Home visit Admitted',
            'diagnosis' => 'Admitted consult draft',
            'consult_notes' => 'Admitted consult notes',
            'date_time' => '2026-02-10 10:00:00',
            'hConsultation' => [
                'address' => '12 Home Street',
                'admitted' => 1,
            ],
        ];

        $this->postJson('/api/v1/consultation', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hConsultation.ward_id']);
    }

    public function test_virtual_consultation_rejects_hconsultation_payload(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'draft',
            'treatment_type' => 'Virtual visit',
            'diagnosis' => 'Diagnosis',
            'consult_notes' => 'Notes',
            'date_time' => '2026-02-10 10:00:00',
            'vConsultation' => [
                'consult_type' => 'video',
                'duration' => 20,
            ],
            'hConsultation' => [
                'address' => 'Invalid for virtual',
                'ward_id' => 1,
                'admitted' => 0,
            ],
        ];

        $this->postJson('/api/v1/consultation', $payload)
            ->assertStatus(422);
    }

    public function test_cannot_transition_completed_consultation_back_to_draft(): void
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

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'status' => 'completed',
            'treatment_type' => 'Virtual visit',
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'status' => 'draft',
            'treatment_type' => 'Virtual visit',
            'diagnosis' => 'Diagnosis',
            'consult_notes' => 'Notes',
            'date_time' => '2026-02-10 10:00:00',
            'vConsultation' => [
                'consult_type' => 'video',
                'duration' => 20,
            ],
        ];

        $this->putJson("/api/v1/consultation/{$consultation->id}", $payload)
            ->assertStatus(422);
    }

    public function test_carer_can_create_consultation_draft_for_own_assignment(): void
    {
        $carerUser = User::factory()->create();
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create([
            'user_id' => $carerUser->id,
            'hospital_id' => $hospital->id,
        ]);

        Passport::actingAs($carerUser);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'draft',
            'treatment_type' => 'Virtual visit',
            'diagnosis' => 'Draft by carer',
            'consult_notes' => 'Draft notes by carer',
            'date_time' => '2026-02-10 10:00:00',
            'vConsultation' => [
                'consult_type' => 'video',
                'duration' => 20,
            ],
        ];

        $this->postJson('/api/v1/consultation', $payload)
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'draft']);
    }
}
