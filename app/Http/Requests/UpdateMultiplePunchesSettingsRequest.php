<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMultiplePunchesSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allow_multiple_punches' => ['nullable', 'boolean'],
            // A clock-in left open this long is closed by attendance:auto-clockout.
            'auto_clockout_hours' => ['nullable', 'integer', 'min:4', 'max:48'],
        ];
    }
}
