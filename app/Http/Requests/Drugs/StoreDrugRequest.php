<?php

namespace App\Http\Requests\Drugs;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreDrugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'drug_type' => trim((string) $this->input('drug_type')),
            'dosage' => trim((string) $this->input('dosage')),
            'extra_notes' => trim((string) $this->input('extra_notes')),
            'quantity' => trim((string) $this->input('quantity')),
            'duration' => trim((string) $this->input('duration')),
            'status' => trim((string) $this->input('status')),
            'start_date' => trim((string) $this->input('start_date')),
            'stop_date' => trim((string) $this->input('stop_date')),
            'carer_name' => trim((string) $this->input('carer_name')),
        ]);
    }

    public function rules(): array
    {
        return [
            'consultation_id' => 'nullable|integer|required_without:ward_id',
            'ward_id' => 'nullable|integer|required_without:consultation_id',
            'drug_type' => 'required|string|max:100',
            'dosage' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'duration' => 'required|string|max:100',
            'quantity' => 'required|string|max:100',
            'start_date' => 'required|date_format:Y-m-d',
            'stop_date' => 'nullable|string|max:50',
            'status' => 'required|string|in:active,completed,discontinued',
            'status_reason' => 'nullable|string|max:500',
            'extra_notes' => 'nullable|string|max:5000',
            'carer_name' => 'required|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startDate = $this->input('start_date');
            if (!$startDate) {
                return;
            }

            try {
                $date = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            } catch (\Throwable $e) {
                return;
            }

            $today = Carbon::today();
            $min = $today->copy()->subDays(30);

            if ($date->greaterThan($today)) {
                $validator->errors()->add('start_date', 'Start date cannot be in the future.');
            }

            if ($date->lessThan($min)) {
                $validator->errors()->add('start_date', 'Start date cannot be more than 30 days in the past.');
            }
        });
    }
}
