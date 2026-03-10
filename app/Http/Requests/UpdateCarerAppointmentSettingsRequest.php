<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarerAppointmentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $virtualDayTime = $this->normalizeScheduleInput($this->input('virtual_day_time'));
        $homeDayTime = $this->normalizeScheduleInput($this->input('home_day_time'));

        $this->merge([
            'virtual_day_time' => $virtualDayTime,
            'home_day_time' => $homeDayTime,
        ]);
    }

    public function rules(): array
    {
        return [
            'onHome_leave' => 'nullable|in:0,1,true,false',
            'onVirtual_leave' => 'nullable|in:0,1,true,false',
            'virtual_day_time' => 'nullable|string',
            'home_day_time' => 'nullable|string',
            'service_radius_km' => 'nullable|integer|min:1|max:500',
            'response_time_minutes' => 'nullable|integer|min:1|max:1440',
        ];
    }

    private function normalizeScheduleInput($value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return (string) $value;
    }
}
