<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HomeWorkflowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_confirm_check_in_from_arrived(): void
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'arrived',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'type' => 'home_visit',
            'status' => 'paid',
        ]);
        $appointment->payment_id = $payment->id;
        $appointment->save();

        Passport::actingAs($patientUser);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/confirm_check_in", [
            'status_reason' => 'patient_check_in_confirmed',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'in_progress');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'arrived',
            'to_status' => 'in_progress',
            'action_key' => 'confirm_check_in',
            'actor_role' => 'patient',
        ]);
    }

    public function test_hospital_cannot_start_travel_for_scheduled_visit(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);
        $patient = Patient::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'scheduled',
            'date_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/start_travel", [])
            ->assertStatus(422);

        $this->assertDatabaseMissing('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'action_key' => 'start_travel',
            'actor_role' => 'hospital',
        ]);
    }

    public function test_hospital_can_approve_quote_and_quote_is_persisted(): void
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
            'valid_until' => Carbon::now()->addDay(),
            'approved_by' => null,
            'approved_at' => null,
            'metadata_json' => json_encode(['source' => 'test_seed']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        Passport::actingAs($hospitalUser);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/approve_quote", [
            'enrollment_fee_minor' => 2500000,
            'recurring_fee_minor' => 1200000,
            'billing_cycle' => 'monthly',
            'addons_total_minor' => 150000,
            'tax_total_minor' => 50000,
            'discount_total_minor' => 10000,
            'reason_note' => 'approved for pilot patient',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'home_admitted_pending_payment')
            ->assertJsonPath('quote_summary.status', 'approved')
            ->assertJsonPath('quote_summary.billing_cycle', 'monthly');

        $this->assertDatabaseHas('home_admission_quotes', [
            'appointment_id' => $appointment->id,
        ]);

        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'home_admission_quote_pending_hospital',
            'to_status' => 'home_admitted_pending_payment',
            'action_key' => 'approve_quote',
            'actor_role' => 'hospital',
        ]);
    }

    public function test_patient_can_activate_home_admission_after_successful_payment(): void
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'home_admitted_pending_payment',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_id' => null,
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
            'quote_status' => 'approved',
            'valid_until' => Carbon::now()->addDays(2),
            'approved_by' => $hospital->id,
            'approved_at' => Carbon::now(),
            'metadata_json' => json_encode(['source' => 'test_seed']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'type' => 'home_visit',
            'status' => 'paid',
        ]);

        Passport::actingAs($patientUser);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/pay_admission", [
            'payment_id' => $payment->id,
            'status_reason' => 'payment_completed',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'home_admitted_active')
            ->assertJsonPath('payment_id', $payment->id)
            ->assertJsonPath('billing_summary.billing_status', 'due')
            ->assertJsonPath('quote_summary.status', 'approved');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'home_admitted_active',
            'payment_id' => $payment->id,
        ]);

        $this->assertDatabaseHas('home_care_episodes', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'hospital_id' => $hospital->id,
            'episode_status' => 'home_admitted_active',
        ]);

        $episode = DB::table('home_care_episodes')
            ->where('appointment_id', $appointment->id)
            ->first();
        $this->assertNotNull($episode);
        $this->assertDatabaseHas('episode_billing_cycles', [
            'episode_id' => $episode->id,
            'billing_status' => 'due',
        ]);
    }

    public function test_patient_cannot_activate_home_admission_with_unpaid_payment(): void
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'home_admitted_pending_payment',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'type' => 'home_visit',
            'status' => 'pending',
        ]);

        Passport::actingAs($patientUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/pay_admission", [
            'payment_id' => $payment->id,
            'status_reason' => 'payment_attempt',
        ])
            ->assertStatus(422);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'home_admitted_pending_payment',
        ]);
        $this->assertDatabaseMissing('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'home_admitted_pending_payment',
            'to_status' => 'home_admitted_active',
            'action_key' => 'pay_admission',
            'actor_role' => 'patient',
        ]);
    }

    public function test_action_endpoint_rejects_virtual_visit_appointment(): void
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'scheduled',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($patientUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/confirm_check_in", [
            'status_reason' => 'should_not_apply_to_virtual',
        ])
            ->assertStatus(422);
    }

    public function test_action_endpoint_respects_feature_flag(): void
    {
        Config::set('home_visit_workflow.enabled', false);

        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'arrived',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($patientUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/actions/confirm_check_in", [
            'status_reason' => 'feature_flag_disabled',
        ])
            ->assertStatus(409);
    }

    public function test_open_escalation_returns_escalation_summary_payload(): void
    {
        $carerUser = User::factory()->create();
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create([
            'user_id' => $carerUser->id,
            'hospital_id' => $hospital->id,
        ]);
        $patient = Patient::factory()->create();
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'type' => 'home_visit',
            'status' => 'paid',
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'in_progress',
            'payment_id' => $payment->id,
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($carerUser);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/escalate", [
            'severity' => 'high',
            'pathway' => 'intervention',
            'status_reason' => 'patient_condition_worsened',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'escalation_open')
            ->assertJsonPath('escalation_summary.status', 'escalation_open')
            ->assertJsonPath('escalation_summary.severity', 'high')
            ->assertJsonPath('escalation_summary.pathway', 'intervention');
    }

    public function test_hospital_can_pause_care_for_payment_overdue_episode(): void
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

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/pause_care_non_critical", [
            'status_reason' => 'non_critical_services_paused_for_overdue_billing',
            'status_reason_note' => 'Paused pending payment recovery plan.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'care_paused_non_critical');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'care_paused_non_critical',
        ]);
        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'payment_overdue',
            'to_status' => 'care_paused_non_critical',
            'action_key' => 'pause_care_non_critical',
            'actor_role' => 'hospital',
        ]);
        $this->assertDatabaseHas('home_care_episodes', [
            'appointment_id' => $appointment->id,
            'episode_status' => 'care_paused_non_critical',
        ]);
    }

    public function test_hospital_can_reinstate_care_from_paused_episode(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);
        $patient = Patient::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'care_paused_non_critical',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'type' => 'home_visit',
            'status' => 'paid',
        ]);
        $appointment->payment_id = $payment->id;
        $appointment->save();
        DB::table('home_care_episodes')->insert([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'hospital_id' => $hospital->id,
            'episode_status' => 'care_paused_non_critical',
            'current_quote_id' => null,
            'care_plan_json' => null,
            'started_at' => Carbon::now()->subDays(3),
            'paused_at' => Carbon::now()->subDay(),
            'closed_at' => null,
            'discharged_at' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        Passport::actingAs($hospitalUser);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/reinstate_care", [
            'status_reason' => 'payment_recovered_restarting_services',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'home_admitted_active');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'home_admitted_active',
        ]);
        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'care_paused_non_critical',
            'to_status' => 'home_admitted_active',
            'action_key' => 'reinstate_care',
            'actor_role' => 'hospital',
        ]);
        $this->assertDatabaseHas('home_care_episodes', [
            'appointment_id' => $appointment->id,
            'episode_status' => 'home_admitted_active',
        ]);
    }

    public function test_hospital_can_close_episode_for_nonpayment(): void
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

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/close_episode_nonpayment", [
            'status_reason' => 'episode_closed_after_nonpayment',
            'status_reason_note' => 'Closed after grace period expiry.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'episode_closed_nonpayment');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'episode_closed_nonpayment',
        ]);
        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'payment_overdue',
            'to_status' => 'episode_closed_nonpayment',
            'action_key' => 'close_episode_nonpayment',
            'actor_role' => 'hospital',
        ]);
        $this->assertDatabaseHas('home_care_episodes', [
            'appointment_id' => $appointment->id,
            'episode_status' => 'episode_closed_nonpayment',
        ]);
    }
}
