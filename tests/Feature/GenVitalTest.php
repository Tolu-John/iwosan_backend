<?php

namespace Tests\Feature;

use App\Models\Gen_Vital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GenVitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_store_vital(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'type' => 'temperature',
            'value' => 37.1,
            'unit' => 'C',
            'taken_at' => now()->subMinutes(2)->toISOString(),
            'context' => 'resting',
            'source' => 'patient_manual',
        ];

        $this->postJson('/api/v1/patient/storevital', $payload)->assertStatus(200);
    }

    public function test_patient_cannot_store_invalid_vital(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'type' => 'temperature',
            'value' => 100,
            'unit' => 'C',
            'taken_at' => now()->subMinutes(2)->toISOString(),
            'context' => 'resting',
            'source' => 'patient_manual',
        ];

        $this->postJson('/api/v1/patient/storevital', $payload)->assertStatus(422);
    }
}
