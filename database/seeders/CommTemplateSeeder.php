<?php

namespace Database\Seeders;

use App\Models\CommTemplate;
use Illuminate\Database\Seeder;

class CommTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'provider' => 'whatsapp',
                'name' => 'appointment_reminder',
                'language' => 'en',
                'variables' => ['patient_name', 'appointment_time'],
                'active' => true,
            ],
            [
                'provider' => 'whatsapp',
                'name' => 'payment_reminder',
                'language' => 'en',
                'variables' => ['patient_name', 'amount'],
                'active' => true,
            ],
            [
                'provider' => 'whatsapp',
                'name' => 'lab_result_ready',
                'language' => 'en',
                'variables' => ['patient_name', 'result_link'],
                'active' => true,
            ],
        ];

        foreach ($templates as $template) {
            CommTemplate::updateOrCreate(
                [
                    'provider' => $template['provider'],
                    'name' => $template['name'],
                    'language' => $template['language'],
                ],
                $template
            );
        }
    }
}
