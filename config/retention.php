<?php

return [
    'phi_access_logs_days' => env('RETENTION_PHI_ACCESS_LOGS_DAYS', 730),
    'security_incidents_days' => env('RETENTION_SECURITY_INCIDENTS_DAYS', 730),
    'comm_events_days' => env('RETENTION_COMM_EVENTS_DAYS', 730),
    'vital_audit_logs_days' => env('RETENTION_VITAL_AUDIT_LOGS_DAYS', 730),
    'ward_vital_audit_logs_days' => env('RETENTION_WARD_VITAL_AUDIT_LOGS_DAYS', 730),
    'ward_audit_logs_days' => env('RETENTION_WARD_AUDIT_LOGS_DAYS', 730),
    'lab_result_audit_logs_days' => env('RETENTION_LAB_RESULT_AUDIT_LOGS_DAYS', 730),
    'review_audit_logs_days' => env('RETENTION_REVIEW_AUDIT_LOGS_DAYS', 730),
    'complaint_audit_logs_days' => env('RETENTION_COMPLAINT_AUDIT_LOGS_DAYS', 730),
    'payment_audit_logs_days' => env('RETENTION_PAYMENT_AUDIT_LOGS_DAYS', 730),
    'certlice_audit_logs_days' => env('RETENTION_CERTLICE_AUDIT_LOGS_DAYS', 730),
    'temp_files_days' => env('RETENTION_TEMP_FILES_DAYS', 30),
    'force_delete' => env('RETENTION_FORCE_DELETE', false),
];
