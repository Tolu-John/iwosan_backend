<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\CommEvent;
use App\Models\CommThread;
use App\Models\CommTemplate;
use App\Services\WhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendAppointmentReminders extends Command
{
    protected $signature = 'iwosan:send-appointment-reminders';
    protected $description = 'Send WhatsApp appointment reminders for upcoming appointments.';

    public function handle(WhatsappService $whatsapp): int
    {
        $windowStart = Carbon::now()->addHours(23);
        $windowEnd = Carbon::now()->addHours(24);

        $appointments = Appointment::whereNotIn('status', ['cancelled', 'completed'])
            ->get()
            ->filter(function ($appointment) use ($windowStart, $windowEnd) {
                try {
                    $time = Carbon::parse($appointment->date_time);
                } catch (\Throwable $e) {
                    return false;
                }
                return $time->between($windowStart, $windowEnd);
            });

        $template = CommTemplate::where('provider', 'whatsapp')
            ->where('name', 'appointment_reminder')
            ->where('active', true)
            ->first();

        foreach ($appointments as $appointment) {
            $consultationId = $appointment->consult_id;
            if (!$consultationId) {
                continue;
            }
            $phone = optional(optional($appointment->patient)->user)->phone;
            if (!$phone) {
                continue;
            }

            $thread = CommThread::firstOrCreate(
                ['consultation_id' => $consultationId, 'channel' => 'whatsapp'],
                ['status' => 'active']
            );

            $event = CommEvent::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'event_type' => 'reminder',
                'sender_role' => 'system',
                'delivery_status' => 'pending',
                'event_timestamp' => Carbon::now(),
                'metadata' => [
                    'to' => $phone,
                    'appointment_id' => $appointment->id,
                ],
            ]);

            try {
                if ($template) {
                    $components = [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => optional($appointment->patient->user)->firstname ?? 'Patient'],
                                ['type' => 'text', 'text' => (string) $appointment->date_time],
                            ],
                        ],
                    ];
                    $whatsapp->sendTemplate($phone, $template->name, $template->language, $components);
                } else {
                    $whatsapp->sendText($phone, 'Iwosan reminder: you have an upcoming appointment.');
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
