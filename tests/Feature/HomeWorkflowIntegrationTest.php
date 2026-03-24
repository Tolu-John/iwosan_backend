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
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HomeWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_home_visit_flow_reaches_visit_completed(): void
    {
        [$patientUser, $carerUser, $hospitalUser, $patient, $carer] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'awaiting_hospital_approval',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'type' => 'home_visit',
            'status' => 'paid',
        ]);

        $this->assertActionStatus($hospitalUser, $appointment, 'approve', 'awaiting_clinician_approval');
        $this->assertActionStatus($carerUser, $appointment, 'approve_assignment', 'awaiting_payment');
        $this->assertActionStatus($patientUser, $appointment, 'pay_now', 'scheduled', [
            'payment_id' => $payment->id,
            'status_reason' => 'patient_completed_payment',
        ]);
        $this->assertActionStatus($carerUser, $appointment, 'start_travel', 'en_route');
        $this->assertActionStatus($carerUser, $appointment, 'mark_arrived', 'arrived');
        $this->assertActionStatus($patientUser, $appointment, 'confirm_check_in', 'in_progress');
        $this->assertActionStatus($carerUser, $appointment, 'complete_visit', 'visit_completed', [
            'status_reason' => 'visit_done_no_admission',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'visit_completed',
            'payment_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'in_progress',
            'to_status' => 'visit_completed',
            'action_key' => 'complete_visit',
            'actor_role' => 'carer',
        ]);
    }

    public function test_admission_quote_revision_and_activation_flow(): void
    {
        [$patientUser, $carerUser, $hospitalUser, $patient, $carer] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'in_progress',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'type' => 'home_visit',
            'status' => 'paid',
        ]);

        $this->assertActionStatus($carerUser, $appointment, 'complete_visit', 'admission_recommended', [
            'disposition' => 'admit_to_home_care',
        ]);
        $this->assertActionStatus($carerUser, $appointment, 'submit_admission_plan', 'home_admission_quote_pending_hospital', [
            'billing_cycle' => 'monthly',
            'enrollment_fee_minor' => 1000000,
            'recurring_fee_minor' => 500000,
            'addons_total_minor' => 50000,
            'tax_total_minor' => 25000,
            'discount_total_minor' => 10000,
            'grand_total_minor' => 1565000,
            'quote_valid_until' => now()->addDays(5)->toIso8601String(),
        ]);

        $this->assertActionStatus($hospitalUser, $appointment, 'request_quote_revision', 'admission_revision_requested', [
            'enrollment_fee_minor' => 1000000,
            'recurring_fee_minor' => 500000,
            'billing_cycle' => 'monthly',
            'addons_total_minor' => 50000,
            'tax_total_minor' => 25000,
            'discount_total_minor' => 10000,
            'status_reason' => 'please_adjust_discounts',
            'status_reason_note' => 'please_adjust_discounts',
        ]);

        $this->assertActionStatus($carerUser, $appointment, 'submit_admission_plan', 'home_admission_quote_pending_hospital', [
            'billing_cycle' => 'monthly',
            'enrollment_fee_minor' => 900000,
            'recurring_fee_minor' => 500000,
            'addons_total_minor' => 40000,
            'tax_total_minor' => 20000,
            'discount_total_minor' => 20000,
            'grand_total_minor' => 1440000,
            'quote_valid_until' => now()->addDays(5)->toIso8601String(),
        ]);

        $this->assertActionStatus($hospitalUser, $appointment, 'approve_quote', 'home_admitted_pending_payment', [
            'enrollment_fee_minor' => 900000,
            'recurring_fee_minor' => 500000,
            'billing_cycle' => 'monthly',
            'addons_total_minor' => 40000,
            'tax_total_minor' => 20000,
            'discount_total_minor' => 20000,
            'status_reason' => 'quote_approved',
        ]);

        $this->assertActionStatus($patientUser, $appointment, 'pay_admission', 'home_admitted_active', [
            'payment_id' => $payment->id,
            'status_reason' => 'payment_completed',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'home_admitted_active',
            'payment_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('home_care_episodes', [
            'appointment_id' => $appointment->id,
            'episode_status' => 'home_admitted_active',
        ]);
        $this->assertDatabaseCount('home_admission_quotes', 3);
        $latestQuote = DB::table('home_admission_quotes')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('version')
            ->first();
        $this->assertNotNull($latestQuote);
        $this->assertSame('approved', $latestQuote->quote_status);
    }

    public function test_escalation_transfer_and_resolution_flow(): void
    {
        [, $carerUser, $hospitalUser, $patient, $carer] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'home_admitted_active',
            'date_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $this->assertActionStatus($carerUser, $appointment, 'open_escalation', 'escalation_open', [
            'severity' => 'urgent',
            'pathway' => 'intervention',
            'status_reason' => 'patient_condition_worsened',
        ]);
        $this->assertActionStatus($hospitalUser, $appointment, 'transfer_escalation', 'escalation_in_transfer', [
            'severity' => 'urgent',
            'pathway' => 'transfer',
        ]);
        $this->assertActionStatus($hospitalUser, $appointment, 'resolve_escalation', 'escalation_resolved', [
            'status_reason' => 'patient_stabilized',
        ]);

        $this->assertDatabaseHas('escalations', [
            'appointment_id' => $appointment->id,
            'status' => 'escalation_resolved',
        ]);
        $this->assertDatabaseHas('escalation_events', [
            'event_type' => 'status_transition',
            'actor_role' => 'hospital',
        ]);
    }

    /**
     * @return array{0:User,1:User,2:User,3:Patient,4:Carer}
     */
    private function seedActors(): array
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);

        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);

        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $carerUser = User::query()->findOrFail($carer->user_id);

        return [$patientUser, $carerUser, $hospitalUser, $patient, $carer];
    }

    private function assertActionStatus(User $actor, Appointment $appointment, string $actionKey, string $expectedStatus, array $payload = []): void
    {
        Passport::actingAs($actor);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/actions/{$actionKey}", $payload);
        $response
            ->assertStatus(200)
            ->assertJsonPath('status', $expectedStatus);

        $appointment->refresh();
        $this->assertSame($expectedStatus, strtolower(trim((string) $appointment->status)));
    }
}
