<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'push_notifications_enabled' => 'sometimes|boolean',
            'sms_alerts_enabled' => 'sometimes|boolean',
            'share_vitals_with_carers' => 'sometimes|boolean',
        ];
    }
}

