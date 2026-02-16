<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProtectedRoutesTest extends TestCase
{
    public function test_patient_dashboard_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/patient/lite/1');
        $response->assertStatus(401);
    }

    public function test_appointments_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/appointment', []);
        $response->assertStatus(401);
    }

    public function test_payments_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/payment', []);
        $response->assertStatus(401);
    }

    public function test_ward_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/ward/vitals/1');
        $response->assertStatus(401);
    }

    public function test_ward_dashboard_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/ward/dashboard/1');
        $response->assertStatus(401);
    }
}
