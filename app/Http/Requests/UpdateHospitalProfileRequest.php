<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHospitalProfileRequest extends FormRequest
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
            'code' => trim((string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'about_us' => 'required|string',
            'website' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'code' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:50',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'lat' => 'required',
            'lon' => 'required',
        ];
    }
}
