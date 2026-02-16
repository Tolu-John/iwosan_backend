<?php

return [
    'encryption_provider_enforced' => env('ENCRYPTION_PROVIDER_ENFORCED', false),
    'encryption_provider_name' => env('ENCRYPTION_PROVIDER_NAME'),
    'encryption_field_level' => env('ENCRYPTION_FIELD_LEVEL', false),
    'encryption_key_id' => env('ENCRYPTION_KEY_ID'),
    'encryption_key_rotation_days' => env('ENCRYPTION_KEY_ROTATION_DAYS', 365),
];
