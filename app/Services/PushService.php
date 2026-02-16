<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PushService
{
    public function send(?string $deviceToken, string $title, string $message, array $data = []): void
    {
        if (!$deviceToken) {
            return;
        }

        Log::info('Push notification queued', [
            'device_token' => $deviceToken,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
