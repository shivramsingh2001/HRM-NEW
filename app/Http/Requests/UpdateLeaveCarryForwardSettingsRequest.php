<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveCarryForwardSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'leave_carry_forward_enabled' => ['nullable', 'boolean'],
            'leave_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            // 1–28 so the start exists in every month (incl. February).
            'leave_year_start_day' => ['required', 'integer', 'min:1', 'max:28'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_year_start_day.max' => 'Pick a day from 1 to 28 so the leave year starts on the same date every year.',
        ];
    }
}
