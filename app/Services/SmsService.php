<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS notification queued', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
