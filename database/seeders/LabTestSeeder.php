<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LabTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\LabTest::factory(20)->create();
    }
}
