<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CarerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Carer::factory(10)->create();
    }
}
