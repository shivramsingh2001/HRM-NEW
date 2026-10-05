<?php

namespace App\Http\Requests;

use App\Services\RbacService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Company Policies → Overtime cards. `section` = mode (switch, mode, start rule)
 * or limits (min / per-day / per-month hours, rate, approval rules); only that
 * card's fields are validated and saved.
 */
class UpdateOvertimePolicySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user && in_array($user->role, ['admin', 'hr'], true)
            && app(RbacService::class)->can($user, 'overtime', 'manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->input('section') === 'limits') {
            return [
                'section' => ['required', 'in:mode,limits'],
                'min_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
                'max_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24', 'gte:min_hours'],
                'max_hours_per_month' => ['nullable', 'numeric', 'min:0', 'max:744'],
                'rate_type' => ['required', 'in:multiplier,fixed'],
                'rate_multiplier' => ['required_if:rate_type,multiplier', 'nullable', 'numeric', 'min:1', 'max:5'],
                'fixed_rate_per_hour' => ['required_if:rate_type,fixed', 'nullable', 'numeric', 'min:1', 'max:100000'],
                'require_approval' => ['nullable', 'boolean'],
                'auto_approve_limit' => ['nullable', 'numeric', 'min:0', 'max:24'],
            ];
        }

        return [
            'section' => ['required', 'in:mode,limits'],
            'enabled' => ['nullable', 'boolean'],
            'mode' => ['required', 'in:request,auto'],
            'auto_start_basis' => ['required_if:mode,auto', 'nullable', 'in:grace,fixed'],
            'auto_start_after_minutes' => ['required_if:auto_start_basis,fixed', 'nullable', 'integer', 'min:0', 'max:600'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_hours_per_day.gte' => 'Maximum hours per day cannot be less than the minimum.',
            'rate_multiplier.required_if' => 'Enter the overtime rate multiplier.',
            'fixed_rate_per_hour.required_if' => 'Enter the fixed overtime pay per hour.',
            'auto_start_after_minutes.required_if' => 'Enter after how many minutes overtime starts.',
        ];
    }
}
