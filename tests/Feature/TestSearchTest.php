<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TestSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_search_tests(): void
    {
        $patientUser = User::factory()->create();
        \App\Models\Patient::factory()->create(['user_id' => $patientUser->id]);
        Passport::actingAs($patientUser);

        $hospital = Hospital::factory()->create(['super_admin_approved' => 1, 'rating' => 4.4]);
        test::factory()->create([
            'hospital_id' => $hospital->id,
            'name' => 'Full Blood Count',
            'price' => 3500,
        ]);

        $response = $this->getJson('/api/v1/patient/search_test?q=blood&price_min=1000&price_max=5000');
        $response->assertStatus(200)->assertJsonStructure([
            'total',
            'page',
            'per_page',
            'results',
        ]);
    }
}
