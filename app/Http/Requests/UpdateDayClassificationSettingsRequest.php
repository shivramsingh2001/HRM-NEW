<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDayClassificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'present_ratio' => ['required', 'numeric', 'min:0.1', 'max:1'],
            'half_day_ratio' => ['required', 'numeric', 'min:0', 'max:1', 'lte:present_ratio'],
            'fallback_present_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'fallback_half_hours' => ['required', 'numeric', 'min:0', 'max:24', 'lte:fallback_present_hours'],
        ];
    }
}
