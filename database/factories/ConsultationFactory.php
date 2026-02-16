<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Payment;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Consultation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'patient_id'=>Patient::factory(),
            'carer_id'=>Carer::factory(),
            'payment_id'=>Payment::factory(),
            'hospital_id'=>Hospital::factory(),
         //   'review_id'=>Review::all()->random(),

            'status'=>$this->faker->randomElement(['completed','incomplete']),
            'treatment_type'=>$this->faker->randomElement(['Virtual visit','Home visit']),
            'diagnosis'=>$this->faker->sentences($nb = 3, $asText = true),
            'consult_notes'=>$this->faker->sentences($nb = 5, $asText = true),
            'date_time'=>$this->faker->dateTime(),
              
            
        ];
    }
}
