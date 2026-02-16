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
            'user.firedb_id' => 'required|string|max:255',
            'user.email' => 'required|email|max:255',
            'user.phone' => 'required|string|max:50',
            'user.gender' => 'required|string|max:20',
            'user.dob' => 'required|string|max:50',
            'user.address' => 'required|string|max:255',
            'user.lat' => 'required',
            'user.lon' => 'required',
            'user_id' => 'required|integer',
            'weight' => 'required',
            'bloodtype' => 'required|string|max:10',
            'genotype' => 'required|string|max:10',
            'sugar_level' => 'required',
            'bp_dia' => 'required',
            'bp_sys' => 'required',
            'height' => 'required',
            'temperature' => 'required',
            'kin_name' => 'required|string|max:255',
            'kin_phone' => 'required|string|max:50',
            'kin_address' => 'required|string|max:255',
            'other_kin_name' => 'required|string|max:255',
            'other_kin_phone' => 'required|string|max:50',
            'other_kin_address' => 'required|string|max:255',
        ];
    }
}
