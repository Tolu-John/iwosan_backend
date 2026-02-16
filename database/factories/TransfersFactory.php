<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Transfers;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransfersFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transfers::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
          
            'payment_id'=>Patient::all()->random(),
            
            'type_id'=>Appointment::all()->random(),
            
            'hospital_id'=>Hospital::all()->random(),
     
            'carer_id'=>Carer::all()->random(),

            'reason'=>$this->faker->randomElement(['virtual_visit','home_visit','tele_test']),
          
            'amount'=>$this->faker->numberBetween($min = 4000, $max = 5000),
          
            'recipient'=>$this->faker->randomFloat($nbMaxDecimals = 1, $min = 0, $max = NULL),           

        ];
    }
}
