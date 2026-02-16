<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_update_own_profile(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);

        Passport::actingAs($user);

        $payload = $this->patientPayload($user, $patient);

        $response = $this->putJson("/api/v1/patient/{$patient->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_patient_cannot_update_another_profile(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $otherUser = User::factory()->create();
        $otherPatient = Patient::factory()->create(['user_id' => $otherUser->id]);

        Passport::actingAs($user);

        $payload = $this->patientPayload($otherUser, $otherPatient);

        $response = $this->putJson("/api/v1/patient/{$otherPatient->id}", $payload);

        $response->assertStatus(403);
    }

    public function test_carer_cannot_change_hospital_on_self_update(): void
    {
        $hospital = Hospital::factory()->create();
        $otherHospital = Hospital::factory()->create();
        $user = User::factory()->create();
        $carer = Carer::factory()->create([
            'user_id' => $user->id,
            'hospital_id' => $hospital->id,
        ]);

        Passport::actingAs($user);

        $payload = $this->carerPayload($user, $carer, $otherHospital->id);

        $response = $this->putJson("/api/v1/carer/{$carer->id}", $payload);

        $response->assertStatus(403);
    }

    public function test_hospital_can_update_own_profile(): void
    {
        $hospital = Hospital::factory()->create();
        $user = User::factory()->create(['firedb_id' => $hospital->firedb_id]);

        Passport::actingAs($user);

        $payload = [
            'name' => $hospital->name,
            'about_us' => 'Updated about',
            'website' => 'https://example.test',
            'email' => $hospital->email,
            'code' => $hospital->code,
            'account_number' => '1234567890',
            'account_name' => 'Iwosan Hospital',
            'bank_name' => 'Test Bank',
            'bank_code' => '001',
            'address' => 'Updated address',
            'phone' => '08000000000',
            'lat' => '0.0000',
            'lon' => '0.0000',
        ];

        $response = $this->putJson("/api/v1/hospital/{$hospital->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('hospitals', [
            'id' => $hospital->id,
            'address' => 'Updated address',
        ]);
    }

    private function patientPayload(User $user, Patient $patient): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'firstname' => 'Patient',
                'lastname' => 'User',
                'firedb_id' => (string) $user->firedb_id,
                'email' => $user->email,
                'phone' => '08000000000',
                'gender' => 'female',
                'dob' => '1990-01-01',
                'address' => 'Test address',
                'lat' => '0.0000',
                'lon' => '0.0000',
            ],
            'user_id' => $user->id,
            'weight' => 70,
            'bloodtype' => 'O+',
            'genotype' => 'AA',
            'sugar_level' => 5,
            'bp_dia' => 70,
            'bp_sys' => 120,
            'height' => 170,
            'temperature' => 36.5,
            'kin_name' => 'Kin Name',
            'kin_phone' => '08000000001',
            'kin_address' => 'Kin address',
            'other_kin_name' => 'Other Kin',
            'other_kin_phone' => '08000000002',
            'other_kin_address' => 'Other address',
        ];
    }

    private function carerPayload(User $user, Carer $carer, int $hospitalId): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'firstname' => 'Carer',
                'lastname' => 'User',
                'firedb_id' => (string) $user->firedb_id,
                'email' => $user->email,
                'phone' => '08000000000',
                'gender' => 'male',
                'address' => 'Test address',
                'lat' => '0.0000',
                'lon' => '0.0000',
            ],
            'hospital_id' => $hospitalId,
            'user_id' => $user->id,
            'bio' => 'Bio',
            'position' => 'Nurse',
            'onHome_leave' => 0,
            'onVirtual_leave' => 0,
            'qualifications' => 'RN',
            'virtual_day_time' => 'Weekdays',
            'home_day_time' => 'Weekdays',
            'super_admin_approved' => $carer->super_admin_approved ?? 0,
            'admin_approved' => $carer->admin_approved ?? 0,
        ];
    }
}
