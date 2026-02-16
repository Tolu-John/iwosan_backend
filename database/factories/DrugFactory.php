<?php

namespace Database\Factories;

use App\Models\Drug;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrugFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Drug::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'consultation_id'=>Consultation::factory(),
            
            'stop_date'=>$this->faker->date($format = 'd-m-Y', $max = '2022-04-09'), // '1979-06-09'
            'start_date'=>$this->faker->date($format = 'd-m-Y', $max = '2022-04-09'),
            'drug_type'=>$this->faker->randomElement(['tablet', 'capsule', 'syrup', 'injection']),
          
            'name'=>$this->faker->name,
            
            'duration'=>$this->faker->numberBetween($min = 10, $max = 30),
            
            'quantity'=>$this->faker->randomElement([ "2 tablespoons",  "2 tablets", "2 tea spoons"]),
            
            'started'=>$this->faker->boolean($chanceOfGettingTrue = 20),
           
            'finished'=>$this->faker->boolean($chanceOfGettingTrue = 20),
           
            'extra_notes'=>$this->faker->paragraph,
           
            'dosage'=>$this->faker->randomElement([ "Morning",  "Morning And Evening", "Morning, Afternoon and Evening"]),
            
            'carer_name'=>$this->faker->name,
            'status'=>$this->faker->randomElement(['active','completed','discontinued']),

        ];
    }
}
