<?php

namespace App\Console\Commands;

use App\Models\CommEvent;
use App\Models\CommTemplate;
use App\Models\LabResult;
use App\Services\WhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendLabResultNotifications extends Command
{
    protected $signature = 'iwosan:send-lab-result-notifications';
    protected $description = 'Send WhatsApp notifications when lab results are uploaded.';

    public function handle(WhatsappService $whatsapp): int
    {
        $since = Carbon::now()->subHours(2);

        $results = LabResult::whereNotNull('uploaded_at')
            ->where('uploaded_at', '>=', $since)
            ->orderBy('uploaded_at', 'desc')
            ->limit(200)
            ->get();

        $template = CommTemplate::where('provider', 'whatsapp')
            ->where('name', 'lab_result_ready')
            ->where('active', true)
            ->first();

        foreach ($results as $result) {
            $patient = $result->patient;
            $phone = optional(optional($patient)->user)->phone;
            if (!$phone) {
                continue;
            }

            $event = CommEvent::create([
                'direction' => 'outbound',
                'event_type' => 'lab_result_ready',
                'sender_role' => 'system',
                'delivery_status' => 'pending',
                'event_timestamp' => Carbon::now(),
                'metadata' => [
                    'to' => $phone,
                    'lab_result_id' => $result->id,
                ],
            ]);

            try {
                if ($template) {
                    $components = [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => optional($patient->user)->firstname ?? 'Patient'],
                                ['type' => 'text', 'text' => 'Check your Iwosan app for details.'],
                            ],
                        ],
                    ];
                    $whatsapp->sendTemplate($phone, $template->name, $template->language, $components);
                } else {
                    $whatsapp->sendText($phone, 'Iwosan: your lab results are ready.');
                }
                $event->delivery_status = 'sent';
            } catch (\Throwable $e) {
                $event->delivery_status = 'failed';
                $event->metadata = array_merge($event->metadata ?? [], ['error' => $e->getMessage()]);
            }
            $event->save();
        }

        return self::SUCCESS;
    }
}
