<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public function sendText(string $to, string $body): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ];

        return $this->postMessage($payload);
    }

    public function sendTemplate(string $to, string $templateName, string $languageCode = 'en', array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        return $this->postMessage($payload);
    }

    public function sendMedia(string $to, string $type, string $mediaUrl, ?string $caption = null): array
    {
        $supported = ['image', 'document', 'video', 'audio'];
        if (!in_array($type, $supported, true)) {
            throw new \InvalidArgumentException('Unsupported media type.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $type,
            $type => array_filter([
                'link' => $mediaUrl,
                'caption' => $caption,
            ], fn ($value) => $value !== null),
        ];

        return $this->postMessage($payload);
    }

    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = config('whatsapp.webhook_secret');
        if (!$secret) {
            return true;
        }
        if (!$signatureHeader || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signatureHeader);
    }

    private function postMessage(array $payload): array
    {
        $token = config('whatsapp.token');
        $phoneId = config('whatsapp.phone_number_id');
        $base = rtrim(config('whatsapp.api_base'), '/');

        if (!$token || !$phoneId) {
            throw new \RuntimeException('WhatsApp API credentials are missing.');
        }

        $url = $base . '/' . $phoneId . '/messages';
        $response = Http::withToken($token)->post($url, $payload);

        if (!$response->successful()) {
            Log::error('WhatsApp API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('WhatsApp API request failed.');
        }

        return $response->json();
    }
}
