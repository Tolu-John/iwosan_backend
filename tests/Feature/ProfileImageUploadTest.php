<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProfileImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_upload_own_image(): void
    {
        Storage::fake('iwosan_files');
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/patient/uploadimage', [
            'user_id' => $user->id,
            'file' => UploadedFile::fake()->image('patient.jpg'),
        ]);

        $response->assertStatus(200);
        $this->assertNotNull(User::find($user->id)->user_img);
    }

    public function test_carer_can_upload_own_image(): void
    {
        Storage::fake('iwosan_files');
        $hospital = Hospital::factory()->create();
        $user = User::factory()->create();
        $carer = Carer::factory()->create(['user_id' => $user->id, 'hospital_id' => $hospital->id]);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/carer/uploadimage', [
            'user_id' => $user->id,
            'file' => UploadedFile::fake()->image('carer.jpg'),
        ]);

        $response->assertStatus(200);
        $this->assertNotNull(User::find($user->id)->user_img);
    }

    public function test_hospital_can_upload_own_image(): void
    {
        Storage::fake('iwosan_files');
        $hospital = Hospital::factory()->create();
        $user = User::factory()->create(['firedb_id' => $hospital->firedb_id]);

        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/hospital/uploadimage', [
            'id' => $hospital->id,
            'file' => UploadedFile::fake()->image('hospital.png'),
        ]);

        $response->assertStatus(200);
        $this->assertNotNull(Hospital::find($hospital->id)->hospital_img);
    }
}
