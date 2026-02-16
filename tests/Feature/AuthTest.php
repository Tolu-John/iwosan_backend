<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_register_and_login(): void
    {
        $registerPayload = [
            'firedb_id' => 'pat-001',
            'firstname' => 'Test',
            'lastname' => 'Patient',
            'email' => 'patient@example.test',
            'phone' => '08000000000',
            'password' => 'password123',
        ];

        $this->postJson('/api/v1/patient/register', $registerPayload)
            ->assertStatus(200)
            ->assertJsonStructure(['id', 'access_token', 'expires_at']);

        $this->postJson('/api/v1/patient/login', [
            'email' => 'patient@example.test',
            'password' => 'password123',
        ])->assertStatus(200);
    }

    public function test_carer_can_register_with_hospital_code_and_login(): void
    {
        $hospital = Hospital::factory()->create(['code' => 'HOSP123']);

        $registerPayload = [
            'firstname' => 'Test',
            'lastname' => 'Carer',
            'phone' => '08000000001',
            'firedb_id' => 'carer-001',
            'email' => 'carer@example.test',
            'password' => 'password123',
            'code' => $hospital->code,
        ];

        $this->postJson('/api/v1/carer/register', $registerPayload)
            ->assertStatus(200)
            ->assertJsonStructure(['id', 'access_token', 'expires_at']);

        $this->postJson('/api/v1/carer/login', [
            'email' => 'carer@example.test',
            'password' => 'password123',
            'code' => $hospital->code,
        ])->assertStatus(200);
    }

    public function test_hospital_can_register_and_login(): void
    {
        $registerPayload = [
            'name' => 'Test Hospital',
            'email' => 'hospital@example.test',
            'password' => 'password123',
            'firedb_id' => 'hosp-001',
            'phone' => '08000000002',
            'code' => 'HOSP001',
        ];

        $this->postJson('/api/v1/hospital/register', $registerPayload)
            ->assertStatus(200)
            ->assertJsonStructure(['id', 'access_token', 'expires_at']);

        $this->postJson('/api/v1/hospital/login', [
            'email' => 'hospital@example.test',
            'password' => 'password123',
            'code' => 'HOSP001',
        ])->assertStatus(200);
    }
}
