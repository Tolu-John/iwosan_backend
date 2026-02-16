<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => trim((string) $this->input('type')),
            'code' => trim((string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|exists:reset_code_passwords',
            'password' => 'required|string|min:8|max:255',
            'type' => 'required|in:user,hospital',
        ];
    }
}
