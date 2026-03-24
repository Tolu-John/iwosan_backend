<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientProfileRequest extends FormRequest
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
            'user.firedb_id' => 'sometimes|nullable|string|max:255',
            'user.email' => 'required|email|max:255',
            'user.phone' => 'required|string|max:50',
            'user.gender' => 'sometimes|nullable|string|max:20',
            'user.dob' => 'sometimes|nullable|string|max:50',
            'user.address' => 'sometimes|nullable|string|max:255',
            'user.lat' => 'sometimes|nullable',
            'user.lon' => 'sometimes|nullable',
            'user_id' => 'required|integer',
            'weight' => 'sometimes|nullable',
            'bloodtype' => 'sometimes|nullable|string|max:20',
            'genotype' => 'sometimes|nullable|string|max:20',
            'sugar_level' => 'sometimes|nullable',
            'bp_dia' => 'sometimes|nullable',
            'bp_sys' => 'sometimes|nullable',
            'height' => 'sometimes|nullable',
            'temperature' => 'sometimes|nullable',
            'kin_name' => 'sometimes|nullable|string|max:255',
            'kin_phone' => 'sometimes|nullable|string|max:50',
            'kin_address' => 'sometimes|nullable|string|max:255',
            'other_kin_name' => 'sometimes|nullable|string|max:255',
            'other_kin_phone' => 'sometimes|nullable|string|max:50',
            'other_kin_address' => 'sometimes|nullable|string|max:255',
        ];
    }
}
