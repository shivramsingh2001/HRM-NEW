<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendancePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin', 'hr'], true);
    }

    public function rules(): array
    {
        return [
            'effective_from' => ['nullable', 'date'],

            'present_ratio' => ['required', 'numeric', 'min:0.1', 'max:1'],
            'half_day_ratio' => ['required', 'numeric', 'min:0', 'max:1', 'lte:present_ratio'],
            'fallback_present_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'fallback_half_hours' => ['required', 'numeric', 'min:0', 'max:24', 'lte:fallback_present_hours'],
            'full_day_min_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],

            'overtime_after_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'overtime_multiplier' => ['required', 'numeric', 'min:0', 'max:5'],

            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'rounding_minutes' => ['required', 'integer', 'min:0', 'max:60'],

            'late_halfday_enabled' => ['nullable', 'boolean'],
            // 0 = every late is a half day; 1..31 = tolerated per month; blank defaults to 30
            'monthly_late_allowance' => ['nullable', 'integer', 'min:0', 'max:31'],

            'min_rest_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'max_daily_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'sandwich_leave' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allowance = $this->input('monthly_late_allowance');

        $this->merge([
            'late_halfday_enabled' => $this->boolean('late_halfday_enabled'),
            'sandwich_leave' => $this->boolean('sandwich_leave'),
            // A blank field means "use the default" rather than "unlimited".
            'monthly_late_allowance' => ($allowance === '' || $allowance === null) ? 30 : $allowance,
        ]);
    }

    /**
     * Only the policy columns (drop effective_from — the controller handles it).
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        unset($data['effective_from']);

        return $data;
    }
}
