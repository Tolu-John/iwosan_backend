<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class HospitalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Hospital::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
           
            'name'=>$this->faker->name,
          
            'code'=>$this->faker->password,
          
            'rating'=>$this->faker->randomFloat($nbMaxDecimals = 1, $min = 0, $max = 5),
            'firedb_id' => $this->faker->randomNumber($nbDigits = NULL, $strict = false),
            'about_us'=>$this->faker->paragraph($nbSentences = 6, $variableNbSentences = true),
          
            'website'=>$this->faker->url,
          
            'hospital_img'=>$this->faker->imageUrl,
           
            'email'=>$this->faker->unique()->safeEmail(),
         
            'phone'=>$this->faker->phoneNumber,
          
            'lat'=>$this->faker->latitude($min = -90, $max = 90),
           
            'lon'=>$this->faker->longitude($min = -180, $max = 180),
          
            'password'=>Hash::make('password'),
           
            'address'=>$this->faker->address,

            'super_admin_approved'=>$this->faker->boolean,

        

        
        ];
    }
}
