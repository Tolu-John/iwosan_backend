<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Teletest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TeletestWorkflowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_teletest_action_endpoint_executes_full_lifecycle_with_side_effects(): void
    {
        [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'paid',
            'type' => 'tele_test',
        ]);

        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
            'status' => 'awaiting_hospital_approval',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        $this->assertTeletestAction($hospitalUser, $teletest, 'approve_request', 'awaiting_technician_approval');
        $this->assertTeletestAction($clinicianUser, $teletest, 'accept_assignment', 'awaiting_payment');
        $this->assertTeletestAction($patientUser, $teletest, 'pay_now', 'scheduled');
        $this->assertTeletestAction($clinicianUser, $teletest, 'start_travel', 'en_route');
        $this->assertTeletestAction($clinicianUser, $teletest, 'mark_arrived', 'arrived');
        $this->assertTeletestAction($patientUser, $teletest, 'confirm_check_in', 'in_progress', [
            'check_in_confirmed' => true,
        ]);
        $this->assertTeletestAction($clinicianUser, $teletest, 'complete_visit', 'sample_collected', [
            'sample_evidence' => ['sample_id' => 'SMP-001', 'tube_type' => 'EDTA'],
        ]);
        $this->assertTeletestAction($clinicianUser, $teletest, 'submit_handover', 'lab_processing');

        DB::table('teletest_results')->insert([
            'teletest_id' => $teletest->id,
            'report_url' => 'https://example.com/reports/rpt-001.pdf',
            'validated_by' => $hospitalUser->id,
            'validated_at' => now(),
            'result_summary' => 'Validated by quality team',
            'abnormal_flag' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTeletestAction($hospitalUser, $teletest, 'monitor_processing_sla', 'result_ready', [
            'target_status' => 'result_ready',
        ]);

        DB::table('teletest_result_deliveries')->insert([
            'teletest_id' => $teletest->id,
            'channel' => 'in_app',
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'receipt_ref' => 'DELIVERY-001',
            'payload_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTeletestAction($hospitalUser, $teletest, 'notify_patient', 'result_delivered');
        $this->assertTeletestAction($patientUser, $teletest, 'open_record', 'visit_completed');
        $this->assertTeletestAction($hospitalUser, $teletest, 'view_audit', 'visit_closed', [
            'target_status' => 'visit_closed',
        ]);

        $this->assertDatabaseHas('teletests', [
            'id' => $teletest->id,
            'status' => 'visit_closed',
            'payment_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('teletest_status_history', [
            'teletest_id' => $teletest->id,
            'from_status' => 'visit_completed',
            'to_status' => 'visit_closed',
            'action_key' => 'view_audit',
            'actor_role' => 'hospital',
        ]);
        $this->assertDatabaseHas('teletest_sample_events', [
            'teletest_id' => $teletest->id,
            'event_type' => 'collected',
        ]);
    }

    public function test_role_and_guard_rules_block_invalid_actions(): void
    {
        [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => null,
            'status' => 'awaiting_hospital_approval',
            'date_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        Passport::actingAs($patientUser);
        $this->postJson("/api/v1/teletest/{$teletest->id}/actions/approve_request")
            ->assertStatus(403);

        $this->assertTeletestAction($hospitalUser, $teletest, 'approve_request', 'awaiting_technician_approval');
        $this->assertTeletestAction($clinicianUser, $teletest, 'accept_assignment', 'awaiting_payment');

        Passport::actingAs($patientUser);
        $this->postJson("/api/v1/teletest/{$teletest->id}/actions/pay_now")
            ->assertStatus(422);

        $paidPayment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'paid',
            'type' => 'tele_test',
        ]);
        $teletest->payment_id = $paidPayment->id;
        $teletest->save();

        $this->assertTeletestAction($patientUser, $teletest, 'pay_now', 'scheduled');
        $this->assertTeletestAction($clinicianUser, $teletest, 'start_travel', 'en_route');
        $this->assertTeletestAction($clinicianUser, $teletest, 'mark_arrived', 'arrived');

        Passport::actingAs($clinicianUser);
        $this->postJson("/api/v1/teletest/{$teletest->id}/actions/start_visit")
            ->assertStatus(422);
    }

    public function test_sla_monitor_command_applies_expected_timeout_transitions(): void
    {
        [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $old = Carbon::now()->subDays(2);

        $awaitingTechnician = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => 'awaiting_technician_approval',
            'updated_at' => $old,
        ]);

        $awaitingPayment = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => 'awaiting_payment',
            'updated_at' => $old,
        ]);

        $enRoute = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => 'en_route',
            'date_time' => Carbon::now()->subHours(2)->format('Y-m-d H:i:s'),
        ]);

        $labProcessing = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => 'lab_processing',
            'updated_at' => $old,
        ]);

        Artisan::call('iwosan:monitor-teletest-workflow-sla');

        $awaitingTechnician->refresh();
        $awaitingPayment->refresh();
        $enRoute->refresh();
        $labProcessing->refresh();

        $this->assertSame('technician_reassignment_pending', $awaitingTechnician->status);
        $this->assertSame('payment_expired', $awaitingPayment->status);
        $this->assertSame('arrival_breach', $enRoute->status);
        $this->assertSame('escalation_open', $labProcessing->status);

        $this->assertDatabaseHas('teletest_status_history', [
            'teletest_id' => $awaitingTechnician->id,
            'to_status' => 'technician_reassignment_pending',
            'action_key' => 'system_transition',
        ]);
        $this->assertDatabaseHas('teletest_status_history', [
            'teletest_id' => $awaitingPayment->id,
            'to_status' => 'payment_expired',
            'action_key' => 'system_transition',
        ]);
        $this->assertDatabaseHas('teletest_status_history', [
            'teletest_id' => $enRoute->id,
            'to_status' => 'arrival_breach',
            'action_key' => 'system_transition',
        ]);
        $this->assertDatabaseHas('teletest_status_history', [
            'teletest_id' => $labProcessing->id,
            'to_status' => 'escalation_open',
            'action_key' => 'system_transition',
        ]);
    }

    public function test_action_endpoint_respects_teletest_feature_flag(): void
    {
        [, , $hospitalUser, $patient, $carer, $hospital] = $this->seedActors();

        $teletest = Teletest::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => 'awaiting_hospital_approval',
        ]);

        config(['teletest_workflow.enabled' => false]);

        Passport::actingAs($hospitalUser);
        $this->postJson("/api/v1/teletest/{$teletest->id}/actions/approve_request")
            ->assertStatus(409)
            ->assertJsonPath('status', 'awaiting_hospital_approval');

        $teletest->refresh();
        $this->assertSame('awaiting_hospital_approval', $teletest->status);
    }

    /**
     * @return array{0:User,1:User,2:User,3:Patient,4:Carer,5:Hospital}
     */
    private function seedActors(): array
    {
        $patientUser = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);

        $hospital = Hospital::factory()->create();
        $hospitalUser = User::query()->findOrFail($hospital->user_id);

        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);
        $clinicianUser = User::query()->findOrFail($carer->user_id);

        return [$patientUser, $clinicianUser, $hospitalUser, $patient, $carer, $hospital];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertTeletestAction(
        User $actor,
        Teletest $teletest,
        string $actionKey,
        string $expectedStatus,
        array $payload = []
    ): void {
        Passport::actingAs($actor);

        $this->postJson("/api/v1/teletest/{$teletest->id}/actions/{$actionKey}", $payload)
            ->assertStatus(200)
            ->assertJsonPath('status', $expectedStatus);

        $teletest->refresh();
        $this->assertSame($expectedStatus, strtolower(trim((string) $teletest->status)));
    }
}
