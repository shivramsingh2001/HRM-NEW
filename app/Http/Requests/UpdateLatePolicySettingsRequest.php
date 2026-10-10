<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLatePolicySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Optional: make the change count from an earlier day (saved days are re-graded).
            'apply_from' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:' . now()->subYear()->toDateString()],
            'grace_mode' => ['required', 'in:shift,fixed'],
            'fixed_grace_minutes' => ['required_if:grace_mode,fixed', 'nullable', 'integer', 'min:0', 'max:240'],
            'monthly_late_allowance' => ['required', 'integer', 'min:0', 'max:31'],
            'late_attendance_action' => ['required', 'in:none,half_day,absent'],
            'late_deduction_enabled' => ['nullable', 'boolean'],
            'late_deduction_mode' => ['required_if:late_deduction_enabled,1', 'nullable', 'in:fixed_amount,half_day,full_day,custom_multiplier'],
            'late_deduction_amount' => ['required_if:late_deduction_mode,fixed_amount', 'nullable', 'numeric', 'min:0'],
            'late_deduction_multiplier' => ['required_if:late_deduction_mode,custom_multiplier', 'nullable', 'numeric', 'min:0', 'max:5'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'late_deduction_enabled' => $this->boolean('late_deduction_enabled'),
        ]);
    }
}
