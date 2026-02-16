<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterHospitalRequest extends FormRequest
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
            'name' => trim((string) $this->input('name')),
            'firedb_id' => trim((string) $this->input('firedb_id')),
            'code' => trim((string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:hospitals',
            'password' => 'required|string|min:8|max:255',
            'firedb_id' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'code' => 'required|string|max:255',
        ];
    }
}
