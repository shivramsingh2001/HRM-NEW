<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDayClassificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    protected function prepareForValidation(): void
    {
        // Unchecked switch posts nothing.
        $this->merge(['day_classification_enabled' => $this->boolean('day_classification_enabled')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // The thresholds are only needed while classification is on; turning it
        // off keeps the stored values for when it is switched back on.
        return [
            'day_classification_enabled' => ['required', 'boolean'],
            'present_ratio' => ['exclude_unless:day_classification_enabled,true', 'required', 'numeric', 'min:0.1', 'max:1'],
            'half_day_ratio' => ['exclude_unless:day_classification_enabled,true', 'required', 'numeric', 'min:0', 'max:1', 'lte:present_ratio'],
            'fallback_present_hours' => ['exclude_unless:day_classification_enabled,true', 'required', 'numeric', 'min:1', 'max:24'],
            'fallback_half_hours' => ['exclude_unless:day_classification_enabled,true', 'required', 'numeric', 'min:0', 'max:24', 'lte:fallback_present_hours'],
        ];
    }
}
