<?php

return [
    'phi_access_log_enabled' => env('PHI_ACCESS_LOG_ENABLED', true),
    'phi_access_log_exempt_routes' => array_filter(explode(',', env('PHI_ACCESS_LOG_EXEMPT_ROUTES', 'patient/login,patient/register,carer/login,carer/register,hospital/login,hospital/register,v1/patient/login,v1/patient/register,v1/carer/login,v1/carer/register,v1/hospital/login,v1/hospital/register,forgot_password*,v1/forgot_password*,payment/webhook,v1/payment/webhook,webhooks/whatsapp,v1/webhooks/whatsapp,metrics/daily,v1/metrics/daily'))),
    'platform_consent_required' => env('PLATFORM_CONSENT_REQUIRED', true),
    'platform_consent_exempt_routes' => array_filter(explode(',', env('PLATFORM_CONSENT_EXEMPT_ROUTES', 'v1/consents/platform,v1/consents/whatsapp'))),
    'incident_notify_emails' => array_filter(explode(',', env('INCIDENT_NOTIFY_EMAILS', ''))),
    'incident_notify_sms' => array_filter(explode(',', env('INCIDENT_NOTIFY_SMS', ''))),
];
