<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'patient_id'=>Patient::all()->random(),
            'carer_id'=>Carer::all()->random(),
            'consultation_id'=>Consultation::all()->random(),
     
            'text'=>$this->faker->paragraph,
            'rating'=>$this->faker->randomFloat($nbMaxDecimals = 1, $min = 0, $max = 5),
            'recomm'=>$this->faker->boolean($chanceOfGettingTrue = 20),
          
        ];
    }
}
