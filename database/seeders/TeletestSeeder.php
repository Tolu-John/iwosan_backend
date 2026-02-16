<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TeletestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Teletest::factory(10)->create();
    }
}
