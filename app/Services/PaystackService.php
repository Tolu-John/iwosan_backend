<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    public function request(string $method, string $endpoint, array $payload = [])
    {
        if ((bool) config('paystack.mock', false)) {
            return $this->mockResponse($method, $endpoint, $payload);
        }

        $secret = config('paystack.secret');

        if (empty($secret)) {
            return response()->json([
                'message' => 'Paystack secret key is not configured',
            ], 500);
        }

        $client = Http::withToken($secret)
            ->acceptJson()
            ->asForm();

        $url = rtrim(config('paystack.base_url'), '/') . '/' . ltrim($endpoint, '/');

        $response = $client->{$method}($url, $payload);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Paystack request failed',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], 502);
        }

        return $response->json();
    }

    private function mockResponse(string $method, string $endpoint, array $payload): array
    {
        $reference = $payload['reference'] ?? ('MOCK-REF-' . now()->timestamp);

        if ($method === 'post' && trim($endpoint, '/') === 'transaction/initialize') {
            return [
                'status' => true,
                'message' => 'Mock transaction initialized',
                'data' => [
                    'authorization_url' => 'https://example.com/mock-paystack/' . $reference,
                    'access_code' => 'MOCK_ACCESS_CODE',
                    'reference' => $reference,
                ],
            ];
        }

        if ($method === 'get' && str_starts_with(trim($endpoint, '/'), 'transaction/verify/')) {
            $parts = explode('/', trim($endpoint, '/'));
            $verifyRef = end($parts) ?: $reference;

            return [
                'status' => true,
                'message' => 'Mock verification successful',
                'data' => [
                    'id' => 'MOCK_TX_' . now()->timestamp,
                    'reference' => $verifyRef,
                    'status' => 'success',
                    'channel' => 'card',
                    'currency' => 'NGN',
                    'fees' => 0,
                ],
            ];
        }

        return [
            'status' => true,
            'message' => 'Mock Paystack response',
            'data' => [],
        ];
    }
}
