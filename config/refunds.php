<?php

return [
    'patient_cancel_hours' => env('REFUND_PATIENT_CANCEL_HOURS', 6),
    'patient_refund_hours' => env('REFUND_PATIENT_WINDOW_HOURS', 24),
    'hospital_override' => env('REFUND_HOSPITAL_OVERRIDE', true),
];
