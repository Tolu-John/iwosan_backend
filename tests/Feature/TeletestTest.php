<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Teletest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TeletestTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_create_teletest_pending_payment(): void
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
            'review_id' => null,
            'address' => 'Test address',
            'test_name' => 'Malaria',
            'status' => 'pending_payment',
            'date_time' => '2026-02-10 10:00:00',
            'admin_approved' => 0,
        ];

        $this->postJson('/api/v1/teletest', $payload)->assertStatus(200);
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

        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id + 1,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'review_id' => 1,
            'address' => 'Test address',
            'test_name' => 'Malaria',
            'status' => 'scheduled',
            'date_time' => '2026-02-10 10:00:00',
            'admin_approved' => 1,
        ];

        $this->putJson("/api/v1/teletest/{$teletest->id}", $payload)->assertStatus(403);
    }

    public function test_patient_cannot_schedule_without_paid_payment(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => 'pending_payment',
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'review_id' => 1,
            'address' => 'Test address',
            'test_name' => 'Malaria',
            'status' => 'scheduled',
            'date_time' => '2026-02-10 10:00:00',
            'admin_approved' => 1,
        ];

        $this->putJson("/api/v1/teletest/{$teletest->id}", $payload)->assertStatus(422);
    }
}
