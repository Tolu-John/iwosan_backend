<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthValidationTest extends TestCase
{
    public function test_patient_register_requires_fields(): void
    {
        $response = $this->postJson('/api/patient/register', []);
        $response->assertStatus(422);
    }

    public function test_patient_login_requires_fields(): void
    {
        $response = $this->postJson('/api/patient/login', []);
        $response->assertStatus(422);
    }

    public function test_carer_register_requires_fields(): void
    {
        $response = $this->postJson('/api/carer/register', []);
        $response->assertStatus(422);
    }

    public function test_hospital_register_requires_fields(): void
    {
        $response = $this->postJson('/api/hospital/register', []);
        $response->assertStatus(422);
    }
}
