<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CodeCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|exists:reset_code_passwords',
        ];
    }
}
