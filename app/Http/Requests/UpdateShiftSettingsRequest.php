<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftSettingsRequest extends FormRequest
{
    public const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin', 'hr'], true);
    }

    public function rules(): array
    {
        return [
            'custom_shifts_enabled' => ['nullable', 'boolean'],
            // The fixed company shift — required only when custom shifts are off.
            'start_time' => ['required_if:custom_shifts_enabled,0', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:custom_shifts_enabled,0', 'nullable', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'break_time' => ['nullable', 'integer', 'min:0', 'max:180'],
            'weekoff_days' => ['nullable', 'array'],
            'weekoff_days.*' => [Rule::in(self::WEEKDAYS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'custom_shifts_enabled' => $this->boolean('custom_shifts_enabled'),
            'grace_minutes' => $this->input('grace_minutes') === '' ? null : $this->input('grace_minutes'),
            'break_time' => $this->input('break_time') === '' ? null : $this->input('break_time'),
        ]);
    }
}
