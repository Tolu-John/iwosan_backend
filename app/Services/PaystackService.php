<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    public function request(string $method, string $endpoint, array $payload = [])
    {
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
}
