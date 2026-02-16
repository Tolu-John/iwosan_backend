<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CarerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_search_carers_with_required_filters(): void
    {
        $patientUser = User::factory()->create();
        \App\Models\Patient::factory()->create(['user_id' => $patientUser->id]);
        Passport::actingAs($patientUser);

        $hospital = Hospital::factory()->create([
            'home_visit_price' => 5000,
            'virtual_visit_price' => 3000,
        ]);

        $carerUser = User::factory()->create(['firstname' => 'Ada', 'lastname' => 'Smith']);
        Carer::factory()->create([
            'user_id' => $carerUser->id,
            'hospital_id' => $hospital->id,
            'admin_approved' => 1,
            'super_admin_approved' => 1,
            'onHome_leave' => 0,
            'onVirtual_leave' => 0,
            'position' => 'Nurse',
        ]);

        $response = $this->getJson('/api/v1/patient/search_carers?visit_type=home&availability=anytime&q=Ada');
        $response->assertStatus(200)->assertJsonStructure([
            'total',
            'page',
            'per_page',
            'results',
        ]);
    }
}
