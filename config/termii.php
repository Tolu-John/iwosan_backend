<?php

return [
    'api_key' => env('TERMII_API_KEY'),
    'sender_id' => env('TERMII_SENDER_ID', 'IWOSAN'),
    'channel' => env('TERMII_CHANNEL', 'generic'),
    'type' => env('TERMII_MESSAGE_TYPE', 'plain'),
    'api_base' => env('TERMII_API_BASE', 'https://api.ng.termii.com'),
];
