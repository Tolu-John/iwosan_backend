<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ComplaintsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Complaints::factory(10)->create(); 
    }
}
