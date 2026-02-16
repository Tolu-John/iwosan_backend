<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VConsultationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\VConsultation::factory(5)->create();
    }
}
