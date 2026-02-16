<?php

namespace Database\Factories;

use App\Models\complaints;
use App\Models\Hospital;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplaintsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = complaints::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'hospital_id'=>Hospital::all()->random(),
            'title'=>$this->faker->sentence,
            'complaint'=>$this->faker->paragraph,
            'patient_id'=>Patient::all()->random()
            
        ];
    }
}
