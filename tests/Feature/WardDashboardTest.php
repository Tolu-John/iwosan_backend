<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\Hospital;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\timeline;
use App\Models\User;
use App\Models\ward;
use App\Models\ward_bp_dia;
use App\Models\ward_bp_sys;
use App\Models\ward_sugar;
use App\Models\ward_temp;
use App\Models\ward_weight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Tests\TestCase;

class WardDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ward_dashboard_returns_summary_and_pagination(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        Schema::disableForeignKeyConstraints();

        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
        ]);

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'payment_id' => $payment->id,
            'consult_id' => $consultation->id,
            'ward_id' => null,
        ]);

        $ward = new ward();
        $ward->patient_id = $patient->id;
        $ward->carer_id = $carer->id;
        $ward->hospital_id = $hospital->id;
        $ward->appt_id = $appointment->id;
        $ward->diagnosis = 'Observation';
        $ward->admission_date = now()->toDateString();
        $ward->ward_vitals = json_encode([
            ['name' => 'temperature'],
            ['name' => 'weight'],
            ['name' => 'Blood Pressure Diastolic'],
            ['name' => 'Blood Pressure Systolic'],
            ['name' => 'sugar'],
        ]);
        $ward->priority = 'medium';
        $ward->save();

        $appointment->ward_id = $ward->id;
        $appointment->save();

        Schema::enableForeignKeyConstraints();

        $temp = new ward_temp();
        $temp->ward_id = $ward->id;
        $temp->value = 36.6;
        $temp->save();

        $weight = new ward_weight();
        $weight->ward_id = $ward->id;
        $weight->value = 70;
        $weight->save();

        $bpSys = new ward_bp_sys();
        $bpSys->ward_id = $ward->id;
        $bpSys->value = 120;
        $bpSys->save();

        $bpDia = new ward_bp_dia();
        $bpDia->ward_id = $ward->id;
        $bpDia->value = 80;
        $bpDia->save();

        $sugar = new ward_sugar();
        $sugar->ward_id = $ward->id;
        $sugar->value = 5.5;
        $sugar->save();

        $timeline = new timeline();
        $timeline->ward_id = $ward->id;
        $timeline->text = 'Vitals recorded';
        $timeline->type = 'note';
        $timeline->type_id = 1;
        $timeline->save();

        Drug::factory()->create(['ward_id' => $ward->id, 'consultation_id' => $consultation->id]);
        LabTest::factory()->create(['ward_id' => $ward->id, 'consultation_id' => $consultation->id]);

        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/ward/dashboard/' . $ward->id . '?timeline_per_page=1&timeline_page=1&vitals_per_page=1&vitals_page=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ward',
            'patient',
            'carer',
            'hospital',
            'vitals' => [
                '*' => ['ward_id', 'name', 'vitalsList', 'pagination'],
            ],
            'timeline' => ['data', 'pagination'],
            'prescriptions' => ['drugs', 'lab_tests'],
            'alerts',
            'updated_at',
        ]);
    }

    public function test_ward_dashboard_forbidden_for_other_patient(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carer = Carer::factory()->create(['hospital_id' => $hospital->id]);

        Schema::disableForeignKeyConstraints();

        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
        ]);

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'payment_id' => $payment->id,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'payment_id' => $payment->id,
            'consult_id' => $consultation->id,
            'ward_id' => null,
        ]);

        $ward = new ward();
        $ward->patient_id = $patient->id;
        $ward->carer_id = $carer->id;
        $ward->hospital_id = $hospital->id;
        $ward->appt_id = $appointment->id;
        $ward->diagnosis = 'Observation';
        $ward->admission_date = now()->toDateString();
        $ward->ward_vitals = json_encode([['name' => 'temperature']]);
        $ward->priority = 'medium';
        $ward->save();

        $appointment->ward_id = $ward->id;
        $appointment->save();

        Schema::enableForeignKeyConstraints();

        $otherUser = User::factory()->create();
        Patient::factory()->create(['user_id' => $otherUser->id]);

        Passport::actingAs($otherUser);

        $this->getJson('/api/v1/ward/dashboard/' . $ward->id)->assertStatus(403);
    }
}
