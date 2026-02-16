<?php

namespace Database\Factories;

use App\Models\Carer;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Teletest;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabResultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LabResult::class;

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
            'teletest_id'=>Teletest::factory(),
            'name'=>$this->faker->name,
          'lab_name'=>$this->faker->name,
          'result_picture'=>$this->faker->imageUrl,
          'result_picture_front'=>null,
          'result_picture_back'=>null,
          'extra_notes'=>$this->faker->paragraph,

        ];
    }
}
