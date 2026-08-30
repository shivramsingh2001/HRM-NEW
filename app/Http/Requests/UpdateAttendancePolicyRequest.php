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
            'late_halfday_enabled' => ['nullable', 'boolean'],
            // null / blank = unlimited; 0 = every late is a half day; 1..31 = tolerated per month
            'monthly_late_allowance' => ['nullable', 'integer', 'min:0', 'max:31'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'late_halfday_enabled' => $this->boolean('late_halfday_enabled'),
            'monthly_late_allowance' => $this->input('monthly_late_allowance') === ''
                ? null
                : $this->input('monthly_late_allowance'),
        ]);
    }
}
