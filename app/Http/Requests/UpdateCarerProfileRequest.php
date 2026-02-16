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
        $this->merge([
            'user' => array_merge($user, [
                'email' => strtolower(trim((string) ($user['email'] ?? ''))),
                'phone' => trim((string) ($user['phone'] ?? '')),
                'firstname' => trim((string) ($user['firstname'] ?? '')),
                'lastname' => trim((string) ($user['lastname'] ?? '')),
                'firedb_id' => trim((string) ($user['firedb_id'] ?? '')),
            ]),
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
            'user.address' => 'required|string|max:255',
            'user.lat' => 'required',
            'user.lon' => 'required',
            'hospital_id' => 'required|integer',
            'user_id' => 'required|integer',
            'bio' => 'required|string',
            'position' => 'required|string|max:255',
            'onHome_leave' => 'required',
            'onVirtual_leave' => 'required',
            'qualifications' => 'required|string',
            'virtual_day_time' => 'required|string',
            'home_day_time' => 'required|string',
            'super_admin_approved' => 'required',
            'admin_approved' => 'required',
            'service_radius_km' => 'nullable|integer|min:1|max:500',
            'response_time_minutes' => 'nullable|integer|min:1|max:1440',
        ];
    }
}
