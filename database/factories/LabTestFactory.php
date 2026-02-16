<?php

namespace Database\Factories;

use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Carer;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabTestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LabTest::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
           
           'consultation_id'=>Consultation::factory(),
           'test_name'=>json_encode($this->faker->sentence),
           'lab_recomm'=>json_encode($this->faker->sentence),
           'extra_notes'=>json_encode($this->faker->sentence),
           'status'=>$this->faker->randomElement(['ordered','scheduled','collected','resulted']),
           'done'=>false,
        
        ];
    }
}
