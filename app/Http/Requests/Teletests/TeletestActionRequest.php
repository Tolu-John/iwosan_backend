<?php

namespace App\Http\Requests\Teletests;

use Illuminate\Foundation\Http\FormRequest;

class TeletestActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statusKeys = array_keys((array) config('teletest_workflow.statuses', []));

        return [
            'target_status' => ['nullable', 'string', 'in:' . implode(',', $statusKeys)],
            'reason_code' => ['nullable', 'string', 'max:80'],
            'reason_note' => ['nullable', 'string', 'max:2000'],
            'check_in_confirmed' => ['nullable', 'boolean'],
            'eta_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'reassigned_to' => ['nullable', 'integer'],
            'actor_id' => ['nullable', 'integer'],
            'sample_evidence' => ['nullable', 'array'],
            'sample_evidence.*' => ['nullable'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

