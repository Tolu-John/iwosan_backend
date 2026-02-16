<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsFallbackService
{
    public function sendSms(string $to, string $message): void
    {
        $apiKey = config('termii.api_key');
        $sender = config('termii.sender_id');
        $channel = config('termii.channel');
        $type = config('termii.type');
        $base = rtrim(config('termii.api_base'), '/');

        if (!$apiKey) {
            throw new \RuntimeException('TERMII_API_KEY is missing.');
        }

        $payload = [
            'to' => $to,
            'from' => $sender,
            'sms' => $message,
            'type' => $type,
            'channel' => $channel,
            'api_key' => $apiKey,
        ];

        $response = Http::post($base . '/api/sms/send', $payload);
        if (!$response->successful()) {
            Log::error('Termii SMS error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Termii SMS send failed.');
        }
    }
}
