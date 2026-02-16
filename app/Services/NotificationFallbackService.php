<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationFallbackService
{
    public function sendEmail(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Fallback email failed', ['error' => $e->getMessage()]);
        }
    }

    public function sendEmailTemplate(string $to, string $subject, string $view, array $data = []): void
    {
        try {
            Mail::send($view, $data, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Fallback email template failed', ['error' => $e->getMessage()]);
        }
    }
}
