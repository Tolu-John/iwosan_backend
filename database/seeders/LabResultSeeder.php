<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LabResultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\LabResult::factory(10)->create();
    }
}
