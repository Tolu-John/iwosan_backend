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
}
