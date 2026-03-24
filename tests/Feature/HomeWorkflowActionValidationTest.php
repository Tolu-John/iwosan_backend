<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HomeWorkflowActionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_quote_requires_quote_fields(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);
        $patient = Patient::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'home_admission_quote_pending_hospital',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        DB::table('home_admission_quotes')->insert([
            'appointment_id' => $appointment->id,
            'version' => 1,
            'currency' => 'NGN',
            'enrollment_fee_minor' => 100000,
            'recurring_fee_minor' => 50000,
            'billing_cycle' => 'monthly',
            'addons_total_minor' => 0,
            'discount_total_minor' => 0,
            'tax_total_minor' => 0,
            'grand_total_minor' => 150000,
            'quote_status' => 'submitted',
            'valid_until' => now()->addDay(),
            'approved_by' => null,
            'approved_at' => null,
            'metadata_json' => json_encode(['source' => 'test_seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Passport::actingAs($hospitalUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/approve_quote", [
            'status_reason' => 'incomplete_payload',
        ])
            ->assertStatus(422);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'home_admission_quote_pending_hospital',
        ]);
    }

    public function test_reject_admission_requires_status_reason_note(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);
        $patient = Patient::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'home_admission_quote_pending_hospital',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/reject_admission", [
            'status_reason' => 'missing_note',
        ])
            ->assertStatus(422);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'home_admission_quote_pending_hospital',
        ]);
    }

    public function test_request_quote_revision_requires_note_and_preserves_existing_quote_values(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);
        $patient = Patient::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'home_admission_quote_pending_hospital',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        DB::table('home_admission_quotes')->insert([
            'appointment_id' => $appointment->id,
            'version' => 1,
            'currency' => 'NGN',
            'enrollment_fee_minor' => 900000,
            'recurring_fee_minor' => 500000,
            'billing_cycle' => 'monthly',
            'addons_total_minor' => 40000,
            'discount_total_minor' => 20000,
            'tax_total_minor' => 20000,
            'grand_total_minor' => 1440000,
            'quote_status' => 'submitted',
            'valid_until' => now()->addDay(),
            'approved_by' => null,
            'approved_at' => null,
            'metadata_json' => json_encode(['source' => 'test_seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Passport::actingAs($hospitalUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/request_quote_revision", [
            'status_reason_note' => 'Need clearer vitals and history details.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'admission_revision_requested')
            ->assertJsonPath('status_reason_note', 'Need clearer vitals and history details.')
            ->assertJsonPath('status_reason', 'Need clearer vitals and history details.');

        $this->assertDatabaseHas('home_admission_quotes', [
            'appointment_id' => $appointment->id,
            'version' => 2,
            'enrollment_fee_minor' => 900000,
            'recurring_fee_minor' => 500000,
            'billing_cycle' => 'monthly',
            'addons_total_minor' => 40000,
            'discount_total_minor' => 20000,
            'tax_total_minor' => 20000,
            'grand_total_minor' => 1440000,
        ]);
    }

    public function test_request_changes_from_awaiting_hospital_approval_stays_in_review_queue(): void
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'awaiting_hospital_approval',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($patientUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/request_changes", [
            'status_reason_note' => 'Updated reason and visit window',
            'status_reason' => 'Updated reason and visit window',
        ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'awaiting_hospital_approval')
            ->assertJsonPath('status_reason_note', 'Updated reason and visit window');
    }

    public function test_pause_care_non_critical_requires_status_reason_note(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);
        $patient = Patient::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'payment_overdue',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/pause_care_non_critical", [
            'status_reason' => 'missing_note',
        ])->assertStatus(422);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'payment_overdue',
        ]);
    }

    public function test_close_episode_nonpayment_requires_status_reason_note(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);
        $patient = Patient::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'payment_overdue',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/close_episode_nonpayment", [
            'status_reason' => 'missing_note',
        ])->assertStatus(422);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'payment_overdue',
        ]);
    }
}
