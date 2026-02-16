<?php

namespace App\Console\Commands;

use App\Models\CommEvent;
use App\Models\CommTemplate;
use App\Models\Payment;
use App\Services\WhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendPaymentReminders extends Command
{
    protected $signature = 'iwosan:send-payment-reminders';
    protected $description = 'Send WhatsApp payment reminders for unpaid payments.';

    public function handle(WhatsappService $whatsapp): int
    {
        $payments = Payment::whereNull('paid_at')
            ->whereNotIn('status', ['paid', 'success', 'completed'])
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        $template = CommTemplate::where('provider', 'whatsapp')
            ->where('name', 'payment_reminder')
            ->where('active', true)
            ->first();

        foreach ($payments as $payment) {
            $patient = $payment->patient;
            $phone = optional(optional($patient)->user)->phone;
            if (!$phone) {
                continue;
            }

            $event = CommEvent::create([
                'direction' => 'outbound',
                'event_type' => 'payment_reminder',
                'sender_role' => 'system',
                'delivery_status' => 'pending',
                'event_timestamp' => Carbon::now(),
                'metadata' => [
                    'to' => $phone,
                    'payment_id' => $payment->id,
                ],
            ]);

            try {
                if ($template) {
                    $components = [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => optional($patient->user)->firstname ?? 'Patient'],
                                ['type' => 'text', 'text' => (string) ($payment->price ?? '')],
                            ],
                        ],
                    ];
                    $whatsapp->sendTemplate($phone, $template->name, $template->language, $components);
                } else {
                    $whatsapp->sendText($phone, 'Iwosan reminder: your payment is pending.');
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
