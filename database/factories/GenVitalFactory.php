<?php

namespace Database\Factories;

use App\Models\Gen_Vital;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class GenVitalFactory extends Factory
{
    protected $model = Gen_Vital::class;

    public function definition()
    {
        return [
            'patient_id' => Patient::factory(),
            'name' => 'temperature',
            'type' => 'temperature',
            'value' => '37.2',
            'value_num' => 37.2,
            'unit' => 'C',
            'taken_at' => now()->subMinutes(5),
            'recorded_at' => now(),
            'context' => 'resting',
            'source' => 'patient_manual',
            'status_flag' => 'normal',
        ];
    }
}
