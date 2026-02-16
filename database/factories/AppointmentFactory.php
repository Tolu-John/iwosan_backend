<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Carer;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Appointment::class;

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
            'payment_id'=>null,
            'consult_id'=>Consultation::factory(),
            'address'=>$this->faker->address,
            'extra_notes'=>$this->faker->paragraph,
            'price'=>$this->faker->numberBetween($int1 = 4000, $int2 = 5000),
            'status'=>$this->faker->randomElement([
                'pending',
            'Aprroved',
            'awaiting',
            'rejected',
            'started',
            'paid']),

            'consult_type'=>$this->faker->randomElement(['NOW','PRE_PLANNED']),

           'appointment_type' =>$this->faker->randomElement([
               'virtual_visit','home_visit','tele_test']),

               'admin_approved'=>$this->faker->boolean($chanceOfGettingTrue = 50),

               'date_time'=>$this->faker->dateTime(),
            
           
               



        ];
    }
}
