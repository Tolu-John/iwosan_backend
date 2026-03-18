<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VirtualVisitWorkflowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_can_advance_requested_to_pending_review(): void
    {
        [$patientUser, $carerUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'requested',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/approve", [
            'status_reason_note' => 'Triage approved for clinician review.',
            'status_reason_code' => 'triage_approved',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'pending_review');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending_review',
        ]);

        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'requested',
            'to_status' => 'pending_review',
            'action_key' => 'approve',
            'actor_role' => 'hospital',
        ]);

        $this->assertDatabaseHas('virtual_visit_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'requested',
            'to_status' => 'pending_review',
            'action_key' => 'approve',
            'actor_role' => 'hospital',
        ]);
    }

    public function test_pending_payment_requires_paid_payment_before_scheduled(): void
    {
        [$patientUser, $carerUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $unpaidPayment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'pending',
            'type' => 'virtual_visit',
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'pending_payment',
            'payment_id' => $unpaidPayment->id,
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($patientUser);
        $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/pay_now")
            ->assertStatus(422);

        $paidPayment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'paid',
            'type' => 'virtual_visit',
        ]);

        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/pay_now", [
            'payment_id' => $paidPayment->id,
            'status_reason' => 'payment_completed',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'scheduled')
            ->assertJsonPath('payment_id', $paidPayment->id);
    }

    public function test_sla_monitor_expires_stale_virtual_pending_payments_only(): void
    {
        [, , $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $old = Carbon::now()->subHours(3);

        $virtual = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'pending_payment',
            'updated_at' => $old,
        ]);

        $home = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'pending_payment',
            'updated_at' => $old,
        ]);

        Passport::actingAs($hospitalUser);
        Artisan::call('iwosan:monitor-virtual-visit-workflow-sla');

        $virtual->refresh();
        $home->refresh();

        $this->assertSame('payment_expired', $virtual->status);
        $this->assertSame('pending_payment', $home->status);

        $this->assertDatabaseHas('virtual_visit_status_history', [
            'appointment_id' => $virtual->id,
            'from_status' => 'pending_payment',
            'to_status' => 'payment_expired',
            'action_key' => 'system_transition',
            'actor_role' => 'system',
        ]);
    }

    public function test_sla_monitor_opens_waiting_room_for_due_virtual_scheduled_appointments(): void
    {
        [, , $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $leadMinutes = max(
            0,
            (int) (config('virtual_visit_workflow.sla_windows.waiting_room_open.open_lead_minutes') ?? 15)
        );
        $workflowTimezone = (string) (config('virtual_visit_workflow.sla_windows.waiting_room_open.timezone') ?? 'Africa/Lagos');
        $now = Carbon::now($workflowTimezone);

        $dueVirtual = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'scheduled',
            'date_time' => $now->copy()->addMinutes(max(0, $leadMinutes - 1))->format('Y-m-d H:i:s'),
        ]);

        $notDueVirtual = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'scheduled',
            'date_time' => $now->copy()->addMinutes($leadMinutes + 10)->format('Y-m-d H:i:s'),
        ]);

        $dueHome = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'home_visit',
            'status' => 'scheduled',
            'date_time' => $now->copy()->addMinutes(max(0, $leadMinutes - 1))->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);
        Artisan::call('iwosan:monitor-virtual-visit-workflow-sla');

        $dueVirtual->refresh();
        $notDueVirtual->refresh();
        $dueHome->refresh();

        $this->assertSame('waiting_room_open', $dueVirtual->status);
        $this->assertNotNull($dueVirtual->waiting_room_opened_at);
        $this->assertSame('scheduled', $notDueVirtual->status);
        $this->assertSame('scheduled', $dueHome->status);

        $this->assertDatabaseHas('virtual_visit_status_history', [
            'appointment_id' => $dueVirtual->id,
            'from_status' => 'scheduled',
            'to_status' => 'waiting_room_open',
            'action_key' => 'system_transition',
            'actor_role' => 'system',
        ]);
    }

    public function test_escalation_path_persists_escalation_rows_and_events(): void
    {
        [, , $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'session_failed',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);
        $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/escalate", [
            'status_reason_note' => 'Session setup failed repeatedly.',
            'status_reason_code' => 'session_failure_escalation',
            'severity' => 'high',
            'pathway' => 'clinical_ops',
        ])->assertStatus(200)->assertJsonPath('status', 'escalation_open');

        $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/start_response", [
            'status_reason_note' => 'Escalation response started.',
        ])->assertStatus(200)->assertJsonPath('status', 'escalation_in_progress');

        $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/decide_outcome", [
            'status_reason_note' => 'Escalation resolved with manual recovery.',
        ])->assertStatus(200)->assertJsonPath('status', 'escalation_resolved');

        $this->assertDatabaseHas('escalations', [
            'appointment_id' => $appointment->id,
            'encounter_type' => 'virtual_visit',
            'status' => 'escalation_resolved',
            'severity' => 'high',
            'pathway' => 'clinical_ops',
        ]);

        $this->assertDatabaseHas('escalation_events', [
            'event_type' => 'escalation_in_progress',
        ]);
        $this->assertDatabaseHas('escalation_events', [
            'event_type' => 'escalation_resolved',
        ]);
    }

    public function test_request_changes_requires_status_reason_note(): void
    {
        [, , $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'requested',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);
        $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/request_changes")
            ->assertStatus(422);
    }

    public function test_reassign_updates_clinician_even_when_status_stays_same(): void
    {
        [, , $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $alternateCarer = Carer::factory()->create([
            'hospital_id' => $hospital->id,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'awaiting_clinician_approval',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($hospitalUser);
        $response = $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/reassign", [
            'carer_id' => $alternateCarer->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'awaiting_clinician_approval');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'awaiting_clinician_approval',
            'carer_id' => $alternateCarer->id,
        ]);

        $this->assertDatabaseHas('virtual_visit_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'awaiting_clinician_approval',
            'to_status' => 'awaiting_clinician_approval',
            'action_key' => 'reassign',
            'actor_role' => 'hospital',
        ]);
    }

    public function test_clinician_accept_routes_to_scheduled_when_payment_already_paid(): void
    {
        [$patientUser, $carerUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $paidPayment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'paid',
            'type' => 'virtual_visit',
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'awaiting_clinician_approval',
            'payment_id' => $paidPayment->id,
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($carerUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/accept")
            ->assertStatus(200)
            ->assertJsonPath('status', 'scheduled')
            ->assertJsonPath('payment_id', $paidPayment->id);

        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'awaiting_clinician_approval',
            'to_status' => 'scheduled',
            'action_key' => 'accept',
            'actor_role' => 'carer',
        ]);
    }

    public function test_clinician_accept_routes_to_pending_payment_when_payment_not_paid(): void
    {
        [$patientUser, $carerUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $pendingPayment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'pending',
            'type' => 'virtual_visit',
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'appointment_type' => 'virtual_visit',
            'status' => 'awaiting_clinician_approval',
            'payment_id' => $pendingPayment->id,
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($carerUser);

        $this->postJson("/api/v1/appointment/{$appointment->id}/virtual-actions/accept")
            ->assertStatus(200)
            ->assertJsonPath('status', 'pending_payment')
            ->assertJsonPath('payment_id', $pendingPayment->id);

        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appointment->id,
            'from_status' => 'awaiting_clinician_approval',
            'to_status' => 'pending_payment',
            'action_key' => 'accept',
            'actor_role' => 'carer',
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Patient, 4: Carer, 5: Hospital}
     */
    private function seedActors(): array
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);

        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);

        $carerUser = User::factory()->create();
        $carer = Carer::factory()->create([
            'user_id' => $carerUser->id,
            'hospital_id' => $hospital->id,
        ]);

        return [$patientUser, $carerUser, $hospitalUser, $patient, $carer, $hospital];
    }
}
