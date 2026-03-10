<?php

namespace Database\Factories;

use App\Models\VConsultation;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Factories\Factory;

class VConsultationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VConsultation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $consultationId = Consultation::query()->inRandomOrder()->value('id');
        if (!$consultationId) {
            $consultationId = Consultation::factory()->create()->id;
        }

        return [
            'consultation_id' => $consultationId,
            'consult_type' => $this->faker->randomElement(['NOW', 'PRE_PLANNED']),
            'duration' => $this->faker->time($format = 'H:i:s'),
        ];
    }
}
