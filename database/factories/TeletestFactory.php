<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Teletest;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeletestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Teletest::class;

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

            'hospital_id'=>Hospital::factory(),

           'payment_id'=>Payment::factory(),
           // 'review_id'=>Review::all()->random(),

            'status'=>$this->faker->randomElement([
            'pending_payment',
            'scheduled',
            'completed']),

            'test_name'=>$this->faker->name,
            'date_time'=>$this->faker->dateTime($max = 'now', $timezone = null) ,
            'address'=>$this->faker->address,
            'admin_approved'=>$this->faker->boolean($chanceOfGettingTrue = 50),
            
        ];
    }
}
