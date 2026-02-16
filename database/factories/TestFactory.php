<?php

namespace Database\Factories;

use App\Models\test;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = test::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'hospital_id'=>Hospital::all()->random(),
            'name'=>$this->faker->sentence,
            'price'=>$this->faker->numberBetween($int1 = 1000, $int2 = 100000)
        
        ];
    }
}
