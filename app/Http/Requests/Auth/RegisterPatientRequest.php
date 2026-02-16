<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
            'firstname' => trim((string) $this->input('firstname')),
            'lastname' => trim((string) $this->input('lastname')),
            'firedb_id' => trim((string) $this->input('firedb_id')),
        ]);
    }

    public function rules(): array
    {
        return [
            'firedb_id' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'required|string|max:50',
            'password' => 'required|string|min:8|max:255',
        ];
    }
}
