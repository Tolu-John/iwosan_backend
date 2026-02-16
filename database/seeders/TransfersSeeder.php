<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TransfersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Transfers::factory(20)->create();
    }
}
