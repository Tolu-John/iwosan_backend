<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\HConsultation;
use App\Models\Consultation;
use App\Models\ward;
use Illuminate\Database\Eloquent\Factories\Factory;

class HConsultationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = HConsultation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $consultation = Consultation::query()->inRandomOrder()->first();
        if (!$consultation) {
            $consultation = Consultation::factory()->create();
        }

        $wardId = ward::query()->inRandomOrder()->value('id');
        if (!$wardId) {
            $appointment = Appointment::query()
                ->where('patient_id', $consultation->patient_id)
                ->where('carer_id', $consultation->carer_id)
                ->inRandomOrder()
                ->first() ?? Appointment::query()->inRandomOrder()->first();

            if (!$appointment) {
                $appointment = Appointment::factory()->create([
                    'patient_id' => $consultation->patient_id,
                    'carer_id' => $consultation->carer_id,
                    'consult_id' => $consultation->id,
                ]);
            }

            $hospitalId = $consultation->hospital_id ?: optional(Carer::find($consultation->carer_id))->hospital_id;

            $ward = new ward();
            $ward->patient_id = $consultation->patient_id;
            $ward->carer_id = $consultation->carer_id;
            $ward->hospital_id = $hospitalId;
            $ward->appt_id = $appointment->id;
            $ward->diagnosis = $consultation->diagnosis ?? $this->faker->sentence();
            $ward->admission_date = now()->toDateString();
            $ward->ward_vitals = json_encode(['seeded' => true]);
            $ward->priority = 'medium';
            $ward->discharged = 0;
            $ward->save();
            $wardId = $ward->id;
        }

        return [
            'consultation_id' => $consultation->id,
            'ward_id' => $wardId,
            'address' => $this->faker->address,
        ];
    }
}
