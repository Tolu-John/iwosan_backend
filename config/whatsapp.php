<?php

return [
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
    'api_base' => env('WHATSAPP_API_BASE', 'https://graph.facebook.com/v20.0'),
];
