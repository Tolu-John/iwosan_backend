<?php

namespace Database\Factories;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Carer::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'user_id'=>User::factory(),
            'hospital_id'=>Hospital::factory(),
            'bio'=>$this->faker->paragraph,

            'position'=>$this->faker->randomElement(['General','Dentist','Heart'
            ,'Nurse','Lab. Tech.','optician','Gynaecologist','Therapist']),

          //  'rating'=>$this->faker->randomFloat($nbMaxDecimals = 1, $min = 0, $max = 5),
          //  'call_address'=>$this->faker->password,
            'super_admin_approved'=>$this->faker->boolean,
            'onHome_leave'=>$this->faker->boolean($chanceOfGettingTrue = 20),
            'onvirtual_leave'=>$this->faker->boolean($chanceOfGettingTrue = 20),
            'admin_approved'=>$this->faker->boolean($chanceOfGettingTrue = 20),
          
          'qualifications'=>"Phd university of walden(?)
                             Bsc university of Lagos(?)
                             Certified Nigerian Institute",
         
            'virtual_day_time'=>'{
                "mon":[
                {"avail":"off"},
                {"start":"8:00"},
                {"stop":"14:00"}
                ]
                ,
                "tue":[
                {"avail":"on"},
                {"start":"8:00"},
                {"stop":"17:00"}
                ]
                ,
                "wed":[
                {"avail":"on"},
                {"start":"14:00"},
                {"stop":"21:00"}
                ]
                ,
                "thur":[
                {"avail":"off"},
                {"start":"null"},
                {"stop":"null"}
                ]
                ,
                
                "fri":[
                {"avail":"on"},
                {"start":"14:00"},
                {"stop":"21:00"}
                ]
                ,
                "sat":[
                {"avail":"on"},
                {"start":"14:00"},
                {"stop":"21:00"}
                ]
                ,
                "sun":[
                {"avail":"off"},
                {"start":"null"},
                {"stop":"null"}
                ]
                }',

            'home_day_time'=>'{
                    "mon":[
                    {"avail":"off"},
                    {"start":"8:00"},
                    {"stop":"14:00"}
                    ]
                    ,
                    "tue":[
                    {"avail":"on"},
                    {"start":"8:00"},
                    {"stop":"17:00"}
                    ]
                    ,
                    "wed":[
                    {"avail":"on"},
                    {"start":"14:00"},
                    {"stop":"21:00"}
                    ]
                    ,
                    "thur":[
                    {"avail":"off"},
                    {"start":"null"},
                    {"stop":"null"}
                    ]
                    ,
                    
                    "fri":[
                    {"avail":"on"},
                    {"start":"14:00"},
                    {"stop":"21:00"}
                    ]
                    ,
                    "sat":[
                    {"avail":"on"},
                    {"start":"14:00"},
                    {"stop":"21:00"}
                    ]
                    ,
                    "sun":[
                    {"avail":"off"},
                    {"start":"null"},
                    {"stop":"null"}
                    ]
                    }'
             
        ];
      
    }
}
