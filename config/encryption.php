<?php

return [
    'provider_enforced' => env('ENCRYPTION_PROVIDER_ENFORCED', false),
    'provider_name' => env('ENCRYPTION_PROVIDER_NAME', 'managed-db'),
    'field_level_enabled' => env('ENCRYPTION_FIELD_LEVEL', false),
    'key_id' => env('ENCRYPTION_KEY_ID', null),
];
