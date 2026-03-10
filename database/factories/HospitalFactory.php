<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        $firedbId = (string) $this->faker->unique()->numerify('##########');
        $email = $this->faker->unique()->safeEmail();
        $passwordHash = Hash::make('password');

        $admin = User::factory()->create([
            'email' => $email,
            'password' => $passwordHash,
            'firedb_id' => $firedbId,
            'firstname' => $this->faker->firstName(),
            'lastname' => 'admin',
            'remember_token' => Str::random(10),
        ]);

        return [
            'name'=>$this->faker->name,
            'code' => strtoupper($this->faker->bothify('HSP####??')),
            'rating'=>$this->faker->randomFloat($nbMaxDecimals = 1, $min = 0, $max = 5),
            'firedb_id' => $firedbId,
            'about_us'=>$this->faker->paragraph($nbSentences = 6, $variableNbSentences = true),
            'website'=>$this->faker->url,
            'hospital_img'=>$this->faker->imageUrl,
            'email'=>$email,
            'phone'=>$this->faker->phoneNumber,
            'lat'=>$this->faker->latitude($min = -90, $max = 90),
            'lon'=>$this->faker->longitude($min = -180, $max = 180),
            'password'=>$passwordHash,
            'address'=>$this->faker->address,
            'super_admin_approved'=>$this->faker->boolean,
            'user_id' => $admin->id,
            'admin_id' => $admin->id,
        ];
    }
}
