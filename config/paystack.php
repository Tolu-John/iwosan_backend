<?php

return [
    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    'secret' => env('PAYSTACK_SECRET'),
    'mock' => (bool) env('PAYSTACK_MOCK', false),
];
