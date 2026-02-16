<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_view_prescriptions(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $carer = Carer::factory()->create();

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
        ]);

        Drug::factory()->create([
            'consultation_id' => $consultation->id,
            'status' => 'active',
        ]);
        LabTest::factory()->create([
            'consultation_id' => $consultation->id,
            'status' => 'ordered',
        ]);

        Passport::actingAs($user);

        $this->getJson("/api/v1/patient/prescriptions/{$patient->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['consultation_id' => $consultation->id]);
    }

    public function test_patient_cannot_view_other_prescriptions(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $otherPatient = Patient::factory()->create();

        Passport::actingAs($user);

        $this->getJson("/api/v1/patient/prescriptions/{$otherPatient->id}")
            ->assertStatus(403);
    }
}
