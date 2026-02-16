<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Patient;
use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Transfers;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payment::class;

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
           // 'transfer_id'=>Transfers::all()->random(),

            'code'=>$this->faker->password,
            'reference'=>$this->faker->uuid,
            'gateway'=>$this->faker->randomElement(['paystack','manual']),
            'type'=>$this->faker->randomElement(['virtual_visit','home_visit','tele_test']),
           //'type_id'=>Consultation::all()->random(),
            'price'=>$this->faker->numberBetween($min = 4000, $max = 5000),
           'status'=>$this->faker->randomElement(['pending','processing','paid']),
           'status_reason'=>null,
           //'method'=>$this->faker->randomElement(['card','transfer']),
           'reuse'=>$this->faker->boolean,
           'verified_at'=>null,
           
        ];
    }
}
