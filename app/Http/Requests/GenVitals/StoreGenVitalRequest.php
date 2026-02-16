<?php

namespace App\Http\Requests\GenVitals;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => trim((string) $this->input('type')),
            'unit' => trim((string) $this->input('unit')),
            'context' => trim((string) $this->input('context')),
            'source' => trim((string) $this->input('source')),
            'device_name' => trim((string) $this->input('device_name')),
            'device_model' => trim((string) $this->input('device_model')),
            'device_serial' => trim((string) $this->input('device_serial')),
            'location' => trim((string) $this->input('location')),
            'notes' => trim((string) $this->input('notes')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer',
            'type' => 'required|string|in:temperature,heart_rate,respiratory_rate,blood_pressure,oxygen_saturation,blood_glucose,weight,height,bmi,pain_score',
            'unit' => 'required|string|max:20',
            'value' => 'nullable|numeric',
            'systolic' => 'required_if:type,blood_pressure|numeric',
            'diastolic' => 'required_if:type,blood_pressure|numeric',
            'pulse' => 'nullable|numeric',
            'taken_at' => 'required|date',
            'context' => 'required|string|in:resting,post_exercise,post_meal,fasting,pre_med,post_med,sleep,unknown',
            'source' => 'required|string|in:patient_manual,device_sync,clinic',
            'device_name' => 'nullable|string|max:100',
            'device_model' => 'nullable|string|max:100',
            'device_serial' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
