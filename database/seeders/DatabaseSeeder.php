<?php

namespace Database\Seeders;

use App\Models\Hospital;
use Illuminate\Database\Seeder;
use Throwable;

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

        // Optional legacy-domain seeders: continue even if legacy FK data drifts.
        $this->callOptional(TransfersSeeder::class);
        $this->callOptional(LabResultSeeder::class);
        $this->callOptional(LabTestSeeder::class);
        $this->callOptional(ComplaintsSeeder::class);
        $this->callOptional(TestSeeder::class);
    }

    private function callOptional(string $seeder): void
    {
        try {
            $this->call($seeder);
        } catch (Throwable $e) {
            if ($this->command) {
                $this->command->warn("Optional seeder failed: {$seeder} - {$e->getMessage()}");
            }
        }
    }
}
