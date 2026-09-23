<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEarlyLeavingPolicySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'monthly_early_allowance' => ['required', 'integer', 'min:0', 'max:31'],
            'early_attendance_action' => ['required', 'in:none,half_day,absent'],
            'early_deduction_enabled' => ['nullable', 'boolean'],
            'early_deduction_mode' => ['required_if:early_deduction_enabled,1', 'nullable', 'in:fixed_amount,half_day,full_day,custom_multiplier'],
            'early_deduction_amount' => ['required_if:early_deduction_mode,fixed_amount', 'nullable', 'numeric', 'min:0'],
            'early_deduction_multiplier' => ['required_if:early_deduction_mode,custom_multiplier', 'nullable', 'numeric', 'min:0', 'max:5'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'early_deduction_enabled' => $this->boolean('early_deduction_enabled'),
        ]);
    }
}
