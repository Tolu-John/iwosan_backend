<?php

namespace Database\Seeders;

use App\Models\Hospital;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
  
        $this->call(UserSeeder::class);
        $this->call(HospitalSeeder::class);
       $this->call(PatientSeeder::class);
       $this->call(CarerSeeder::class);


       $this->call(PaymentSeeder::class);
       $this->call(ConsultationSeeder::class);
       $this->call(TeletestSeeder::class);
       
       $this->call(AppointmentSeeder::class);
       $this->call(ReviewSeeder::class);

       $this->call(CommTemplateSeeder::class);
       
       $this->call(VConsultationSeeder::class);
       $this->call(HConsultationSeeder::class);
       $this->call(DrugSeeder::class);

    
       $this->call(TransfersSeeder::class);

       

     

       $this->call(LabResultSeeder::class);
       $this->call(LabTestSeeder::class);
      
       $this->call(ComplaintsSeeder::class);

       $this->call(TestSeeder::class);
   

    }
}
