<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Teletest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class LabResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_upload_lab_result_for_own_teletest(): void
    {
        Storage::fake('iwosan_files');

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'teletest_id' => $teletest->id,
            'name' => 'Malaria Test',
            'lab_name' => 'Main Lab',
            'extra_notes' => 'Notes',
            'files' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.jpg'),
            ],
        ];

        $this->postJson('/api/v1/labresult', $payload)->assertStatus(200);
    }

    public function test_patient_cannot_upload_for_other_patient_teletest(): void
    {
        Storage::fake('iwosan_files');

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);

        $otherUser = User::factory()->create();
        $otherPatient = Patient::factory()->create(['user_id' => $otherUser->id]);

        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $teletest = Teletest::factory()->create([
            'patient_id' => $otherPatient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'teletest_id' => $teletest->id,
            'name' => 'Malaria Test',
            'lab_name' => 'Main Lab',
            'extra_notes' => 'Notes',
            'file' => UploadedFile::fake()->image('result.jpg'),
        ];

        $this->postJson('/api/v1/labresult', $payload)->assertStatus(422);
    }

    public function test_soft_delete_and_restore_lab_result(): void
    {
        Storage::fake('iwosan_files');

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
        ]);

        $labResult = LabResult::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'teletest_id' => $teletest->id,
        ]);

        Passport::actingAs($user);

        $this->deleteJson("/api/v1/labresult/{$labResult->id}")->assertStatus(200);
        $this->assertSoftDeleted('lab_results', ['id' => $labResult->id]);

        $this->postJson("/api/v1/labresult/{$labResult->id}/restore")->assertStatus(200);
        $this->assertDatabaseHas('lab_results', ['id' => $labResult->id, 'deleted_at' => null]);
    }
}
