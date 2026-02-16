<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [

            'user_id'=>User::factory(),
        
            'bloodtype'=>$this->faker->randomElement(['O+','O-','B+','B-']),
            'genotype'=>$this->faker->randomElement(['AA','AS','SS']),
            'sugar_level'=>$this->faker->randomFloat($nbMaxDecimals = 1, $min = 3, $max = 10) ,
            'bloodpressure'=>$this->faker->randomElement(['120/70','110/60','80/120','90/60','130/90','100/70','150/80','120/70','80/90','90/150','126/70','115/70']),
            'weight'=>$this->faker->numberBetween($min = 50, $max = 100),
            'height'=>$this->faker->randomFloat($nbMaxDecimals = 1, $min = 0, $max = 250),
            
        ];
    }
}
