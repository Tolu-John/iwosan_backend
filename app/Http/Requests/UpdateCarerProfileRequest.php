<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->input('user', []);
        $specialties = $this->input('specialties');
        $virtualDayTime = $this->normalizeScheduleInput($this->input('virtual_day_time'));
        $homeDayTime = $this->normalizeScheduleInput($this->input('home_day_time'));
        if (is_string($specialties)) {
            $specialties = array_filter(array_map('trim', explode(',', $specialties)));
        }

        $this->merge([
            'user' => array_merge($user, [
                'email' => strtolower(trim((string) ($user['email'] ?? ''))),
                'phone' => trim((string) ($user['phone'] ?? '')),
                'firstname' => trim((string) ($user['firstname'] ?? '')),
                'lastname' => trim((string) ($user['lastname'] ?? '')),
                'firedb_id' => trim((string) ($user['firedb_id'] ?? '')),
            ]),
            'primary_qualification' => trim((string) $this->input('primary_qualification', '')),
            'license_number' => trim((string) $this->input('license_number', '')),
            'issuing_body' => trim((string) $this->input('issuing_body', '')),
            'specialties' => $specialties,
            'virtual_day_time' => $virtualDayTime,
            'home_day_time' => $homeDayTime,
        ]);
    }

    public function rules(): array
    {
        return [
            'user' => 'required|array',
            'user.id' => 'required|integer',
            'user.firstname' => 'required|string|max:255',
            'user.lastname' => 'required|string|max:255',
            'user.firedb_id' => 'required|string|max:255',
            'user.email' => 'required|email|max:255',
            'user.phone' => 'required|string|max:50',
            'user.gender' => 'required|string|max:20',
            'user.address' => 'nullable|string|max:255',
            'user.lat' => 'nullable',
            'user.lon' => 'nullable',
            'hospital_id' => 'required|integer',
            'user_id' => 'required|integer',
            'bio' => 'required|string',
            'position' => 'required|string|max:255',
            'onHome_leave' => 'nullable|in:0,1,true,false',
            'onVirtual_leave' => 'nullable|in:0,1,true,false',
            'qualifications' => 'nullable|string',
            'primary_qualification' => 'nullable|string|max:255',
            'specialties' => 'nullable|array',
            'specialties.*' => 'string|max:100',
            'license_number' => 'nullable|string|max:255',
            'issuing_body' => 'nullable|string|max:255',
            'years_experience' => 'nullable|integer|min:0|max:80',
            'virtual_day_time' => 'nullable|string',
            'home_day_time' => 'nullable|string',
            'super_admin_approved' => 'nullable|in:0,1,true,false',
            'admin_approved' => 'nullable|in:0,1,true,false',
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
